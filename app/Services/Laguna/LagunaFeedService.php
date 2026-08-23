<?php

namespace App\Services\Laguna;

use Generator;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use SimpleXMLElement;
use XMLReader;

class LagunaFeedService
{
    public function feedUrl(): string
    {
        return (string) config('laguna_import.feed_url');
    }

    public function cachePath(): string
    {
        return (string) config('laguna_import.cache_path');
    }

    public function refreshCache(): array
    {
        $cachePath = $this->cachePath();
        $directory = dirname($cachePath);

        if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new RuntimeException('Nije moguće napraviti direktorij za Laguna feed.');
        }

        $temporaryPath = tempnam($directory, '.laguna-feed-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Nije moguće napraviti privremenu datoteku za Laguna feed.');
        }

        try {
            $response = Http::withOptions([
                    'sink' => $temporaryPath,
                    'connect_timeout' => 10,
                ])
                ->timeout(180)
                ->withHeaders(['User-Agent' => 'Zuzi-Laguna-Importer/1.0'])
                ->get($this->feedUrl());

            if (! $response->successful()) {
                throw new RuntimeException('Laguna feed vratio je HTTP ' . $response->status() . '.');
            }

            $metadata = $this->inspect($temporaryPath);
            if ($metadata['count'] < 1) {
                throw new RuntimeException('Laguna feed ne sadrži artikle.');
            }

            if (! rename($temporaryPath, $cachePath)) {
                throw new RuntimeException('Nije moguće spremiti novu verziju Laguna feeda.');
            }

            return $metadata + ['path' => $cachePath];
        } catch (\Throwable $exception) {
            if (is_file($temporaryPath)) {
                @unlink($temporaryPath);
            }

            throw $exception;
        }
    }

    public function metadata(): array
    {
        $path = $this->cachePath();

        if (! is_file($path)) {
            return ['exists' => false, 'count' => 0, 'bytes' => 0, 'modified_at' => null];
        }

        $this->assertReadableFile($path);

        return [
            'exists' => true,
            'count' => 0,
            'bytes' => filesize($path) ?: 0,
            'modified_at' => date('d.m.Y. H:i', filemtime($path) ?: time()),
        ];
    }

    public function inspect(string $path): array
    {
        $this->assertReadableFile($path);
        $bytes = filesize($path);
        $maximum = (int) config('laguna_import.max_feed_bytes', 52428800);

        if ($bytes === false || $bytes < 1 || $bytes > $maximum) {
            throw new RuntimeException('Laguna feed ima neispravnu veličinu.');
        }

        $count = 0;
        foreach ($this->iterate($path) as $unused) {
            $count++;
        }

        return [
            'count' => $count,
            'bytes' => $bytes,
            'hash' => hash_file('sha256', $path),
            'modified_at' => date('d.m.Y. H:i', filemtime($path) ?: time()),
        ];
    }

    public function iterate(string $path): Generator
    {
        $this->assertReadableFile($path);

        $reader = new XMLReader();
        if (! $reader->open($path, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new RuntimeException('Laguna RSS nije moguće otvoriti.');
        }

        $previousErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();

        try {
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::DOC_TYPE) {
                    throw new RuntimeException('DOCTYPE nije dopušten u Laguna feedu.');
                }

                if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'item') {
                    continue;
                }

                $xml = $reader->readOuterXml();
                if ($xml === '') {
                    throw new RuntimeException('Neispravan artikl u Laguna feedu.');
                }

                yield $this->normalizeItemXml($xml);
            }

            $errors = libxml_get_errors();
            if ($errors !== []) {
                throw new RuntimeException('Laguna RSS sadrži neispravan XML.');
            }
        } finally {
            $reader->close();
            libxml_clear_errors();
            libxml_use_internal_errors($previousErrors);
        }
    }

    public function normalizeItemXml(string $xml): array
    {
        $previousErrors = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $item = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        if (! $item || $errors !== []) {
            throw new RuntimeException('Nije moguće pročitati artikl iz Laguna RSS-a.');
        }

        $google = $item->children('http://base.google.com/ns/1.0');
        $media = $item->children('http://search.yahoo.com/mrss/');
        $externalId = trim((string) $google->id);
        $name = $this->plainText((string) ($google->title ?: $item->title));
        $sourceUrl = trim((string) ($google->link ?: $item->link));
        $description = $this->plainText((string) ($google->description ?: $item->description), true);
        $price = $this->parsePrice((string) $google->price);

        $additionalImages = [];
        foreach ($google->additional_image_link as $image) {
            $url = trim((string) $image);
            if ($url !== '') {
                $additionalImages[] = $url;
            }
        }

        $imageUrl = trim((string) $google->image_link);
        if ($imageUrl === '' && isset($media->content['url'])) {
            $imageUrl = trim((string) $media->content['url']);
        }

        $normalized = [
            'external_id' => $externalId,
            'name' => $name,
            'description' => $description,
            'product_type' => $this->plainText((string) $google->product_type),
            'source_category' => $this->plainText((string) $item->category),
            'source_url' => $sourceUrl,
            'image_url' => $imageUrl ?: null,
            'additional_image_urls' => array_values(array_unique($additionalImages)),
            'price_rsd' => $price,
            'sale_price_rsd' => $this->parsePrice((string) $google->sale_price) ?: null,
            'availability' => strtolower(trim((string) $google->availability)),
        ];

        $normalized['source_hash'] = hash('sha256', json_encode(
            $normalized,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        ));

        return $normalized;
    }

    private function parsePrice(string $value): float
    {
        if (! preg_match('/-?[0-9]+(?:[.,][0-9]+)?/', $value, $matches)) {
            return 0.0;
        }

        return max(0, (float) str_replace(',', '.', $matches[0]));
    }

    private function plainText(string $value, bool $description = false): string
    {
        $value = html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? '';
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? '';

        if ($description) {
            $value = preg_replace('/(?<=[.!?])(?=\p{Lu})/u', ' ', $value) ?? $value;
        }

        return $value;
    }

    private function assertReadableFile(string $path): void
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Laguna feed nije pronađen ili nije čitljiv.');
        }
    }
}
