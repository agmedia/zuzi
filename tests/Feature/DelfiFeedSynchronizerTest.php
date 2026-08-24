<?php

namespace Tests\Feature;

use App\Models\Back\Catalog\DelfiImportProduct;
use App\Services\Delfi\DelfiFeedNormalizer;
use App\Services\Delfi\DelfiFeedService;
use App\Services\Delfi\DelfiFeedSynchronizer;
use App\Services\Delfi\DelfiImportSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class DelfiFeedSynchronizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_persist_genre_mapping_as_an_array(): void
    {
        $settings = app(DelfiImportSettings::class)->save([
            'map_source_publishers' => 0,
            'genre_category_map' => [
                'Fantastika' => 42,
                'Bez kategorije' => 0,
                '  Drama  ' => 17,
            ],
        ]);

        $this->assertFalse($settings['map_source_publishers']);
        $this->assertSame([
            'Drama' => 17,
            'Fantastika' => 42,
        ], $settings['genre_category_map']);
        $this->assertDatabaseHas('settings', [
            'code' => 'delfi_import',
            'key' => 'genre_category_map',
            'json' => 1,
        ]);
    }

    public function test_sync_is_incremental_and_accepts_only_exact_book_categories(): void
    {
        $firstFeed = $this->temporaryFeed([
            $this->item('BOOK-1', 101, 'Prva', 'Knjiga', 1000),
            $this->item('FOREIGN-1', 102, 'Second', 'Strana knjiga', 1200),
            $this->item('GIFT-1', 103, 'Poklon', 'Gift', 500),
            $this->item('LOWER-1', 104, 'Krivi zapis', 'knjiga', 500),
            $this->item('INVALID-1', 105, '', 'Knjiga', 500),
        ]);
        $secondFeed = $this->temporaryFeed([
            $this->item('BOOK-1', 101, 'Prva - novo izdanje', 'Knjiga', 1100),
            $this->item('BOOK-2', 106, 'Treća', 'Knjiga', 1300),
        ]);

        try {
            $first = app(DelfiFeedSynchronizer::class)->syncFile($firstFeed);
            $this->assertSame(2, $first['staged']);
            $this->assertSame(2, $first['skipped_category']);
            $this->assertSame(1, $first['skipped']);
            $this->assertDatabaseMissing('delfi_import_products', ['external_id' => 'GIFT-1']);
            $this->assertDatabaseMissing('delfi_import_products', ['external_id' => 'LOWER-1']);

            DelfiImportProduct::query()->where('external_id', 'BOOK-1')->update([
                'product_id' => 987,
                'isbn' => '9788652000100',
                'source_genres' => json_encode(['Fantastika']),
                'description' => 'Obogaćeni API opis',
                'source_publisher' => 'Obogaćeni nakladnik',
                'image_url' => 'https://delfi.rs/_img/enriched-cover.png',
                'additional_image_urls' => json_encode(['https://delfi.rs/_img/enriched-extra.png']),
                'author' => 'Obogaćeni autor',
                'check_status' => 'matched',
            ]);

            $second = app(DelfiFeedSynchronizer::class)->syncFile($secondFeed);
            $this->assertSame(2, $second['current']);
            $this->assertSame(1, $second['retired']);

            $updated = DelfiImportProduct::query()->where('external_id', 'BOOK-1')->firstOrFail();
            $this->assertSame('Prva - novo izdanje', $updated->name);
            $this->assertSame(987, (int) $updated->product_id);
            $this->assertSame(['Fantastika'], $updated->source_genres);
            $this->assertSame('matched', $updated->check_status);
            $this->assertSame('Obogaćeni API opis', $updated->description);
            $this->assertSame('Obogaćeni nakladnik', $updated->source_publisher);
            $this->assertSame('https://delfi.rs/_img/enriched-cover.png', $updated->image_url);
            $this->assertSame(
                ['https://delfi.rs/_img/enriched-extra.png'],
                $updated->additional_image_urls
            );
            $this->assertSame('Obogaćeni autor', $updated->author);
            $this->assertTrue($updated->is_current);
            $this->assertFalse(
                DelfiImportProduct::query()->where('external_id', 'FOREIGN-1')->firstOrFail()->is_current
            );
            $this->assertSame(
                ['BOOK-1', 'BOOK-2'],
                DelfiImportProduct::query()
                    ->where('is_current', true)
                    ->orderBy('feed_position')
                    ->pluck('external_id')
                    ->all()
            );
        } finally {
            @unlink($firstFeed);
            @unlink($secondFeed);
        }
    }

    public function test_failed_parse_does_not_finalize_current_flags(): void
    {
        $valid = $this->temporaryFeed([
            $this->item('BOOK-1', 101, 'Prva', 'Knjiga', 1000),
            $this->item('BOOK-2', 102, 'Druga', 'Knjiga', 1100),
        ]);

        try {
            app(DelfiFeedSynchronizer::class)->syncFile($valid);
            $before = DelfiImportProduct::query()->where('external_id', 'BOOK-1')->firstOrFail();

            $partialItems = [];
            foreach (range(1, 10) as $position) {
                $externalId = $position === 1 ? 'BOOK-1' : 'PARTIAL-' . $position;
                $remoteId = $position === 1 ? 101 : 200 + $position;
                $partialItems[] = $this->normalizedItem(
                    $externalId,
                    $remoteId,
                    $position === 1 ? 'Ne smije ostati' : 'Parcijalna ' . $position,
                    $position === 1 ? 9999 : 1200 + $position,
                    hash('sha256', 'partial-' . $position)
                );
            }

            $failingFeed = new class($partialItems, app(DelfiFeedNormalizer::class)) extends DelfiFeedService
            {
                private array $items;

                public function __construct(array $items, DelfiFeedNormalizer $normalizer)
                {
                    parent::__construct($normalizer);
                    $this->items = $items;
                }

                public function iterate(string $path): \Generator
                {
                    foreach ($this->items as $item) {
                        yield $item;
                    }

                    throw new \RuntimeException('Namjerno prekinut parcijalni feed.');
                }
            };
            config(['delfi_import.sync_batch_size' => 10]);

            try {
                (new DelfiFeedSynchronizer($failingFeed))->syncFile('nije-bitno.xml');
                $this->fail('Neispravan XML mora prekinuti sinkronizaciju.');
            } catch (RuntimeException $exception) {
                $this->assertNotSame('', $exception->getMessage());
            }

            $this->assertSame(2, DelfiImportProduct::query()->where('is_current', true)->count());
            $after = DelfiImportProduct::query()->where('external_id', 'BOOK-1')->firstOrFail();
            $this->assertSame($before->name, $after->name);
            $this->assertSame($before->price_rsd, $after->price_rsd);
            $this->assertSame($before->source_hash, $after->source_hash);
            $this->assertSame($before->feed_token, $after->feed_token);
            $this->assertTrue($after->is_current);
            $this->assertDatabaseMissing('delfi_import_products', ['external_id' => 'PARTIAL-2']);
            $this->assertSame(0, DB::table('delfi_import_feed_rows')->count());
        } finally {
            @unlink($valid);
        }
    }

    public function test_sanity_guard_does_not_retire_current_books_for_a_truncated_feed(): void
    {
        $first = $this->temporaryFeed([
            $this->item('BOOK-1', 101, 'Prva', 'Knjiga', 1000),
            $this->item('BOOK-2', 102, 'Druga', 'Knjiga', 1100),
        ]);
        $truncated = $this->temporaryFeed([
            $this->item('BOOK-3', 103, 'Treća', 'Knjiga', 1200),
        ]);

        try {
            app(DelfiFeedSynchronizer::class)->syncFile($first);
            config(['delfi_import.minimum_current_ratio' => 0.8]);

            $this->expectException(RuntimeException::class);
            try {
                app(DelfiFeedSynchronizer::class)->syncFile($truncated, true);
            } finally {
                $this->assertSame(2, DelfiImportProduct::query()->where('is_current', true)->count());
                $this->assertDatabaseMissing('delfi_import_products', ['external_id' => 'BOOK-3']);
                $this->assertSame(0, DB::table('delfi_import_feed_rows')->count());
            }
        } finally {
            @unlink($first);
            @unlink($truncated);
        }
    }

    private function temporaryFeed(array $items): string
    {
        $path = tempnam(sys_get_temp_dir(), 'delfi-feed-test-');
        file_put_contents(
            $path,
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<rss xmlns:g="http://base.google.com/ns/1.0" version="2.0"><channel>'
            . implode('', $items)
            . '</channel></rss>'
        );

        return $path;
    }

    private function normalizedItem(
        string $externalId,
        int $remoteId,
        string $name,
        float $price,
        string $sourceHash
    ): array {
        return [
            'external_id' => $externalId,
            'remote_product_id' => $remoteId,
            'name' => $name,
            'description' => 'Parcijalni opis',
            'source_category' => 'Knjiga',
            'source_publisher' => 'Parcijalni nakladnik',
            'source_url' => 'https://delfi.rs/knjige/' . $remoteId . '-partial.html',
            'image_url' => 'https://delfi.rs/_img/partial.png',
            'additional_image_urls' => [],
            'price_rsd' => $price,
            'sale_price_rsd' => null,
            'availability' => 'in stock',
            'author' => 'Parcijalni autor',
            'source_hash' => $sourceHash,
        ];
    }

    private function item(
        string $id,
        int $sourceId,
        string $title,
        string $category,
        float $price
    ): string {
        return '<item>'
            . '<g:id>' . $id . '</g:id>'
            . '<title><![CDATA[' . $title . ']]></title>'
            . '<description><![CDATA[Opis]]></description>'
            . '<g:availability>in stock</g:availability>'
            . '<g:price>' . $price . ' RSD</g:price>'
            . '<link>https://delfi.rs/knjige/' . $sourceId . '-book.html</link>'
            . '<g:image_link>https://delfi.rs/_img/book.png</g:image_link>'
            . '<brand><![CDATA[Nakladnik]]></brand>'
            . '<category><![CDATA[' . $category . ']]></category>'
            . '<authors><![CDATA[Autor]]></authors>'
            . '</item>';
    }
}
