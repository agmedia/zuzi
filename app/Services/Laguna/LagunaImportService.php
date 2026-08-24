<?php

namespace App\Services\Laguna;

use App\Helpers\Helper;
use App\Models\Back\Catalog\Author;
use App\Models\Back\Catalog\Category;
use App\Models\Back\Catalog\LagunaImportProduct;
use App\Models\Back\Catalog\Product\Product;
use App\Models\Back\Catalog\Product\ProductImage;
use App\Models\Back\Catalog\Publisher;
use App\Models\Back\Marketing\Action;
use App\Services\ProductIdentifierAllocator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use RuntimeException;

class LagunaImportService
{
    private LagunaProductPageParser $pageParser;
    private LagunaTranslationService $translator;
    private LagunaImportSettings $settings;
    private LagunaPriceCalculator $priceCalculator;
    private ProductIdentifierAllocator $identifierAllocator;

    public function __construct(
        LagunaProductPageParser $pageParser,
        LagunaTranslationService $translator,
        LagunaImportSettings $settings,
        LagunaPriceCalculator $priceCalculator,
        ProductIdentifierAllocator $identifierAllocator
    ) {
        $this->pageParser = $pageParser;
        $this->translator = $translator;
        $this->settings = $settings;
        $this->priceCalculator = $priceCalculator;
        $this->identifierAllocator = $identifierAllocator;
    }

    public function inspect(LagunaImportProduct $source, bool $force = false): LagunaImportProduct
    {
        if (! $source->is_current) {
            throw new RuntimeException('Artikl više nije prisutan u aktualnom Laguna feedu.');
        }

        if (! $force && $source->checked_source_hash === $source->source_hash
            && in_array($source->check_status, ['new', 'matched', 'conflict'], true)) {
            return $source->fresh(['product']);
        }

        try {
            $this->assertProductUrl($source->source_url);
            $response = Http::withOptions(['connect_timeout' => 5])
                ->timeout(30)
                ->withHeaders([
                    'Accept' => 'text/html,application/xhtml+xml',
                    'User-Agent' => 'Zuzi-Laguna-Importer/1.0',
                ])
                ->get($source->source_url);

            if (! $response->successful()) {
                throw new RuntimeException('Laguna stranica vratila je HTTP ' . $response->status() . '.');
            }

            $details = $this->pageParser->parse($response->body());
            $matches = $this->findExistingProducts(
                $details['isbn'] ?? null,
                $source->name,
                $details['author'] ?? null
            );
            $status = 'new';
            $message = 'ISBN ni kombinacija naziva i autora nisu pronađeni u Zuzi katalogu.';
            $productId = null;

            if ($matches->count() === 1) {
                $productId = (int) $matches->first()->id;
                $status = 'matched';
                $message = 'Postojeći Zuzi artikl pronađen po ISBN-u ili kombinaciji naziva i autora.';
            } elseif ($matches->count() > 1) {
                $status = 'conflict';
                $message = 'ISBN ili kombinacija naziva i autora odgovara na više Zuzi artikala: '
                    . $matches->pluck('id')->implode(', ') . '.';
            } elseif (empty($details['isbn'])) {
                throw new RuntimeException(
                    'ISBN nije pronađen na Laguna stranici, a naziv i autor ne odgovaraju postojećem Zuzi artiklu.'
                );
            }

            $source->update([
                'product_id' => $productId,
                'isbn' => $details['isbn'],
                'author' => $details['author'] ?: null,
                'genre' => $details['genre'] ?: null,
                'format' => $details['format'] ?: null,
                'pages' => $details['pages'],
                'letter' => $details['letter'] ?: null,
                'binding' => $details['binding'] ?: null,
                'publication_year' => $details['publication_year'],
                'detail_payload' => $details,
                'checked_source_hash' => $source->source_hash,
                'check_status' => $status,
                'check_message' => $message,
                'checked_at' => now(),
            ]);

            return $source->fresh(['product']);
        } catch (\Throwable $exception) {
            $source->update([
                'checked_source_hash' => $source->source_hash,
                'check_status' => 'error',
                'check_message' => $exception->getMessage(),
                'checked_at' => now(),
            ]);

            throw $exception;
        }
    }

    public function import(LagunaImportProduct $source, ?int $additionalCategoryId = null): array
    {
        $source = $this->inspect($source);

        if ($source->check_status === 'conflict') {
            throw new RuntimeException($source->check_message ?: 'Artikl ima konflikt u Zuzi katalogu.');
        }

        $settings = $this->settings->all();
        $this->validateImportSettings($settings, $additionalCategoryId);

        if ($source->product_id) {
            return $this->handleExisting($source, $settings, $additionalCategoryId);
        }

        if ($source->check_status !== 'new') {
            throw new RuntimeException('Artikl nije sigurno potvrđen kao nov.');
        }

        $descriptionResult = $this->importDescription($source, $settings);
        $description = $descriptionResult['description'];
        $translationWarning = $descriptionResult['warning'];
        $price = $this->priceCalculator->convert(
            $source->price_rsd,
            $settings['exchange_rate'],
            $settings['markup_percent']
        );
        $special = $source->sale_price_rsd
            ? $this->priceCalculator->convert($source->sale_price_rsd, $settings['exchange_rate'], $settings['markup_percent'])
            : null;

        if ($price <= 0) {
            throw new RuntimeException('Izračunata EUR cijena mora biti veća od nule.');
        }

        $product = DB::transaction(function () use (
            $source,
            $settings,
            $description,
            $price,
            $special,
            $additionalCategoryId
        ) {
            $locked = LagunaImportProduct::query()->lockForUpdate()->findOrFail($source->id);
            $matches = $this->findExistingProducts($locked->isbn, $locked->name, $locked->author);

            if ($matches->count() > 0) {
                if ($matches->count() > 1) {
                    $locked->update([
                        'check_status' => 'conflict',
                        'check_message' => 'ISBN ili kombinacija naziva i autora odgovara na više Zuzi artikala: '
                            . $matches->pluck('id')->implode(', ') . '.',
                    ]);
                    throw new RuntimeException($locked->check_message);
                }

                $existing = $matches->first();
                $locked->update([
                    'product_id' => $existing->id,
                    'check_status' => 'matched',
                    'check_message' => 'Postojeći Zuzi artikl pronađen po ISBN-u ili kombinaciji naziva i autora prije spremanja.',
                ]);

                return $existing;
            }

            $authorId = $this->resolveAuthorId($locked->author);

            return $this->identifierAllocator->confirm(null, function (array $identifiers) use (
                $locked,
                $settings,
                $description,
                $price,
                $special,
                $authorId,
                $additionalCategoryId
            ) {
                $request = Request::create('/admin/catalog/laguna-import', 'POST', [
                    'name' => $locked->name,
                    'sku' => $identifiers['sku'],
                    'itemid' => $identifiers['itemid'],
                    'isbn' => $locked->isbn,
                    'description' => $this->descriptionHtml($description),
                    'price' => $price,
                    'quantity' => $this->quantity($locked, $settings),
                    'tax_id' => (int) config('settings.default_tax_id', 1),
                    'special' => $special && $special < $price ? $special : null,
                    'category' => $this->creationCategoryIds($settings, $additionalCategoryId),
                    'author_id' => $authorId,
                    'publisher_id' => (int) $settings['publisher_id'],
                    'meta_title' => $locked->name,
                    'meta_description' => Str::limit($description, 250, ''),
                    'pages' => $locked->pages,
                    'dimensions' => $locked->format,
                    'origin' => 'Beograd',
                    'letter' => $locked->letter,
                    'language' => 'Srpski',
                    'condition' => 'Nova knjiga',
                    'binding' => $locked->binding,
                    'year' => $locked->publication_year,
                    'status' => $settings['activate_new_products'] ? 'on' : null,
                ]);

                $created = (new Product())->validateRequest($request)->create();
                if (! $created) {
                    throw new RuntimeException('Zuzi artikl nije moguće spremiti.');
                }

                $this->deduplicateCategoryLinks($created);

                return $created;
            });
        }, 3);

        $freshSource = $source->fresh();
        if ($freshSource->product_id && (int) $freshSource->product_id === (int) $product->id) {
            return $this->handleExisting($freshSource, $settings, $additionalCategoryId);
        }

        $imageWarning = $this->storeImages($product, $source);
        $warnings = trim(implode(' ', array_filter([$translationWarning, $imageWarning])));

        $source->update([
            'product_id' => $product->id,
            'check_status' => 'matched',
            'check_message' => 'Novi Zuzi artikl uspješno je uvezen.' . ($warnings ? ' ' . $warnings : ''),
            'imported_hash' => $source->source_hash,
            'imported_at' => now(),
        ]);

        return [
            'action' => 'created',
            'message' => 'Artikl je uvezen u Zuzi.' . ($warnings ? ' ' . $warnings : ''),
            'product_id' => (int) $product->id,
        ];
    }

    private function handleExisting(
        LagunaImportProduct $source,
        array $settings,
        ?int $additionalCategoryId = null
    ): array
    {
        $product = Product::query()->find($source->product_id);
        if (! $product) {
            $source->update(['product_id' => null, 'check_status' => 'pending']);
            throw new RuntimeException('Povezani Zuzi artikl više ne postoji. Ponovite provjeru.');
        }

        $categoriesAdded = $this->ensureCategories($product, $settings, $additionalCategoryId);
        $categoryMessage = $categoriesAdded ? ' Dodane su odabrane kategorije.' : '';

        if ($settings['existing_action'] === 'price_stock') {
            $price = $this->priceCalculator->convert($source->price_rsd, $settings['exchange_rate'], $settings['markup_percent']);
            $special = $source->sale_price_rsd
                ? $this->priceCalculator->convert($source->sale_price_rsd, $settings['exchange_rate'], $settings['markup_percent'])
                : null;

            $product->update([
                'price' => $price,
                'special' => $special && $special < $price ? $special : null,
                'quantity' => $this->quantity($source, $settings),
            ]);
            $source->update([
                'imported_hash' => $source->source_hash,
                'imported_at' => now(),
                'check_message' => 'Postojećem artiklu ažurirane su cijena i količina.' . $categoryMessage,
            ]);

            return [
                'action' => 'updated',
                'message' => 'Postojećem artiklu ažurirane su cijena i količina.' . $categoryMessage,
                'product_id' => (int) $product->id,
            ];
        }

        $source->update([
            'check_message' => 'Artikl već postoji u Zuzi katalogu i preskočen je.' . $categoryMessage,
        ]);

        return [
            'action' => 'skipped',
            'message' => 'Artikl već postoji u Zuzi katalogu i preskočen je.' . $categoryMessage,
            'product_id' => (int) $product->id,
        ];
    }

    private function importDescription(LagunaImportProduct $source, array $settings): array
    {
        $description = trim((string) $source->description);
        if (! ($settings['translate_descriptions'] ?? false) || $description === '') {
            return ['description' => $description, 'warning' => ''];
        }

        $hash = hash('sha256', $description);
        if ($source->translated_description && hash_equals($hash, (string) $source->translation_source_hash)) {
            return ['description' => $source->translated_description, 'warning' => ''];
        }

        try {
            $translated = $this->translator->translateDescription($description);
        } catch (RuntimeException | ConnectionException $exception) {
            logger()->warning('Laguna opis nije preveden; koristi se izvorni opis.', [
                'source_id' => $source->id,
                'reason' => $exception->getMessage(),
            ]);

            return [
                'description' => $description,
                'warning' => 'Prijevod opisa nije uspio; spremljen je izvorni opis.',
            ];
        }

        $source->update([
            'translated_description' => $translated,
            'translation_source_hash' => $hash,
        ]);

        return ['description' => $translated, 'warning' => ''];
    }

    private function findExistingProducts(?string $isbn, ?string $name = null, ?string $author = null)
    {
        $normalizedIsbn = strtoupper(preg_replace('/[^0-9X]/i', '', (string) $isbn) ?? '');
        $normalizedName = mb_strtolower(trim((string) $name));
        $normalizedAuthor = mb_strtolower(trim(explode(',', (string) $author)[0]));
        $hasTitleAuthor = $normalizedName !== '' && $normalizedAuthor !== '';

        if ($normalizedIsbn === '' && ! $hasTitleAuthor) {
            return collect();
        }

        return Product::query()
            ->where(function ($query) use ($normalizedIsbn, $normalizedName, $normalizedAuthor, $hasTitleAuthor) {
                if ($normalizedIsbn !== '') {
                    $query->where(function ($isbnQuery) use ($normalizedIsbn) {
                        $isbnQuery->where('isbn', $normalizedIsbn)
                            ->orWhere('ean', $normalizedIsbn)
                            ->orWhereRaw(
                                "REPLACE(REPLACE(UPPER(COALESCE(isbn, '')), '-', ''), ' ', '') = ?",
                                [$normalizedIsbn]
                            )
                            ->orWhereRaw(
                                "REPLACE(REPLACE(UPPER(COALESCE(ean, '')), '-', ''), ' ', '') = ?",
                                [$normalizedIsbn]
                            );
                    });
                }

                if ($hasTitleAuthor) {
                    $method = $normalizedIsbn !== '' ? 'orWhere' : 'where';
                    $query->{$method}(function ($titleAuthorQuery) use ($normalizedName, $normalizedAuthor) {
                        $titleAuthorQuery
                            ->whereRaw("LOWER(TRIM(COALESCE(products.name, ''))) = ?", [$normalizedName])
                            ->whereHas('author', function ($authorQuery) use ($normalizedAuthor) {
                                $authorQuery->whereRaw(
                                    "LOWER(TRIM(COALESCE(authors.title, ''))) = ?",
                                    [$normalizedAuthor]
                                );
                            });
                    });
                }
            })
            ->get(['id', 'name', 'sku', 'itemid', 'isbn', 'ean', 'price', 'quantity']);
    }

    private function resolveAuthorId(?string $authors): int
    {
        $name = trim(explode(',', (string) $authors)[0]);
        if ($name === '') {
            return 0;
        }

        $existing = Author::query()
            ->whereRaw('LOWER(TRIM(title)) = ?', [mb_strtolower($name)])
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $slug = $this->uniqueAuthorSlug($name);
        $author = Author::query()->create([
            'letter' => Helper::resolveFirstLetter($name),
            'title' => $name,
            'description' => null,
            'meta_title' => $name,
            'meta_description' => null,
            'lang' => 'hr',
            'sort_order' => 0,
            'status' => 1,
            'slug' => $slug,
            'url' => config('settings.author_path') . '/' . $slug,
        ]);

        return (int) $author->id;
    }

    private function uniqueAuthorSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'laguna-autor';
        $slug = $base;
        $counter = 2;

        while (Author::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    private function quantity(LagunaImportProduct $source, array $settings): int
    {
        return $source->availability === 'in stock'
            ? max(0, (int) $settings['default_quantity'])
            : 0;
    }

    private function descriptionHtml(string $description): string
    {
        $paragraphs = preg_split('/\n{2,}/u', trim($description)) ?: [];

        return implode('', array_map(function ($paragraph) {
            return '<p>' . e(trim($paragraph)) . '</p>';
        }, array_filter($paragraphs, 'strlen')));
    }

    private function validateImportSettings(array $settings, ?int $additionalCategoryId): void
    {
        $parent = Category::query()->find((int) $settings['publisher_parent_category_id']);
        $publisherCategory = Category::query()->find((int) $settings['publisher_category_id']);

        if (! $parent || (int) $parent->parent_id !== 0) {
            throw new RuntimeException('Prije importa mapirajte obaveznu kategoriju Nakladnici.');
        }

        if (! $publisherCategory || (int) $publisherCategory->parent_id !== (int) $parent->id) {
            throw new RuntimeException('Obavezna Laguna kategorija mora biti podkategorija odabrane kategorije Nakladnici.');
        }

        if ($additionalCategoryId !== null && ! Category::query()->whereKey($additionalCategoryId)->exists()) {
            throw new RuntimeException('Odabrana dodatna kategorija više ne postoji.');
        }

        if ((int) $settings['publisher_id'] < 1
            || ! Publisher::query()->whereKey($settings['publisher_id'])->exists()) {
            throw new RuntimeException('Prije importa odaberite izdavača Laguna.');
        }
    }

    private function creationCategoryIds(array $settings, ?int $additionalCategoryId): array
    {
        $parentId = (int) $settings['publisher_parent_category_id'];
        $categoryIds = [(int) $settings['publisher_category_id']];

        if ($additionalCategoryId && $additionalCategoryId !== $parentId) {
            $categoryIds[] = $additionalCategoryId;
        }

        return array_values(array_unique($categoryIds));
    }

    private function ensureCategories(
        Product $product,
        array $settings,
        ?int $additionalCategoryId
    ): bool {
        $requestedIds = array_values(array_unique(array_filter([
            (int) $settings['publisher_parent_category_id'],
            (int) $settings['publisher_category_id'],
            $additionalCategoryId,
        ])));

        $categories = Category::query()->whereIn('id', $requestedIds)->get(['id', 'parent_id']);
        $categoryIds = $categories->pluck('id')
            ->merge($categories->pluck('parent_id')->filter())
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
        $added = false;

        foreach ($categoryIds as $categoryId) {
            $exists = DB::table('product_category')
                ->where('product_id', $product->id)
                ->where('category_id', $categoryId)
                ->exists();

            if (! $exists) {
                DB::table('product_category')->insert([
                    'product_id' => $product->id,
                    'category_id' => $categoryId,
                ]);
                $added = true;
            }
        }

        if ($added) {
            Action::syncCategoryActionForProduct((int) $product->id);
        }

        return $added;
    }

    private function deduplicateCategoryLinks(Product $product): void
    {
        $categoryIds = DB::table('product_category')
            ->where('product_id', $product->id)
            ->pluck('category_id')
            ->map(fn ($id) => (int) $id);
        $uniqueIds = $categoryIds->unique()->values();

        if ($categoryIds->count() === $uniqueIds->count()) {
            return;
        }

        DB::table('product_category')->where('product_id', $product->id)->delete();
        DB::table('product_category')->insert($uniqueIds->map(function (int $categoryId) use ($product) {
            return [
                'product_id' => $product->id,
                'category_id' => $categoryId,
            ];
        })->all());

        Action::syncCategoryActionForProduct((int) $product->id);
    }

    private function assertProductUrl(string $url): void
    {
        $parts = parse_url($url);
        if (($parts['scheme'] ?? '') !== 'https'
            || strtolower($parts['host'] ?? '') !== strtolower((string) config('laguna_import.allowed_product_host'))) {
            throw new RuntimeException('Laguna poveznica artikla nije dopuštena.');
        }
    }

    private function storeImages(Product $product, LagunaImportProduct $source): string
    {
        $urls = array_values(array_filter(array_unique(array_merge(
            [$source->image_url],
            $source->additional_image_urls ?: []
        ))));

        if ($urls === []) {
            return '';
        }

        try {
            foreach (array_slice($urls, 0, 4) as $index => $url) {
                $this->assertImageUrl($url);
                $response = Http::withOptions(['connect_timeout' => 5])
                    ->timeout(30)
                    ->withHeaders(['User-Agent' => 'Zuzi-Laguna-Importer/1.0'])
                    ->get($url);

                if (! $response->successful() || strlen($response->body()) > (int) config('laguna_import.max_image_bytes')) {
                    throw new RuntimeException('Slika nije dostupna ili je prevelika.');
                }

                $image = Image::make($response->body());
                $base = $product->id . '/' . Str::slug($product->name) . '-laguna-' . ($index + 1);
                $path = $base . '.webp';
                Storage::disk('products')->put($path, (string) $image->encode('webp'));

                $thumb = clone $image;
                $thumb->resize(null, 300, function ($constraint) {
                    $constraint->aspectRatio();
                    $constraint->upsize();
                })->resizeCanvas(250, null);
                Storage::disk('products')->put($base . '-thumb.webp', (string) $thumb->encode('webp', 80));

                $storedPath = config('filesystems.disks.products.url') . $path;
                if ($index === 0) {
                    $product->update(['image' => $storedPath]);
                } else {
                    ProductImage::query()->create([
                        'product_id' => $product->id,
                        'image' => $storedPath,
                        'alt' => $product->name,
                        'published' => 1,
                        'sort_order' => $index,
                    ]);
                }
            }
        } catch (\Throwable $exception) {
            return 'Uvoz slike nije uspio: ' . $exception->getMessage();
        }

        return '';
    }

    private function assertImageUrl(string $url): void
    {
        $parts = parse_url($url);
        $allowed = array_map('strtolower', (array) config('laguna_import.allowed_image_hosts', []));
        if (($parts['scheme'] ?? '') !== 'https' || ! in_array(strtolower($parts['host'] ?? ''), $allowed, true)) {
            throw new RuntimeException('Domena slike nije dopuštena.');
        }
    }
}
