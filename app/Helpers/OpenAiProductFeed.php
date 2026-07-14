<?php

namespace App\Helpers;

use App\Models\Front\Catalog\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OpenAiProductFeed
{
    private const DEFAULT_CACHE_TTL = 3600;
    private const DEFAULT_MAX_ITEMS = 500000;
    private const EXPORT_PATH = 'app/feeds/openai-products.jsonl';

    /**
     * @return \Generator<array<string, mixed>>
     */
    public function items(int $chunkSize = 500, ?int $limit = null): \Generator
    {
        $processed = 0;
        $limit ??= $this->maxItems();

        foreach ($this->baseQuery()->lazyByIdDesc($chunkSize) as $product) {
            if ($processed >= $limit) {
                break;
            }

            $item = $this->transform($product);

            if (! $item) {
                continue;
            }

            $processed++;
            yield $item;
        }
    }

    public function ensureExport(?int $ttl = null): string
    {
        $ttl ??= (int) config('settings.openai_product_feed.cache_ttl', self::DEFAULT_CACHE_TTL);
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
     * @return array<string, mixed>|null
     */
    public function transform(Product $product): ?array
    {
        $url = $this->absoluteUrl((string) $product->url);
        $mainImage = $this->mainImageUrl($product);

        if (! $url || ! $mainImage) {
            return null;
        }

        $regularPrice = (float) $product->price;
        $salePrice = (float) $product->special();
        $hasSalePrice = $salePrice > 0 && $salePrice < $regularPrice;

        $row = [
            'is_eligible_search' => true,
            'is_eligible_checkout' => false,
            'item_id' => $this->itemId($product),
            'gtin' => $this->gtin($product),
            'mpn' => $this->mpn($product),
            'title' => $this->title($product),
            'description' => $this->description($product),
            'url' => $url,
            'brand' => $this->brand($product),
            'condition' => $this->condition($product->condition ?? null),
            'product_category' => $this->categoryPath($product),
            'image_url' => $mainImage,
            'additional_image_urls' => $this->additionalImageUrls($product, $mainImage),
            'price' => $this->money($regularPrice),
            'sale_price' => $hasSalePrice ? $this->money($salePrice) : null,
            'sale_price_start_date' => $hasSalePrice ? $this->dateValue($product->special_from ?? null) : null,
            'sale_price_end_date' => $hasSalePrice ? $this->dateValue($product->special_to ?? null) : null,
            'availability' => $this->availability($product),
        ];

        return $this->filterEmptyValues($row);
    }

    private function baseQuery(): Builder
    {
        return Product::query()
            ->active()
            ->hasStock()
            ->whereNotNull('url')
            ->where('url', '!=', '')
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->select($this->productColumns([
                'id',
                'name',
                'description',
                'quantity',
                'status',
                'price',
                'special',
                'special_from',
                'special_to',
                'action_id',
                'image',
                'pages',
                'dimensions',
                'origin',
                'url',
                'letter',
                'condition',
                'binding',
                'year',
                'language',
                'isbn',
                'sku',
                'shipping_time',
                'delivery_24h',
                'author_id',
                'publisher_id',
            ]))
            ->with([
                'action:id,coupon,status',
                'author:id,title',
                'publisher:id,title',
                'images:id,product_id,image',
                'categories',
            ]);
    }

    /**
     * @param array<int, string> $columns
     * @return array<int, string>
     */
    private function productColumns(array $columns): array
    {
        $available = $this->productColumnListing();

        return array_values(array_filter(
            $columns,
            static fn (string $column): bool => in_array($column, $available, true)
        ));
    }

    /**
     * @return array<int, string>
     */
    private function productColumnListing(): array
    {
        static $columns = null;

        if ($columns === null) {
            $columns = Schema::getColumnListing((new Product())->getTable());
        }

        return $columns;
    }

    private function writeExport(string $path): void
    {
        $directory = dirname($path);

        if (! File::isDirectory($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $tempPath = tempnam($directory, 'openai-products-');

        if ($tempPath === false) {
            throw new \RuntimeException('Unable to create OpenAI product feed temp file.');
        }

        $handle = null;

        try {
            $handle = fopen($tempPath, 'wb');

            if (! $handle) {
                throw new \RuntimeException('Unable to open OpenAI product feed temp file.');
            }

            foreach ($this->items() as $item) {
                $line = json_encode($item, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";

                if (fwrite($handle, $line) === false) {
                    throw new \RuntimeException('Unable to write OpenAI product feed line.');
                }
            }

            fclose($handle);
            $handle = null;

            if (! @rename($tempPath, $path)) {
                File::move($tempPath, $path);
            }

            $tempPath = null;
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }

            if ($tempPath && File::exists($tempPath)) {
                File::delete($tempPath);
            }
        }
    }

    private function isFresh(string $path, int $ttl): bool
    {
        if (! File::exists($path)) {
            return false;
        }

        $lastModified = File::lastModified($path);

        return (time() - $lastModified) < max(0, $ttl)
            && $lastModified >= $this->sourceModifiedAt();
    }

    private function sourceModifiedAt(): int
    {
        $paths = [
            __FILE__,
            config_path('settings.php'),
        ];

        return max(array_map(static fn (string $path): int => is_file($path) ? (int) filemtime($path) : 0, $paths));
    }

    private function maxItems(): int
    {
        return max(1, (int) config('settings.openai_product_feed.max_items', self::DEFAULT_MAX_ITEMS));
    }

    private function itemId(Product $product): string
    {
        return (string) $product->id;
    }

    private function title(Product $product): string
    {
        $title = $this->cleanText($product->name, 150);

        return $title !== '' ? $title : 'Knjiga #' . $product->id;
    }

    private function brand(Product $product): string
    {
        $publisher = $this->cleanText(optional($product->publisher)->title, 70);

        return $publisher !== '' ? $publisher : 'ZUZI Shop';
    }

    private function description(Product $product): string
    {
        $parts = [];
        $base = $this->cleanText($product->description, 3500);

        if ($base !== '') {
            $parts[] = $base;
        } else {
            $parts[] = 'Knjiga "' . $this->title($product) . '" dostupna u ZUZI Shop web knjižari i antikvarijatu.';
        }

        $details = [
            'Autor' => optional($product->author)->title,
            'Izdavač' => optional($product->publisher)->title,
            'ISBN' => $product->isbn,
            'Godina' => $product->year,
            'Jezik' => $product->language,
            'Uvez' => $product->binding,
            'Stanje' => $product->condition,
            'Broj stranica' => $product->pages,
            'Dimenzije' => $product->dimensions,
            'Pismo' => $product->letter,
            'Podrijetlo' => $product->origin,
            'Rok isporuke' => $product->shipping_time,
        ];

        if ((int) ($product->delivery_24h ?? 0) === 1) {
            $details['Dostava'] = 'dostupno za brzu isporuku';
        }

        $detailSentences = [];

        foreach ($details as $label => $value) {
            $value = $this->cleanText($value, 200);

            if ($value !== '') {
                $detailSentences[] = $label . ': ' . $value;
            }
        }

        if ($detailSentences) {
            $parts[] = implode('. ', $detailSentences) . '.';
        }

        return $this->cleanText(implode(' ', $parts), 5000);
    }

    private function gtin(Product $product): ?string
    {
        $isbn = preg_replace('/\D+/', '', (string) ($product->isbn ?? '')) ?? '';
        $length = strlen($isbn);

        return $length >= 8 && $length <= 14 ? $isbn : null;
    }

    private function mpn(Product $product): ?string
    {
        $sku = preg_replace('/[^A-Za-z0-9]/', '', (string) ($product->sku ?? '')) ?? '';

        if ($sku === '') {
            return null;
        }

        return Str::limit($sku, 70, '');
    }

    private function condition(?string $condition): ?string
    {
        $condition = $this->cleanText($condition, 60);

        if ($condition === '') {
            return null;
        }

        $normalized = mb_strtolower($condition, 'UTF-8');

        if (in_array($normalized, ['novo', 'new', 'nekoristeno', 'nekorišteno'], true)) {
            return 'new';
        }

        return 'secondhand';
    }

    private function categoryPath(Product $product): string
    {
        $categories = $product->relationLoaded('categories')
            ? $product->getRelation('categories')->filter()
            : collect();

        if ($categories->isEmpty()) {
            return 'Knjige';
        }

        $root = $categories->first(fn ($category) => (int) ($category->parent_id ?? 0) === 0) ?: $categories->first();
        $child = $categories->first(fn ($category) => (int) ($category->parent_id ?? 0) !== 0 && (int) $category->id !== (int) $root->id);
        $parts = ['Knjige'];

        foreach ([optional($root)->group, optional($root)->title, optional($child)->title] as $part) {
            $part = $this->cleanText($part, 80);

            if ($part !== '' && ! $this->containsCaseInsensitive($parts, $part)) {
                $parts[] = $part;
            }
        }

        return implode(' > ', $parts);
    }

    private function mainImageUrl(Product $product): ?string
    {
        $attributes = $product->getAttributes();
        $image = $attributes['image'] ?? $product->getRawOriginal('image');

        if (! $image) {
            $image = $product->image;
        }

        return $this->imageUrl($image);
    }

    private function additionalImageUrls(Product $product, string $mainImage): ?string
    {
        $images = $product->relationLoaded('images')
            ? $product->getRelation('images')
            : collect();

        $urls = $images
            ->map(function ($image) {
                $attributes = $image->getAttributes();
                $path = $attributes['image'] ?? $image->getRawOriginal('image');

                if (! $path) {
                    $path = $image->image;
                }

                return $this->imageUrl($path);
            })
            ->filter()
            ->reject(fn (string $url) => $url === $mainImage)
            ->unique()
            ->take(8)
            ->values();

        return $urls->isNotEmpty() ? $urls->implode(',') : null;
    }

    private function imageUrl(?string $image): ?string
    {
        $image = trim((string) $image);

        if ($image === '') {
            return null;
        }

        if (str_starts_with($image, '//')) {
            return 'https:' . $image;
        }

        if (filter_var($image, FILTER_VALIDATE_URL)) {
            return $image;
        }

        $base = trim((string) config('settings.images_domain'));

        if ($base !== '') {
            return rtrim($base, '/') . '/' . ltrim($image, '/');
        }

        return url($image);
    }

    private function absoluteUrl(string $path): ?string
    {
        $path = trim($path);

        if ($path === '') {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        return url($path);
    }

    private function availability(Product $product): string
    {
        return (int) $product->quantity > 0 ? 'in_stock' : 'out_of_stock';
    }

    private function money(float $amount): string
    {
        return number_format(max(0, $amount), 2, '.', '') . ' EUR';
    }

    private function dateValue($value): ?string
    {
        $value = trim((string) $value);

        if ($value === '' || $value === '0000-00-00 00:00:00') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function cleanText($value, int $limit): string
    {
        $text = html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[[:cntrl:]]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        return Str::limit($text, $limit, '...');
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function filterEmptyValues(array $row): array
    {
        return array_filter($row, static function ($value): bool {
            return $value !== null && $value !== '' && $value !== [];
        });
    }

    /**
     * @param array<int, string> $values
     */
    private function containsCaseInsensitive(array $values, string $needle): bool
    {
        $needle = mb_strtolower($needle, 'UTF-8');

        foreach ($values as $value) {
            if (mb_strtolower($value, 'UTF-8') === $needle) {
                return true;
            }
        }

        return false;
    }
}
