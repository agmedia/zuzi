<?php

namespace App\Helpers;

use App\Models\Back\Catalog\Product\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;
use XMLWriter;

/**
 * Generira stavke za Njuškalo feed uz minimalan memory footprint.
 * Koristi lazyById() pa se proizvodi učitavaju i obrađuju streamano.
 */
class Njuskalo
{
    private const DEFAULT_CACHE_TTL = 21600;
    private const EXPORT_PATH = 'app/feeds/njuskalo.xml';

    /**
     * Vraća Generator sa transformiranim stavkama.
     * Primjer upotrebe u kontroleru:
     *   return response()->view('front.layouts.partials.njuskalo', [
     *       'items' => (new Njuskalo())->items()
     *   ])->header('Content-Type', 'text/xml; charset=UTF-8');
     *
     * @param int $chunkSize Primarni ključ batch veličina za lazyById
     * @return \Generator<array<string,mixed>>
     */
    public function items(int $chunkSize = 500): \Generator
    {
        foreach ($this->baseQuery()->lazyById($chunkSize) as $product) {
            yield $this->transform($product);
        }
    }

    /**
     * Osigurava da na disku postoji svjez XML export i vraca njegovu putanju.
     */
    public function ensureExport(?int $ttl = null): string
    {
        $ttl ??= (int) config('settings.njuskalo.cache_ttl', self::DEFAULT_CACHE_TTL);
        $path = static::exportPath();

        if ($this->isFresh($path, $ttl)) {
            return $path;
        }

        try {
            $this->writeExport($path);
        } catch (\Throwable $e) {
            report($e);

            if (! File::exists($path)) {
                throw $e;
            }
        }

        return $path;
    }

    public static function exportPath(): string
    {
        return storage_path(self::EXPORT_PATH);
    }

    public static function clearExport(): void
    {
        $path = static::exportPath();

        if (File::exists($path)) {
            File::delete($path);
        }
    }

    /**
     * Osnovni upit – ovdje držimo sve filtre i eager load.
     */
    private function baseQuery(): Builder
    {
        return Product::query()
            ->where('status', 1)
            ->whereNotIn('sku', config('settings.njuskalo.forbidden'))
            ->where('price', '!=', 0)
            ->where('quantity', '!=', 0)
            ->select([
                'id', 'name', 'description', 'quantity', 'status', 'price',
                'image', 'pages', 'dimensions', 'origin', 'url', 'letter',
                'condition', 'binding', 'year',
            ])
            ->with(['categories:id,slug']);
    }

    /**
     * Transformira Eloquent model u polje prikladno za XML Blade view.
     *
     * @param \App\Models\Back\Catalog\Product\Product $product
     * @return array<string,mixed>
     */
    public function transform(Product $product): array
    {
        $categorySlug = optional($product->categories->first())->slug ?? 'ostala-literatura';

        return [
            'id'          => $product->id,
            'name'        => $product->name,
            'description' => $this->getDescription($product), // spremno za CDATA u Bladeu
            'group'       => config('settings.njuskalo.sync.' . $categorySlug),
            'price'       => $product->price,
            'slug'        => $product->url,
            'image'       => asset($product->image),
        ];
    }

    public function getItems(): \Generator
    {
        // alias na novu metodu
        return $this->items();
    }

    /**
     * Sastavlja opis; čisti kontrolne znakove i po želji limitira duljinu.
     */
    private function getDescription(Product $product): string
    {
        $parts = [];

        if (!empty($product->description)) {
            $desc = preg_replace('/[[:cntrl:]]/', '', (string) $product->description);
            // Po potrebi odkomentiraj limit:
            // $desc = Str::limit($desc, 2000, '…');
            $parts[] = $desc . '<br><br>';
        }

        $kv = [
            'Stranica'  => $product->pages,
            'Dimenzije' => $product->dimensions,
            'Jezik'     => $product->origin,
            'Pismo'     => $product->letter,
            'Stanje'    => $product->condition,
            'Uvez'      => $product->binding,
            'Godina'    => $product->year,
        ];

        foreach ($kv as $label => $value) {
            if (!empty($value)) {
                $parts[] = "{$label}: {$value}<br>";
            }
        }

        return implode('', $parts);
    }

    private function isFresh(string $path, int $ttl): bool
    {
        return File::exists($path) && (time() - File::lastModified($path)) < max(0, $ttl);
    }

    private function writeExport(string $path): void
    {
        $directory = dirname($path);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $tempPath = tempnam($directory, 'njuskalo-');

        if ($tempPath === false) {
            throw new \RuntimeException('Unable to create Njuskalo XML temp file.');
        }

        try {
            $xml = new XMLWriter();

            if (! $xml->openUri($tempPath)) {
                throw new \RuntimeException('Unable to open Njuskalo XML temp file.');
            }

            $xml->startDocument('1.0', 'UTF-8');
            $xml->startElement('ad_list');

            foreach ($this->items() as $item) {
                $this->writeItem($xml, $item);
            }

            $xml->endElement();
            $xml->endDocument();
            $xml->flush();

            if (! @rename($tempPath, $path)) {
                File::move($tempPath, $path);
            }

            $tempPath = null;
        } finally {
            if ($tempPath && File::exists($tempPath)) {
                File::delete($tempPath);
            }
        }
    }

    /**
     * @param array<string, mixed> $item
     */
    private function writeItem(XMLWriter $xml, array $item): void
    {
        $xml->startElement('ad_item');
        $xml->writeAttribute('class', 'ad_simple');

        $xml->writeElement('user_id', (string) config('settings.njuskalo.user_id'));
        $xml->writeElement('original_id', (string) $item['id']);
        $xml->writeElement('category_id', (string) $item['group']);
        $xml->writeElement('title', $this->sanitizeXmlText($item['name'] ?? ''));
        $xml->writeElement('currency_id', '2');
        $xml->writeElement('price', (string) $item['price']);
        $xml->writeElement('description', $this->sanitizeXmlText($item['description'] ?? ''));
        $xml->writeElement('conditionId', '20');

        $xml->startElement('phone_list');
        $xml->startElement('phone');
        $xml->writeElement('calling_code', '385');
        $xml->writeElement('area_code', '91');
        $xml->writeElement('phone_number', '7627441');
        $xml->endElement();
        $xml->endElement();

        $xml->writeElement('youtubeUrl', '');
        $xml->writeElement('location_id', '2803');
        $xml->writeElement('gmap_lng', '45.802118274402126');
        $xml->writeElement('gmap_lat', '15.890055457671485');
        $xml->writeElement('isOnlinePaymentEnabled', '1');

        $xml->startElement('availableParcelShops');
        $xml->writeElement('item', 'boxNow');
        $xml->endElement();

        $xml->writeElement('deliveryPackageWeight', '1');

        $xml->startElement('biddingOptions');
        $xml->writeElement('item', 'buyNow');
        $xml->writeElement('item', 'bidding');
        $xml->endElement();

        $xml->writeElement('videoCallOption', '0');
        $xml->writeElement('webshopLink', url((string) $item['slug']));

        $xml->startElement('image_list');
        $xml->writeElement('image', (string) $item['image']);
        $xml->endElement();

        $xml->endElement();
    }

    private function sanitizeXmlText(mixed $value): string
    {
        $value = (string) $value;

        return preg_replace('/[^\x09\x0A\x0D\x20-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';
    }
}
