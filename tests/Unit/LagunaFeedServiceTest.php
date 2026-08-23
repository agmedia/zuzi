<?php

namespace Tests\Unit;

use App\Services\Laguna\LagunaFeedService;
use Tests\TestCase;

class LagunaFeedServiceTest extends TestCase
{
    public function test_it_streams_and_normalizes_google_merchant_rss_items(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'laguna-feed-test-');
        file_put_contents($path, $this->feed([
            $this->item('A335684', 'Mir pod pritiskom', '1299.00 RSD', 'in stock'),
        ]));

        try {
            $items = iterator_to_array(app(LagunaFeedService::class)->iterate($path));

            $this->assertCount(1, $items);
            $this->assertSame('A335684', $items[0]['external_id']);
            $this->assertSame('Mir pod pritiskom', $items[0]['name']);
            $this->assertSame(1299.0, $items[0]['price_rsd']);
            $this->assertSame('in stock', $items[0]['availability']);
            $this->assertSame('Knjige', $items[0]['product_type']);
            $this->assertSame('https://laguna.rs/proizvodi/knjige/mir-pod-pritiskom/', $items[0]['source_url']);
            $this->assertSame(['https://laguna.oozmi-cdn.com/extra.webp'], $items[0]['additional_image_urls']);
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $items[0]['source_hash']);
        } finally {
            @unlink($path);
        }
    }

    private function feed(array $items): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>'
            . '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0" xmlns:media="http://search.yahoo.com/mrss/">'
            . '<channel><title>Proizvodi</title>' . implode('', $items) . '</channel></rss>';
    }

    private function item(string $id, string $title, string $price, string $availability): string
    {
        $slug = str_replace(' ', '-', strtolower($title));

        return '<item>'
            . '<title>' . $title . '</title>'
            . '<link>https://laguna.rs/proizvodi/knjige/' . $slug . '/</link>'
            . '<description><![CDATA[Prva rečenica.Druga rečenica.]]></description>'
            . '<category>Knjige</category>'
            . '<g:id>' . $id . '</g:id>'
            . '<g:title>' . $title . '</g:title>'
            . '<g:link>https://laguna.rs/proizvodi/knjige/' . $slug . '/</g:link>'
            . '<g:image_link>https://laguna.oozmi-cdn.com/main.webp</g:image_link>'
            . '<g:additional_image_link>https://laguna.oozmi-cdn.com/extra.webp</g:additional_image_link>'
            . '<g:availability>' . $availability . '</g:availability>'
            . '<g:price>' . $price . '</g:price>'
            . '<g:product_type>Knjige</g:product_type>'
            . '</item>';
    }
}
