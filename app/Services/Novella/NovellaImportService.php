<?php

namespace App\Services\Novella;

use App\Helpers\Helper;
use App\Models\Back\Catalog\Author;
use App\Models\Back\Catalog\Category;
use App\Models\Back\Catalog\NovellaImportProduct;
use App\Models\Back\Catalog\Product\Product;
use App\Models\Back\Catalog\Product\ProductImage;
use App\Models\Back\Catalog\Publisher;
use App\Models\Back\Marketing\Action;
use App\Services\ProductIdentifierAllocator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use RuntimeException;

class NovellaImportService
{
    private const FEED_BASELINE_KEY = '_novella_feed';

    private const CHECKED_FEED_BASELINE_KEY = '_novella_checked_feed';

    private NovellaProductPageClient $pageClient;

    private NovellaProductDetailParser $detailParser;

    private NovellaImportSettings $settings;

    private NovellaPriceCalculator $priceCalculator;

    private ProductIdentifierAllocator $identifierAllocator;

    public function __construct(
        NovellaProductPageClient $pageClient,
        NovellaProductDetailParser $detailParser,
        NovellaImportSettings $settings,
        NovellaPriceCalculator $priceCalculator,
        ProductIdentifierAllocator $identifierAllocator
    ) {
        $this->pageClient = $pageClient;
        $this->detailParser = $detailParser;
        $this->settings = $settings;
        $this->priceCalculator = $priceCalculator;
        $this->identifierAllocator = $identifierAllocator;
    }

    public function inspect(NovellaImportProduct $source, bool $force = false): NovellaImportProduct
    {
        if (! $source->is_current) {
            throw new NovellaTerminalException(
                'Artikl više nije prisutan u aktualnom Novella feedu.'
            );
        }

        if (! $force && $source->checked_source_hash === $source->source_hash
            && in_array($source->check_status, ['new', 'matched', 'conflict'], true)) {
            return $source->fresh(['product']);
        }

        try {
            $details = $this->detailParser->parse($this->pageClient->fetch($source->source_url));
            $this->assertDetailIdentity($source, $details);
            $author = trim((string) ($details['author'] ?? '')) ?: null;
            if ($author === null) {
                $author = trim((string) $this->inspectionFallback($source, 'author', $source->author)) ?: null;
            }
            $isbn = $this->normalizeIdentifier($details['isbn'] ?? null) ?: null;
            if ($isbn === null) {
                $isbn = $this->normalizeIdentifier(
                    $this->inspectionFallback($source, 'isbn', $source->isbn)
                ) ?: null;
            }
            $ean = $this->normalizeIdentifier($details['ean'] ?? null) ?: null;
            if ($ean === null) {
                $ean = $this->normalizeIdentifier(
                    $this->inspectionFallback($source, 'ean', $source->ean)
                ) ?: null;
            }
            $matches = $this->findExistingProducts($isbn, $ean, $source->name, $author);
            $status = 'new';
            $message = 'ISBN, EAN ni kombinacija naziva i autora nisu pronađeni u Zuzi katalogu.';
            $productId = null;

            if ($matches->count() === 1) {
                $productId = (int) $matches->first()->id;
                $status = 'matched';
                $message = 'Postojeći Zuzi artikl pronađen po ISBN-u, EAN-u ili kombinaciji naziva i autora.';
            } elseif ($matches->count() > 1) {
                $status = 'conflict';
                $message = 'ISBN, EAN ili kombinacija naziva i autora odgovara na više Zuzi artikala: '
                    . $matches->pluck('id')->implode(', ') . '.';
            } elseif ($isbn === null && $ean === null
                && ($author === null || trim((string) $source->name) === '')) {
                throw new NovellaTerminalException(
                    'ISBN ni EAN nisu pronađeni na Novella stranici, a nedostaje naziv ili autor za sigurnu provjeru.'
                );
            }

            $detailImages = array_values(array_unique(array_filter(array_merge(
                (array) ($details['images'] ?? []),
                [$details['image_url'] ?? null],
                (array) ($details['additional_image_urls'] ?? [])
            ))));
            if ($detailImages !== []) {
                $images = $detailImages;
            } elseif ($this->feedChangedSinceInspection($source, 'image_url')
                || $this->feedChangedSinceInspection($source, 'additional_image_urls')) {
                $images = array_values(array_unique(array_filter(array_merge(
                    [(string) $this->inspectionFallback($source, 'image_url', $source->image_url)],
                    (array) $this->inspectionFallback(
                        $source,
                        'additional_image_urls',
                        (array) $source->additional_image_urls
                    )
                ))));
            } else {
                $images = array_values(array_unique(array_filter(array_merge(
                    [$source->image_url],
                    (array) $source->additional_image_urls
                ))));
            }
            $categories = $this->stringList(array_merge(
                (array) $source->source_categories,
                (array) ($details['source_categories'] ?? [])
            ));
            $genres = $this->stringList(array_merge(
                (array) $source->source_genres,
                (array) ($details['source_genres'] ?? []),
                (array) ($details['genres'] ?? [])
            ));
            $description = trim((string) ($details['description'] ?? ''));
            if ($description === '') {
                $description = trim((string) $this->inspectionFallback(
                    $source,
                    'description',
                    $source->description
                ));
            }
            $sourcePublisher = trim((string) ($details['publisher'] ?? ''));
            if ($sourcePublisher === '') {
                $sourcePublisher = trim((string) $this->inspectionFallback(
                    $source,
                    'source_publisher',
                    $source->source_publisher
                ));
            }
            $detailPayload = $details;
            $existingDetailPayload = is_array($source->detail_payload)
                ? $source->detail_payload
                : [];
            if (isset($existingDetailPayload[self::FEED_BASELINE_KEY])
                && is_array($existingDetailPayload[self::FEED_BASELINE_KEY])) {
                $detailPayload[self::FEED_BASELINE_KEY] = $existingDetailPayload[self::FEED_BASELINE_KEY];
                $detailPayload[self::CHECKED_FEED_BASELINE_KEY]
                    = $existingDetailPayload[self::FEED_BASELINE_KEY];
            }

            $source->update([
                'product_id' => $productId,
                'description' => $description !== '' ? $description : null,
                'source_categories' => $categories,
                'source_publisher' => $sourcePublisher !== '' ? $sourcePublisher : null,
                'image_url' => $images[0] ?? null,
                'additional_image_urls' => array_slice($images, 1),
                'isbn' => $isbn,
                'ean' => $ean,
                'author' => $author,
                'source_genres' => $genres,
                'genre' => trim((string) ($details['genre'] ?? ($genres[0] ?? ''))) ?: null,
                'format' => trim((string) ($details['format'] ?? $source->format)) ?: null,
                'pages' => $this->positiveInteger($details['pages'] ?? $source->pages),
                'letter' => trim((string) ($details['letter'] ?? $source->letter)) ?: null,
                'binding' => trim((string) ($details['binding'] ?? $source->binding)) ?: null,
                'publication_year' => $this->publicationYear(
                    $details['publication_year'] ?? $details['year'] ?? $source->publication_year
                ),
                'language' => trim((string) ($details['language'] ?? $source->language)) ?: 'Hrvatski',
                'origin' => trim((string) ($details['origin'] ?? $source->origin)) ?: null,
                'detail_payload' => $detailPayload,
                'checked_source_hash' => $source->source_hash,
                'check_status' => $status,
                'check_message' => $message,
                'checked_at' => now(),
            ]);

            return $source->fresh(['product']);
        } catch (NovellaRetryableException $exception) {
            $updates = ['check_message' => $exception->getMessage()];
            if (! hash_equals((string) $source->source_hash, (string) $source->checked_source_hash)) {
                $updates['check_status'] = 'pending';
            }
            $source->update($updates);

            throw $exception;
        } catch (NovellaTerminalException $exception) {
            $source->update([
                'checked_source_hash' => $source->source_hash,
                'check_status' => 'error',
                'check_message' => $exception->getMessage(),
                'checked_at' => now(),
            ]);

            throw $exception;
        }
    }

    /**
     * Prefer the latest feed value only when that value changed after the last
     * successful detail inspection. Otherwise keep the existing enrichment.
     *
     * @return mixed
     */
    private function inspectionFallback(NovellaImportProduct $source, string $field, $existing)
    {
        if (! $this->feedChangedSinceInspection($source, $field)) {
            return $existing;
        }

        $payload = is_array($source->detail_payload) ? $source->detail_payload : [];
        $feed = $payload[self::FEED_BASELINE_KEY] ?? [];

        return is_array($feed) && array_key_exists($field, $feed)
            ? $feed[$field]
            : $existing;
    }

    private function feedChangedSinceInspection(NovellaImportProduct $source, string $field): bool
    {
        $payload = is_array($source->detail_payload) ? $source->detail_payload : [];
        $feed = $payload[self::FEED_BASELINE_KEY] ?? null;
        $checkedFeed = $payload[self::CHECKED_FEED_BASELINE_KEY] ?? null;
        if (! is_array($feed) || ! is_array($checkedFeed)
            || ! array_key_exists($field, $feed)
            || ! array_key_exists($field, $checkedFeed)) {
            return false;
        }

        $current = $feed[$field];
        $checked = $checkedFeed[$field];
        if (is_array($current) || is_array($checked)) {
            return array_values((array) $current) !== array_values((array) $checked);
        }

        return ! ($current === $checked || (string) $current === (string) $checked);
    }

    public function import(NovellaImportProduct $source, ?int $additionalCategoryId = null): array
    {
        $source = $this->inspect($source);
        if ($source->check_status === 'conflict') {
            throw new RuntimeException($source->check_message ?: 'Artikl ima konflikt u Zuzi katalogu.');
        }

        $settings = $this->settings->all();
        $publisherMapping = $this->resolvePublisherMapping($source, $settings);
        $mappedCategoryIds = $this->mappedCategoryIds($source, $settings);
        $this->validateImportSettings(
            $settings,
            $publisherMapping,
            $mappedCategoryIds,
            $additionalCategoryId
        );

        if ($source->product_id) {
            return $this->handleExisting(
                $source,
                $settings,
                $publisherMapping,
                $mappedCategoryIds,
                $additionalCategoryId
            );
        }
        if ($source->check_status !== 'new') {
            throw new RuntimeException('Artikl nije sigurno potvrđen kao nov.');
        }

        $description = trim((string) $source->description);
        $price = $this->priceCalculator->calculate(
            $source->price_eur,
            $settings['markup_percent']
        );
        $special = $source->sale_price_eur
            ? $this->priceCalculator->calculate(
                $source->sale_price_eur,
                $settings['markup_percent']
            )
            : null;
        if ($price <= 0) {
            throw new RuntimeException('Izračunata EUR cijena mora biti veća od nule.');
        }

        $product = DB::transaction(function () use (
            $source,
            $settings,
            $publisherMapping,
            $mappedCategoryIds,
            $additionalCategoryId,
            $description,
            $price,
            $special
        ) {
            $locked = NovellaImportProduct::query()->lockForUpdate()->findOrFail($source->id);
            $matches = $this->findExistingProducts(
                $locked->isbn,
                $locked->ean,
                $locked->name,
                $locked->author,
                true
            );
            if ($matches->count() > 0) {
                if ($matches->count() > 1) {
                    $locked->update([
                        'check_status' => 'conflict',
                        'check_message' => 'ISBN, EAN ili kombinacija naziva i autora odgovara na više Zuzi artikala: '
                            . $matches->pluck('id')->implode(', ') . '.',
                    ]);
                    throw new RuntimeException($locked->check_message);
                }

                $existing = $matches->first();
                $locked->update([
                    'product_id' => $existing->id,
                    'check_status' => 'matched',
                    'check_message' => 'Postojeći Zuzi artikl pronađen neposredno prije spremanja.',
                ]);

                return $existing;
            }

            return $this->identifierAllocator->confirm(null, function (array $identifiers) use (
                $locked,
                $settings,
                $publisherMapping,
                $mappedCategoryIds,
                $additionalCategoryId,
                $description,
                $price,
                $special
            ) {
                $serializedMatches = $this->findExistingProducts(
                    $locked->isbn,
                    $locked->ean,
                    $locked->name,
                    $locked->author,
                    true
                );
                if ($serializedMatches->count() > 0) {
                    if ($serializedMatches->count() > 1) {
                        $locked->update([
                            'check_status' => 'conflict',
                            'check_message' => 'ISBN, EAN ili kombinacija naziva i autora odgovara na više Zuzi artikala: '
                                . $serializedMatches->pluck('id')->implode(', ') . '.',
                        ]);
                        throw new RuntimeException($locked->check_message);
                    }

                    $existing = $serializedMatches->first();
                    $locked->update([
                        'product_id' => $existing->id,
                        'check_status' => 'matched',
                        'check_message' => 'Postojeći Zuzi artikl pronađen neposredno prije spremanja.',
                    ]);

                    return $existing;
                }

                $request = Request::create('/admin/catalog/novella-import', 'POST', [
                    'name' => $locked->name,
                    'sku' => $identifiers['sku'],
                    'itemid' => $identifiers['itemid'],
                    'isbn' => $locked->isbn,
                    'description' => $this->descriptionHtml($description),
                    'price' => $price,
                    'quantity' => $this->quantity($locked, $settings),
                    'tax_id' => (int) config('settings.default_tax_id', 1),
                    'special' => $special && $special < $price ? $special : null,
                    'category' => $this->creationCategoryIds(
                        $publisherMapping,
                        $mappedCategoryIds,
                        $additionalCategoryId
                    ),
                    'author_id' => $this->resolveAuthorId($locked->author),
                    'publisher_id' => $publisherMapping['publisher_id'],
                    'meta_title' => $locked->name,
                    'meta_description' => Str::limit($description, 250, ''),
                    'pages' => $locked->pages,
                    'dimensions' => $locked->format,
                    'origin' => $locked->origin,
                    'letter' => $locked->letter ?: 'Latinica',
                    'language' => $locked->language ?: 'Hrvatski',
                    'condition' => 'Nova knjiga',
                    'binding' => $locked->binding,
                    'year' => $locked->publication_year,
                    'status' => $settings['activate_new_products'] ? 'on' : null,
                ]);

                $created = (new Product())->validateRequest($request)->create();
                if (! $created) {
                    throw new RuntimeException('Zuzi artikl nije moguće spremiti.');
                }
                if ($locked->ean && (string) $created->ean !== (string) $locked->ean) {
                    $created->update(['ean' => $locked->ean]);
                }
                $this->deduplicateCategoryLinks($created);

                return $created;
            });
        }, 3);

        $freshSource = $source->fresh();
        if ($freshSource->product_id && (int) $freshSource->product_id === (int) $product->id) {
            return $this->handleExisting(
                $freshSource,
                $settings,
                $publisherMapping,
                $mappedCategoryIds,
                $additionalCategoryId
            );
        }

        $imageWarning = $this->storeImages($product, $source);
        $source->update([
            'product_id' => $product->id,
            'check_status' => 'matched',
            'check_message' => 'Novi Zuzi artikl uspješno je uvezen.'
                . ($imageWarning ? ' ' . $imageWarning : ''),
            'imported_hash' => $source->source_hash,
            'imported_at' => now(),
        ]);

        return [
            'action' => 'created',
            'message' => 'Artikl je uvezen u Zuzi.' . ($imageWarning ? ' ' . $imageWarning : ''),
            'product_id' => (int) $product->id,
        ];
    }

    private function handleExisting(
        NovellaImportProduct $source,
        array $settings,
        array $publisherMapping,
        array $mappedCategoryIds,
        ?int $additionalCategoryId
    ): array {
        $product = Product::query()->find($source->product_id);
        if (! $product) {
            $source->update(['product_id' => null, 'check_status' => 'pending']);
            throw new RuntimeException('Povezani Zuzi artikl više ne postoji. Ponovite provjeru.');
        }

        $categoriesAdded = $this->ensureCategories(
            $product,
            $settings,
            $publisherMapping,
            $mappedCategoryIds,
            $additionalCategoryId
        );
        $categoryMessage = $categoriesAdded ? ' Dodane su odabrane kategorije.' : '';
        $imageMessage = '';
        if ($source->imported_at && ! $product->image && $source->image_url) {
            $warning = $this->storeImages($product, $source);
            $imageMessage = $warning ? ' ' . $warning : ' Slika je uspješno ponovno uvezena.';
        }

        if ($settings['existing_action'] === 'price_stock') {
            $price = $this->priceCalculator->calculate(
                $source->price_eur,
                $settings['markup_percent']
            );
            $special = $source->sale_price_eur
                ? $this->priceCalculator->calculate(
                    $source->sale_price_eur,
                    $settings['markup_percent']
                )
                : null;
            $product->update([
                'price' => $price,
                'special' => $special && $special < $price ? $special : null,
                'quantity' => $this->quantity($source, $settings),
            ]);
            $source->update([
                'imported_hash' => $source->source_hash,
                'imported_at' => now(),
                'check_message' => 'Postojećem artiklu ažurirane su cijena i količina.'
                    . $categoryMessage . $imageMessage,
            ]);

            return [
                'action' => 'updated',
                'message' => 'Postojećem artiklu ažurirane su cijena i količina.'
                    . $categoryMessage . $imageMessage,
                'product_id' => (int) $product->id,
            ];
        }

        $source->update([
            'check_message' => 'Artikl već postoji u Zuzi katalogu i preskočen je.'
                . $categoryMessage . $imageMessage,
        ]);

        return [
            'action' => 'skipped',
            'message' => 'Artikl već postoji u Zuzi katalogu i preskočen je.'
                . $categoryMessage . $imageMessage,
            'product_id' => (int) $product->id,
        ];
    }

    /**
     * ISBN/EAN identifiers are authoritative. Title + first author is used
     * only when neither identifier finds a row, avoiding false conflicts on
     * alternate editions.
     */
    private function findExistingProducts(
        ?string $isbn,
        ?string $ean,
        ?string $name,
        ?string $author,
        bool $lockForUpdate = false
    ) {
        $identifiers = collect([$isbn, $ean])
            ->map(fn ($value) => $this->normalizeIdentifier($value))
            ->filter()
            ->unique()
            ->values();
        if ($identifiers->isNotEmpty()) {
            $query = Product::query()->where(function ($identifierQuery) use ($identifiers) {
                $identifierQuery
                    ->whereIn('isbn', $identifiers)
                    ->orWhereIn('ean', $identifiers);
            });
            if ($lockForUpdate) {
                $query->lockForUpdate();
            }
            $matches = $query->get($this->matchColumns());

            // Legacy rows can contain separators. This fallback runs only when
            // the indexed exact lookup did not find anything.
            if ($matches->isEmpty()) {
                $query = Product::query()->where(function ($identifierQuery) use ($identifiers) {
                    foreach ($identifiers as $index => $identifier) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $identifierQuery->{$method}(function ($normalizedQuery) use ($identifier) {
                            $normalizedQuery
                                ->whereRaw(
                                    "REPLACE(REPLACE(UPPER(COALESCE(isbn, '')), '-', ''), ' ', '') = ?",
                                    [$identifier]
                                )
                                ->orWhereRaw(
                                    "REPLACE(REPLACE(UPPER(COALESCE(ean, '')), '-', ''), ' ', '') = ?",
                                    [$identifier]
                                );
                        });
                    }
                });
                if ($lockForUpdate) {
                    $query->lockForUpdate();
                }
                $matches = $query->get($this->matchColumns());
            }

            if ($matches->isNotEmpty()) {
                return $matches->unique('id')->values();
            }
        }

        $name = trim((string) $name);
        $author = trim(explode(',', (string) $author)[0]);
        if ($name === '' || $author === '') {
            return collect();
        }

        $query = Product::query()
            ->where('products.name', $name)
            ->whereHas('author', function ($authorQuery) use ($author) {
                $authorQuery->where('authors.title', $author);
            });
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get($this->matchColumns())->unique('id')->values();
    }

    private function matchColumns(): array
    {
        return ['id', 'name', 'sku', 'itemid', 'isbn', 'ean', 'price', 'quantity'];
    }

    private function resolvePublisherMapping(NovellaImportProduct $source, array $settings): array
    {
        $mapping = [
            'publisher_id' => (int) $settings['publisher_id'],
            'publisher_category_id' => (int) $settings['publisher_category_id'],
            'source_matched' => false,
        ];
        $name = trim((string) $source->source_publisher);
        if (! ($settings['map_source_publishers'] ?? false) || $name === '') {
            return $mapping;
        }

        $normalized = mb_strtolower($name);
        $publisherId = (int) (Publisher::query()
            ->whereRaw('LOWER(TRIM(title)) = ?', [$normalized])
            ->value('id') ?: 0);
        $categoryId = (int) (Category::query()
            ->where('parent_id', (int) $settings['publisher_parent_category_id'])
            ->whereRaw('LOWER(TRIM(title)) = ?', [$normalized])
            ->value('id') ?: 0);
        if ($publisherId > 0 && $categoryId > 0) {
            $mapping['publisher_id'] = $publisherId;
            $mapping['publisher_category_id'] = $categoryId;
            $mapping['source_matched'] = true;
        }

        return $mapping;
    }

    private function mappedCategoryIds(NovellaImportProduct $source, array $settings): array
    {
        $map = (array) ($settings['category_map'] ?? []);
        $normalizedMap = [];
        foreach ($map as $category => $categoryId) {
            $normalizedMap[mb_strtolower(trim((string) $category))] = (int) $categoryId;
        }

        $ids = [];
        foreach (array_merge(
            (array) $source->source_categories,
            (array) $source->source_genres
        ) as $category) {
            $categoryId = $normalizedMap[mb_strtolower(trim((string) $category))] ?? 0;
            if ($categoryId > 0) {
                $ids[] = $categoryId;
            }
        }

        return array_values(array_unique($ids));
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

        $base = Str::slug($name) ?: 'novella-autor';
        $slug = $base;
        $counter = 2;
        while (Author::query()->where('slug', $slug)->exists()) {
            $slug = $base . '-' . $counter++;
        }
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

    private function validateImportSettings(
        array $settings,
        array $publisherMapping,
        array $mappedCategoryIds,
        ?int $additionalCategoryId
    ): void {
        $parent = Category::query()->find((int) $settings['publisher_parent_category_id']);
        $publisherCategory = Category::query()->find((int) $publisherMapping['publisher_category_id']);
        if (! $parent || (int) $parent->parent_id !== 0) {
            throw new RuntimeException('Prije importa mapirajte obaveznu kategoriju Nakladnici.');
        }
        if (! $publisherCategory || (int) $publisherCategory->parent_id !== (int) $parent->id) {
            throw new RuntimeException(
                'Kategorija nakladnika mora biti podkategorija odabrane kategorije Nakladnici.'
            );
        }
        if ((int) $publisherMapping['publisher_id'] < 1
            || ! Publisher::query()->whereKey($publisherMapping['publisher_id'])->exists()) {
            throw new RuntimeException('Prije importa odaberite valjanog nakladnika Novella.');
        }

        $requestedIds = array_values(array_unique(array_filter(array_merge(
            $mappedCategoryIds,
            [$additionalCategoryId]
        ))));
        if ($requestedIds !== []
            && Category::query()->whereIn('id', $requestedIds)->count() !== count($requestedIds)) {
            throw new RuntimeException('Jedna od mapiranih ili dodatnih kategorija više ne postoji.');
        }
    }

    private function creationCategoryIds(
        array $publisherMapping,
        array $mappedCategoryIds,
        ?int $additionalCategoryId
    ): array {
        return array_values(array_unique(array_filter(array_merge(
            [(int) $publisherMapping['publisher_category_id']],
            $mappedCategoryIds,
            [$additionalCategoryId]
        ))));
    }

    private function ensureCategories(
        Product $product,
        array $settings,
        array $publisherMapping,
        array $mappedCategoryIds,
        ?int $additionalCategoryId
    ): bool {
        $requestedIds = array_values(array_unique(array_filter(array_merge([
            (int) $settings['publisher_parent_category_id'],
            (int) $publisherMapping['publisher_category_id'],
            $additionalCategoryId,
        ], $mappedCategoryIds))));
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
            return ['product_id' => $product->id, 'category_id' => $categoryId];
        })->all());
        Action::syncCategoryActionForProduct((int) $product->id);
    }

    private function quantity(NovellaImportProduct $source, array $settings): int
    {
        return in_array(strtolower(trim((string) $source->availability)), [
            'in stock', 'in_stock', 'instock', 'available', 'onbackorder',
        ], true)
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

    private function storeImages(Product $product, NovellaImportProduct $source): string
    {
        $urls = array_values(array_filter(array_unique(array_merge(
            [$source->image_url],
            (array) $source->additional_image_urls
        ))));
        if ($urls === []) {
            return '';
        }

        try {
            foreach (array_slice($urls, 0, 4) as $index => $url) {
                $this->assertImageUrl($url);
                $temporaryPath = $this->downloadImage($url);
                try {
                    $image = Image::make($temporaryPath);
                    $base = $product->id . '/' . Str::slug($product->name)
                        . '-novella-' . ($index + 1);
                    $path = $base . '.webp';
                    Storage::disk('products')->put($path, (string) $image->encode('webp'));
                    $thumb = clone $image;
                    $thumb->resize(null, 300, function ($constraint) {
                        $constraint->aspectRatio();
                        $constraint->upsize();
                    })->resizeCanvas(250, null);
                    Storage::disk('products')->put(
                        $base . '-thumb.webp',
                        (string) $thumb->encode('webp', 80)
                    );

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
                } finally {
                    @unlink($temporaryPath);
                }
            }
        } catch (\Throwable $exception) {
            return 'Uvoz slike nije uspio: ' . $exception->getMessage();
        }

        return '';
    }

    private function downloadImage(string $url): string
    {
        $maximum = max(1024, (int) config('novella_import.max_image_bytes', 15 * 1024 * 1024));
        $temporaryPath = tempnam(sys_get_temp_dir(), 'zuzi-novella-image-');
        if ($temporaryPath === false) {
            throw new RuntimeException('Nije moguće pripremiti privremenu datoteku za sliku.');
        }

        try {
            $response = Http::withOptions([
                    'sink' => $temporaryPath,
                    'connect_timeout' => 5,
                    'allow_redirects' => false,
                    'on_headers' => function ($response) use ($maximum) {
                        $length = trim((string) $response->getHeaderLine('Content-Length'));
                        if (ctype_digit($length) && (int) $length > $maximum) {
                            throw new RuntimeException('Slika je veća od dopuštene veličine.');
                        }
                    },
                    'progress' => function ($downloadTotal, $downloadedBytes) use ($maximum) {
                        if ((int) $downloadedBytes > $maximum) {
                            throw new RuntimeException('Slika je veća od dopuštene veličine.');
                        }
                    },
                ])
                ->timeout(30)
                ->withHeaders(['User-Agent' => 'Zuzi-Novella-Importer/1.0'])
                ->get($url);
            clearstatcache(true, $temporaryPath);
            $bytes = filesize($temporaryPath);
            if (! $response->successful() || $bytes === false || $bytes < 1 || $bytes > $maximum) {
                throw new RuntimeException('Slika nije dostupna ili je prevelika.');
            }

            $dimensions = @getimagesize($temporaryPath);
            $maximumPixels = max(1000000, (int) config('novella_import.max_image_pixels', 40000000));
            if (! is_array($dimensions)
                || empty($dimensions[0])
                || empty($dimensions[1])
                || ((int) $dimensions[0] * (int) $dimensions[1]) > $maximumPixels) {
                throw new RuntimeException(
                    'Datoteka nije podržana slika ili su joj dimenzije prevelike.'
                );
            }

            return $temporaryPath;
        } catch (\Throwable $exception) {
            @unlink($temporaryPath);
            throw $exception;
        }
    }

    private function assertImageUrl(string $url): void
    {
        $parts = parse_url($url);
        $allowed = array_map('strtolower', (array) config('novella_import.allowed_image_hosts', []));
        if (($parts['scheme'] ?? '') !== 'https'
            || ! in_array(strtolower($parts['host'] ?? ''), $allowed, true)) {
            throw new RuntimeException('Domena slike nije dopuštena.');
        }
    }

    private function assertDetailIdentity(NovellaImportProduct $source, array $details): void
    {
        $remoteId = (int) ($details['remote_product_id'] ?? 0);
        if ($remoteId > 0 && $remoteId !== (int) $source->remote_product_id) {
            throw new NovellaTerminalException(
                'Novella stranica vratila je podatke drugog artikla. Provjera je zaustavljena.'
            );
        }

        $externalId = trim((string) ($details['external_id'] ?? ''));
        if ($externalId !== '' && ! hash_equals((string) $source->external_id, $externalId)) {
            throw new NovellaTerminalException(
                'Novella identitet artikla ne odgovara zapisu iz feeda.'
            );
        }

        $detailUrl = trim((string) ($details['source_url'] ?? ''));
        if ($detailUrl !== ''
            && ! hash_equals(
                $this->normalizedProductUrl((string) $source->source_url),
                $this->normalizedProductUrl($detailUrl)
            )) {
            throw new NovellaTerminalException(
                'Novella poveznica artikla ne odgovara zapisu iz feeda.'
            );
        }
    }

    private function normalizedProductUrl(string $url): string
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts)) {
            return '';
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = '/' . ltrim((string) ($parts['path'] ?? ''), '/');
        $path = rtrim(preg_replace('~/+~', '/', $path) ?? $path, '/') . '/';

        return $scheme . '://' . $host . $path;
    }

    private function normalizeIdentifier($value): string
    {
        return strtoupper(preg_replace('/[^0-9X]/i', '', (string) $value) ?? '');
    }

    private function stringList($values): array
    {
        return array_values(array_unique(array_filter(array_map(function ($value) {
            return trim((string) $value);
        }, (array) $values))));
    }

    private function positiveInteger($value): ?int
    {
        if (! preg_match('/\d+/', (string) $value, $matches)) {
            return null;
        }
        $number = (int) $matches[0];

        return $number > 0 ? $number : null;
    }

    private function publicationYear($value): ?int
    {
        if (! preg_match('/\b(?:19|20)\d{2}\b/', (string) $value, $matches)) {
            return null;
        }
        $year = (int) $matches[0];

        return $year <= (int) date('Y') + 2 ? $year : null;
    }
}
