<?php

namespace Tests\Unit;

use App\Services\Delfi\DelfiFeedService;
use App\Services\Delfi\DelfiFeedNormalizer;
use RuntimeException;
use Tests\TestCase;

class DelfiFeedServiceTest extends TestCase
{
    public function test_it_streams_and_normalizes_delfi_books_in_feed_order(): void
    {
        $path = $this->temporaryFeed([
            $this->item('6a8824', 251917, 'Anathema', 'Strana knjiga', '1999 RSD', 'Keri Lake'),
            $this->item('6a8811', 251908, 'Neveštice', 'Knjiga', '1.199 RSD', 'Geri Penton'),
        ]);

        try {
            $items = iterator_to_array(app(DelfiFeedService::class)->iterate($path));

            $this->assertCount(2, $items);
            $this->assertSame('6a8824', $items[0]['external_id']);
            $this->assertSame(251917, $items[0]['remote_product_id']);
            $this->assertSame('Strana knjiga', $items[0]['source_category']);
            $this->assertSame('Keri Lake', $items[0]['author']);
            $this->assertSame('The Book Service Limited', $items[0]['source_publisher']);
            $this->assertSame(1999.0, $items[0]['price_rsd']);
            $this->assertSame("Only the banished know what lies beyond the woods…\n\nSecond paragraph.", $items[0]['description']);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $items[0]['source_hash']);
            $this->assertSame(1199.0, $items[1]['price_rsd']);
        } finally {
            @unlink($path);
        }
    }

    public function test_it_rejects_a_doctype(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'delfi-feed-test-');
        file_put_contents($path, '<?xml version="1.0"?><!DOCTYPE rss><rss><channel></channel></rss>');

        try {
            $this->expectException(RuntimeException::class);
            iterator_to_array(app(DelfiFeedService::class)->iterate($path));
        } finally {
            @unlink($path);
        }
    }

    public function test_it_keeps_only_safe_https_delfi_image_urls(): void
    {
        $xml = '<item xmlns:g="http://base.google.com/ns/1.0">'
            . '<g:id>SAFE-1</g:id><title>Knjiga</title><description>Opis</description>'
            . '<g:availability>in stock</g:availability><g:price>999 RSD</g:price>'
            . '<link>https://delfi.rs/knjige/251900-knjiga.html</link>'
            . '<g:image_link>https://delfi.rs/_img/Strana knjiga/naslovnica.png</g:image_link>'
            . '<g:additional_image_link>javascript://delfi.rs/%0Aalert(1)</g:additional_image_link>'
            . '<g:additional_image_link>https://evil.example/naslovnica.png</g:additional_image_link>'
            . '<category>Knjiga</category><authors>Autor</authors>'
            . '</item>';

        $item = app(DelfiFeedNormalizer::class)->normalizeItemXml($xml);

        $this->assertSame(
            'https://delfi.rs/_img/Strana%20knjiga/naslovnica.png',
            $item['image_url']
        );
        $this->assertSame([], $item['additional_image_urls']);
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

    private function item(
        string $id,
        int $sourceId,
        string $title,
        string $category,
        string $price,
        string $author
    ): string {
        return '<item>'
            . '<g:id>' . $id . '</g:id>'
            . '<title><![CDATA[' . $title . ']]></title>'
            . '<description><![CDATA[Only the banished know what lies beyond the woodshellip;'
            . "\n\n"
            . 'Second paragraph.]]></description>'
            . '<g:availability>in stock</g:availability>'
            . '<g:price>' . $price . '</g:price>'
            . '<link>https://delfi.rs/strane_knjige/' . $sourceId . '-book.html</link>'
            . '<g:image_link>https://delfi.rs/_img/book.png</g:image_link>'
            . '<brand><![CDATA[The Book Service Limited]]></brand>'
            . '<category><![CDATA[' . $category . ']]></category>'
            . '<authors><![CDATA[' . $author . ']]></authors>'
            . '</item>';
    }
}
