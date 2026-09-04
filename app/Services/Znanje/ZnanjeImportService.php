<?php

namespace App\Services\Znanje;

use App\Models\Back\Catalog\Category;
use App\Models\Back\Catalog\ZnanjeImportProduct;
use App\Models\Back\Catalog\Product\Product;
use App\Models\Back\Catalog\Product\ProductImage;
use App\Models\Back\Catalog\Publisher;
use App\Models\Back\Marketing\Action;
use App\Services\Catalog\AuthorResolver;
use App\Services\Catalog\ImportedProductName;
use App\Services\ProductIdentifierAllocator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use RuntimeException;

class ZnanjeImportService
{
    private const FEED_BASELINE_KEY = '_znanje_feed';

    private const CHECKED_FEED_BASELINE_KEY = '_znanje_checked_feed';

    private ZnanjeProductPageClient $pageClient;

    private ZnanjeProductDetailParser $detailParser;

    private ZnanjeImportSettings $settings;

    private ZnanjePriceCalculator $priceCalculator;

    private ProductIdentifierAllocator $identifierAllocator;

    private AuthorResolver $authorResolver;

    public function __construct(
        ZnanjeProductPageClient $pageClient,
        ZnanjeProductDetailParser $detailParser,
        ZnanjeImportSettings $settings,
        ZnanjePriceCalculator $priceCalculator,
        ProductIdentifierAllocator $identifierAllocator,
        AuthorResolver $authorResolver
    ) {
        $this->pageClient = $pageClient;
        $this->detailParser = $detailParser;
        $this->settings = $settings;
        $this->priceCalculator = $priceCalculator;
        $this->identifierAllocator = $identifierAllocator;
        $this->authorResolver = $authorResolver;
    }

    public function inspect(ZnanjeImportProduct $source, bool $force = false): ZnanjeImportProduct
    {
        $expectedSourceHash = (string) $source->source_hash;
        if (! $source->is_current) {
            throw new ZnanjeTerminalException(
                'Artikl više nije prisutan u aktualnom Znanje feedu.'
            );
        }

        if (! $force && $source->checked_source_hash === $source->source_hash
            && in_array($source->check_status, ['new', 'matched', 'conflict'], true)) {
            if ($source->check_status === 'new') {
                return $this->recheckCachedNew($source);
            }

            return $source->fresh(['product']);
        }

        try {
            $details = $this->detailParser->parse(
                $this->pageClient->fetch($source->source_url),
                $source->source_url
            );
            $this->assertDetailIdentity($source, $details);
            $author = $this->firstDetailAuthor($details);
            if ($author === null) {
                $fallbackAuthor = trim((string) $this->inspectionFallback(
                    $source,
                    'author',
                    $source->author
                ));
                $author = $fallbackAuthor !== ''
                    ? $this->canonicalAuthorName($fallbackAuthor)
                    : null;
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
                throw new ZnanjeTerminalException(
                    'ISBN ni EAN nisu pronađeni na Znanje stranici, a nedostaje naziv ili autor za sigurnu provjeru.'
                );
            }

            $detailImages = array_values(array_unique(array_filter(array_merge(
                (array) ($details['images'] ?? []),
                [$details['image_url'] ?? $details['image'] ?? null],
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
                [$source->source_category],
                (array) $source->source_categories,
                [$details['source_category'] ?? $details['category'] ?? null],
                (array) ($details['source_categories'] ?? [])
            ));
            $genres = $this->stringList(array_merge(
                (array) $source->source_genres,
                (array) ($details['source_genres'] ?? []),
                (array) ($details['genres'] ?? []),
                [$details['genre'] ?? null]
            ));
            $description = trim((string) ($details['description'] ?? ''));
            if ($description === '') {
                $description = trim((string) $this->inspectionFallback(
                    $source,
                    'description',
                    $source->description
                ));
            }
            $sourcePublisher = trim((string) (
                $details['source_publisher'] ?? $details['publisher'] ?? ''
            ));
            if ($sourcePublisher === '') {
                $sourcePublisher = trim((string) $this->inspectionFallback(
                    $source,
                    'source_publisher',
                    $source->source_publisher
                ));
            }
            $priceEur = $this->positiveMoney($details['price_eur'] ?? null)
                ?? $this->positiveMoney($source->price_eur);
            $salePriceEur = $this->positiveMoney($details['sale_price_eur'] ?? null);
            if ($salePriceEur === null && ! array_key_exists('sale_price_eur', $details)) {
                $salePriceEur = $this->positiveMoney($source->sale_price_eur);
            }
            if ($priceEur !== null && $salePriceEur !== null && $salePriceEur >= $priceEur) {
                $salePriceEur = null;
            }
            $availability = trim((string) ($details['availability'] ?? ''));
            if ($availability === '' && array_key_exists('available', $details)) {
                $availability = $details['available'] ? 'in_stock' : 'out_of_stock';
            }
            if ($availability === '') {
                $availability = trim((string) $source->availability);
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

            $inspectionUpdates = [
                'product_id' => $productId,
                'description' => $description !== '' ? $description : null,
                'source_categories' => $categories,
                'source_publisher' => $sourcePublisher !== '' ? $sourcePublisher : null,
                'image_url' => $images[0] ?? null,
                'additional_image_urls' => array_slice($images, 1),
                'price_eur' => $priceEur ?? 0,
                'sale_price_eur' => $salePriceEur,
                'availability' => $availability !== '' ? $availability : null,
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
                'language' => trim((string) ($details['language'] ?? $source->language)) ?: null,
                'origin' => trim((string) ($details['origin'] ?? $source->origin)) ?: null,
                'detail_payload' => $detailPayload,
                'checked_source_hash' => $expectedSourceHash,
                'check_status' => $status,
                'check_message' => $message,
                'checked_at' => now(),
            ];
            if (! $this->persistInspectionResult(
                (int) $source->id,
                $expectedSourceHash,
                $inspectionUpdates
            )) {
                throw $this->sourceChangedDuringImport();
            }

            return $source->fresh(['product']);
        } catch (ZnanjeRetryableException $exception) {
            $updates = ['check_message' => $exception->getMessage()];
            if (! hash_equals($expectedSourceHash, (string) $source->checked_source_hash)) {
                $updates['check_status'] = 'pending';
            }
            $this->persistInspectionResult((int) $source->id, $expectedSourceHash, $updates);

            throw $exception;
        } catch (ZnanjeTerminalException $exception) {
            $persisted = $this->persistInspectionResult((int) $source->id, $expectedSourceHash, [
                'checked_source_hash' => $expectedSourceHash,
                'check_status' => 'error',
                'check_message' => $exception->getMessage(),
                'checked_at' => now(),
            ]);
            if (! $persisted) {
                throw $this->sourceChangedDuringImport($exception);
            }

            throw $exception;
        }
    }

    private function persistInspectionResult(int $sourceId, string $expectedHash, array $updates): bool
    {
        return DB::transaction(function () use ($sourceId, $expectedHash, $updates) {
            $target = ZnanjeImportProduct::query()
                ->whereKey($sourceId)
                ->where('is_current', true)
                ->where('source_hash', $expectedHash)
                ->lockForUpdate()
                ->first();
            if (! $target) {
                return false;
            }

            $target->update($updates);

            return true;
        }, 3);
    }

    /**
     * Reconcile a cached "new" result with the current Zuzi catalogue without
     * downloading the Znanje detail page again.
     */
    public function recheckCachedNew(ZnanjeImportProduct $source): ZnanjeImportProduct
    {
        if (! $source->is_current
            || $source->product_id
            || $source->checked_source_hash !== $source->source_hash
            || $source->check_status !== 'new') {
            return $source->fresh(['product']);
        }

        $matches = $this->findExistingProducts(
            $source->isbn,
            $source->ean,
            $source->name,
            $source->author
        );

        $updates = null;
        if ($matches->count() === 1) {
            $updates = [
                'product_id' => (int) $matches->first()->id,
                'check_status' => 'matched',
                'check_message' => 'Postojeći Zuzi artikl pronađen po ISBN-u, EAN-u ili kombinaciji naziva i autora.',
            ];
        } elseif ($matches->count() > 1) {
            $updates = [
                'product_id' => null,
                'check_status' => 'conflict',
                'check_message' => 'ISBN, EAN ili kombinacija naziva i autora odgovara na više Zuzi artikala: '
                    . $matches->pluck('id')->implode(', ') . '.',
            ];
        }

        if ($updates !== null) {
            $source->newQuery()
                ->whereKey($source->getKey())
                ->where('is_current', true)
                ->whereNull('product_id')
                ->where('check_status', 'new')
                ->where('source_hash', (string) $source->source_hash)
                ->where('checked_source_hash', (string) $source->checked_source_hash)
                ->update($updates);
        }

        return $source->fresh(['product']);
    }

    /**
     * Prefer the latest feed value only when that value changed after the last
     * successful detail inspection. Otherwise keep the existing enrichment.
     *
     * @return mixed
     */
    private function inspectionFallback(ZnanjeImportProduct $source, string $field, $existing)
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

    private function feedChangedSinceInspection(ZnanjeImportProduct $source, string $field): bool
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

    public function import(ZnanjeImportProduct $source, ?int $additionalCategoryId = null): array
    {
        // The public listing does not contain every mutable field. Always
        // re-read the detail page before an import so stale availability,
        // price, ISBN or description can never be persisted.
        $source = $this->inspect($source, true);
        if ($source->check_status === 'conflict') {
            throw new RuntimeException($source->check_message ?: 'Artikl ima konflikt u Zuzi katalogu.');
        }
        if (! $source->product_id
            && $source->check_status === 'new'
            && ! $this->isAvailable($source)) {
            throw new RuntimeException(
                'Znanje artikl više nije dostupan za narudžbu. Osvježite feed prije uvoza.'
            );
        }

        $settings = $this->settings->all();
        $inspectedHash = (string) $source->source_hash;
        if ($source->product_id) {
            return $this->handleExisting(
                (int) $source->id,
                (int) $source->product_id,
                $inspectedHash,
                $settings,
                $additionalCategoryId
            );
        }
        if ($source->check_status !== 'new') {
            throw new RuntimeException('Artikl nije sigurno potvrđen kao nov.');
        }

        try {
            $importResult = DB::transaction(function () use (
                $source,
                $settings,
                $additionalCategoryId,
                $inspectedHash
            ) {
                $locked = ZnanjeImportProduct::query()->lockForUpdate()->findOrFail($source->id);
                $this->assertLockedImportSnapshot($locked, $inspectedHash);

                if ($locked->product_id) {
                    $alreadyImported = Product::query()->find($locked->product_id);
                    if (! $alreadyImported) {
                        throw $this->sourceChangedDuringImport();
                    }

                    return [
                        'created' => false,
                        'product' => $alreadyImported,
                    ];
                }

                $publisherMapping = $this->resolvePublisherMapping($locked, $settings);
                $mappedCategoryIds = $this->mappedCategoryIds($locked, $settings);
                $this->validateImportSettings(
                    $settings,
                    $publisherMapping,
                    $mappedCategoryIds,
                    $additionalCategoryId
                );
                $description = trim((string) $locked->description);
                $price = $this->priceCalculator->calculate(
                    $locked->price_eur,
                    $settings['markup_percent']
                );
                $special = $locked->sale_price_eur
                    ? $this->priceCalculator->calculate(
                        $locked->sale_price_eur,
                        $settings['markup_percent']
                    )
                    : null;
                if ($price <= 0) {
                    throw new RuntimeException('Izračunata EUR cijena mora biti veća od nule.');
                }

                $matches = $this->findExistingProducts(
                    $locked->isbn,
                    $locked->ean,
                    $locked->name,
                    $locked->author,
                    true
                );
                if ($matches->count() > 0) {
                    if ($matches->count() > 1) {
                        throw new ZnanjeImportConflictDetected($this->conflictMessage($matches));
                    }

                    $existing = $matches->first();
                    $locked->update([
                        'product_id' => $existing->id,
                        'check_status' => 'matched',
                        'check_message' => 'Postojeći Zuzi artikl pronađen neposredno prije spremanja.',
                    ]);

                    return [
                        'created' => false,
                        'product' => $existing,
                    ];
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
                            throw new ZnanjeImportConflictDetected(
                                $this->conflictMessage($serializedMatches)
                            );
                        }

                        $existing = $serializedMatches->first();
                        $locked->update([
                            'product_id' => $existing->id,
                            'check_status' => 'matched',
                            'check_message' => 'Postojeći Zuzi artikl pronađen neposredno prije spremanja.',
                        ]);

                        return [
                            'created' => false,
                            'product' => $existing,
                        ];
                    }

                    $productName = ImportedProductName::format($locked->author, $locked->name);
                    $request = Request::create('/admin/catalog/znanje-import', 'POST', [
                        'name' => $productName,
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
                        'author_id' => $this->authorResolver->resolveName($locked->author),
                        'publisher_id' => $publisherMapping['publisher_id'],
                        'meta_title' => $productName,
                        'meta_description' => Str::limit($description, 250, ''),
                        'pages' => $locked->pages,
                        'dimensions' => $locked->format,
                        'origin' => $locked->origin,
                        'letter' => $locked->letter ?: 'Latinica',
                        'language' => $locked->language,
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

                    // Product creation and its source association must commit
                    // together. Otherwise a process crash between these two
                    // writes leaves an orphan product that the next import can
                    // no longer identify reliably.
                    $locked->update([
                        'product_id' => $created->id,
                        'check_status' => 'matched',
                        'check_message' => 'Novi Zuzi artikl uspješno je uvezen.',
                        'imported_hash' => (string) $locked->source_hash,
                        'imported_at' => now(),
                    ]);

                    return [
                        'created' => true,
                        'product' => $created,
                        'source' => clone $locked,
                    ];
                });
            }, 3);
        } catch (ZnanjeImportConflictDetected $exception) {
            if (! $this->persistImportConflict(
                (int) $source->id,
                $inspectedHash,
                $exception->getMessage()
            )) {
                throw $this->sourceChangedDuringImport($exception);
            }

            throw new RuntimeException($exception->getMessage(), 0, $exception);
        }

        /** @var Product $product */
        $product = $importResult['product'];
        if (! $importResult['created']) {
            return $this->handleExisting(
                (int) $source->id,
                (int) $product->id,
                $inspectedHash,
                $settings,
                $additionalCategoryId
            );
        }

        /** @var ZnanjeImportProduct $imageSource */
        $imageSource = $importResult['source'];
        $imageWarning = $this->storeImages($product, $imageSource);
        if ($imageWarning !== '') {
            $this->updateLinkedSourceMessage(
                (int) $source->id,
                (int) $product->id,
                $inspectedHash,
                'Novi Zuzi artikl uspješno je uvezen. ' . $imageWarning
            );
        }

        return [
            'action' => 'created',
            'message' => 'Artikl je uvezen u Zuzi.' . ($imageWarning ? ' ' . $imageWarning : ''),
            'product_id' => (int) $product->id,
        ];
    }

    private function assertLockedImportSnapshot(
        ZnanjeImportProduct $locked,
        string $inspectedHash
    ): void
    {
        if (! $locked->is_current
            || ! hash_equals($inspectedHash, (string) $locked->source_hash)
            || ! hash_equals((string) $locked->source_hash, (string) $locked->checked_source_hash)) {
            throw $this->sourceChangedDuringImport();
        }

        if ($locked->product_id) {
            return;
        }
        if ($locked->check_status === 'conflict') {
            throw new ZnanjeImportConflictDetected(
                $locked->check_message ?: 'Artikl ima konflikt u Zuzi katalogu.'
            );
        }
        if ($locked->check_status !== 'new' || ! $this->isAvailable($locked)) {
            throw $this->sourceChangedDuringImport();
        }
    }

    private function conflictMessage($matches): string
    {
        return 'ISBN, EAN ili kombinacija naziva i autora odgovara na više Zuzi artikala: '
            . $matches->pluck('id')->implode(', ') . '.';
    }

    private function persistImportConflict(
        int $sourceId,
        string $expectedHash,
        string $message
    ): bool
    {
        return DB::transaction(function () use ($sourceId, $expectedHash, $message) {
            $target = ZnanjeImportProduct::query()
                ->whereKey($sourceId)
                ->where('is_current', true)
                ->where('source_hash', $expectedHash)
                ->where('checked_source_hash', $expectedHash)
                ->whereNull('product_id')
                ->lockForUpdate()
                ->first();
            if (! $target) {
                return false;
            }

            $target->update([
                'check_status' => 'conflict',
                'check_message' => $message,
                'checked_at' => now(),
            ]);

            return true;
        }, 3);
    }

    private function sourceChangedDuringImport(?\Throwable $previous = null): ZnanjeRetryableException
    {
        return new ZnanjeRetryableException(
            'Znanje feed promijenio se tijekom provjere ili uvoza. Ponovite postupak.',
            503,
            1,
            $previous
        );
    }

    private function handleExisting(
        int $sourceId,
        int $expectedProductId,
        string $inspectedHash,
        array $settings,
        ?int $additionalCategoryId
    ): array {
        $outcome = DB::transaction(function () use (
            $sourceId,
            $expectedProductId,
            $inspectedHash,
            $settings,
            $additionalCategoryId
        ) {
            $locked = ZnanjeImportProduct::query()->lockForUpdate()->findOrFail($sourceId);
            $this->assertLockedImportSnapshot($locked, $inspectedHash);
            if ((int) $locked->product_id !== $expectedProductId
                || $locked->check_status !== 'matched') {
                throw $this->sourceChangedDuringImport();
            }

            $product = Product::query()->lockForUpdate()->find($expectedProductId);
            if (! $product) {
                $locked->update(['product_id' => null, 'check_status' => 'pending']);

                return ['missing' => true];
            }

            $publisherMapping = $this->resolvePublisherMapping($locked, $settings);
            $mappedCategoryIds = $this->mappedCategoryIds($locked, $settings);
            $this->validateImportSettings(
                $settings,
                $publisherMapping,
                $mappedCategoryIds,
                $additionalCategoryId
            );
            $categoriesAdded = $this->ensureCategories(
                $product,
                $settings,
                $publisherMapping,
                $mappedCategoryIds,
                $additionalCategoryId
            );
            // Category synchronization can assign an action and special lock.
            // Refresh before deciding whether the supplier sale price may be
            // written, while the product row is still locked by this import.
            $product->refresh();
            $categoryMessage = $categoriesAdded ? ' Dodane su odabrane kategorije.' : '';
            $restoreImage = (bool) $locked->imported_at
                && ! $product->image
                && (bool) $locked->image_url;

            if ($settings['existing_action'] === 'price_stock') {
                $price = $this->priceCalculator->calculate(
                    $locked->price_eur,
                    $settings['markup_percent']
                );
                $special = $locked->sale_price_eur
                    ? $this->priceCalculator->calculate(
                        $locked->sale_price_eur,
                        $settings['markup_percent']
                    )
                    : null;
                $updates = [
                    'price' => $price,
                    'quantity' => $this->quantity($locked, $settings),
                ];
                // A manually/action-locked special belongs to the catalogue,
                // not to the supplier feed. Never clobber it during a stock
                // refresh. Unlocked category actions are recalculated below.
                if ((int) $product->special_lock !== 1) {
                    $updates['special'] = $special && $special < $price ? $special : null;
                }
                $product->update($updates);
                if ((int) $product->special_lock !== 1) {
                    Action::syncCategoryActionForProduct((int) $product->id);
                    $product->refresh();
                }

                $message = 'Postojećem artiklu ažurirane su cijena i količina.'
                    . $categoryMessage;
                $locked->update([
                    'imported_hash' => (string) $locked->source_hash,
                    'imported_at' => now(),
                    'check_message' => $message,
                ]);

                return [
                    'missing' => false,
                    'action' => 'updated',
                    'message' => $message,
                    'product' => $product,
                    'source' => clone $locked,
                    'restore_image' => $restoreImage,
                ];
            }

            $message = 'Artikl već postoji u Zuzi katalogu i preskočen je.'
                . $categoryMessage;
            $locked->update(['check_message' => $message]);

            return [
                'missing' => false,
                'action' => 'skipped',
                'message' => $message,
                'product' => $product,
                'source' => clone $locked,
                'restore_image' => $restoreImage,
            ];
        }, 3);

        if ($outcome['missing']) {
            throw new RuntimeException('Povezani Zuzi artikl više ne postoji. Ponovite provjeru.');
        }

        $imageMessage = '';
        if ($outcome['restore_image']) {
            $warning = $this->storeImages($outcome['product'], $outcome['source']);
            $imageMessage = $warning ? ' ' . $warning : ' Slika je uspješno ponovno uvezena.';
        }
        $message = $outcome['message'] . $imageMessage;
        if ($imageMessage !== '') {
            $this->updateLinkedSourceMessage(
                $sourceId,
                $expectedProductId,
                $inspectedHash,
                $message
            );
        }

        return [
            'action' => $outcome['action'],
            'message' => $message,
            'product_id' => (int) $outcome['product']->id,
        ];
    }

    private function updateLinkedSourceMessage(
        int $sourceId,
        int $productId,
        string $expectedHash,
        string $message
    ): void {
        ZnanjeImportProduct::query()
            ->whereKey($sourceId)
            ->where('is_current', true)
            ->where('source_hash', $expectedHash)
            ->where('checked_source_hash', $expectedHash)
            ->where('product_id', $productId)
            ->update(['check_message' => $message]);
    }

    /**
     * ISBN/EAN identifikatori su autoritativni. Kombinacija normaliziranog
     * naziva i prvog autora koristi se samo ako nijedan identifikator ne
     * pronađe artikl, čime se izbjegavaju lažni konflikti različitih izdanja.
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

        $name = $this->normalizeComparableText((string) $name);
        // Inspection stores one complete canonical author name. Splitting it
        // again here would turn a valid surname-first name such as
        // "Bataille, Josephine" into the incomplete author "Bataille".
        $author = $this->normalizeComparableText($this->canonicalAuthorName((string) $author));
        if ($name === '' || $author === '') {
            return collect();
        }

        $query = Product::query()
            ->with('author:id,title,normalized_title')
            ->whereHas('author', function ($authorQuery) use ($author) {
                $authorQuery->where(function ($query) use ($author) {
                    $query
                        ->where('authors.normalized_title', $author)
                        ->orWhereRaw('LOWER(TRIM(authors.title)) = ?', [$author]);
                });
            });
        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->get($this->matchColumns())
            ->filter(function (Product $product) use ($name, $author) {
                $productName = ImportedProductName::withoutAuthor(
                    (string) optional($product->author)->title,
                    (string) $product->name
                );

                return $this->normalizeComparableText($productName) === $name
                    && $this->normalizeComparableText((string) optional($product->author)->title) === $author;
            })
            ->unique('id')
            ->values();
    }

    private function matchColumns(): array
    {
        return ['id', 'author_id', 'name', 'sku', 'itemid', 'isbn', 'ean', 'price', 'quantity'];
    }

    private function resolvePublisherMapping(ZnanjeImportProduct $source, array $settings): array
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

    private function mappedCategoryIds(ZnanjeImportProduct $source, array $settings): array
    {
        $map = (array) ($settings['category_map'] ?? []);
        $normalizedMap = [];
        foreach ($map as $category => $categoryId) {
            $normalizedMap[mb_strtolower(trim((string) $category))] = (int) $categoryId;
        }

        $ids = [];
        $root = trim((string) $source->source_category);
        $normalizedRoot = mb_strtolower($root);
        if ($normalizedRoot !== '' && ($normalizedMap[$normalizedRoot] ?? 0) > 0) {
            $ids[] = $normalizedMap[$normalizedRoot];
        }

        $sourceCategories = array_values(array_unique(array_filter(array_map(
            fn ($category) => trim((string) $category),
            array_merge((array) $source->source_categories, (array) $source->source_genres)
        ))));
        foreach ($sourceCategories as $category) {
            if ($category === '' || mb_strtolower($category) === $normalizedRoot) {
                continue;
            }

            $flatKey = mb_strtolower($category);
            $compositeKey = $normalizedRoot !== ''
                ? mb_strtolower($root . ' › ' . $category)
                : '';
            // A path-specific mapping wins. The flat key remains a compatibility
            // fallback for mappings saved before Znanje exposed both root trees.
            $categoryId = ($compositeKey !== '' ? ($normalizedMap[$compositeKey] ?? 0) : 0)
                ?: ($normalizedMap[$flatKey] ?? 0);
            if ($categoryId > 0) {
                $ids[] = $categoryId;
            }
        }

        return array_values(array_unique($ids));
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
            throw new RuntimeException('Prije importa odaberite valjanog nakladnika Znanje.');
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

    private function quantity(ZnanjeImportProduct $source, array $settings): int
    {
        return $this->isAvailable($source)
            ? max(0, (int) $settings['default_quantity'])
            : 0;
    }

    private function isAvailable(ZnanjeImportProduct $source): bool
    {
        $availability = mb_strtolower(trim((string) $source->availability));
        $unavailable = $availability === ''
            || str_contains($availability, 'nedostup')
            || str_contains($availability, 'rasprodan')
            || str_contains($availability, 'out of stock')
            || str_contains($availability, 'out_of_stock')
            || str_contains($availability, 'outofstock');
        $available = ! $unavailable && (in_array($availability, [
            'in stock', 'in_stock', 'instock', 'available', 'onbackorder',
            'dostupno', 'na zalihi', 'raspoloživo',
        ], true)
            || str_contains($availability, 'dostup')
            || str_contains($availability, 'zalih')
            || str_contains($availability, 'raspolož'));

        return $available;
    }

    private function descriptionHtml(string $description): string
    {
        $paragraphs = preg_split('/\n{2,}/u', trim($description)) ?: [];

        return implode('', array_map(function ($paragraph) {
            return '<p>' . e(trim($paragraph)) . '</p>';
        }, array_filter($paragraphs, 'strlen')));
    }

    private function storeImages(Product $product, ZnanjeImportProduct $source): string
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
                        . '-znanje-' . ($index + 1);
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
        $maximum = max(1024, (int) config('znanje_import.max_image_bytes', 15 * 1024 * 1024));
        $temporaryPath = tempnam(sys_get_temp_dir(), 'zuzi-znanje-image-');
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
                ->withHeaders(['User-Agent' => 'Zuzi-Znanje-Importer/1.0'])
                ->get($url);
            clearstatcache(true, $temporaryPath);
            $bytes = filesize($temporaryPath);
            if (! $response->successful() || $bytes === false || $bytes < 1 || $bytes > $maximum) {
                throw new RuntimeException('Slika nije dostupna ili je prevelika.');
            }

            $dimensions = @getimagesize($temporaryPath);
            $maximumPixels = max(1000000, (int) config('znanje_import.max_image_pixels', 40000000));
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
        $allowed = array_map('strtolower', (array) config('znanje_import.allowed_image_hosts', []));
        if (($parts['scheme'] ?? '') !== 'https'
            || ! in_array(strtolower($parts['host'] ?? ''), $allowed, true)) {
            throw new RuntimeException('Domena slike nije dopuštena.');
        }
    }

    private function assertDetailIdentity(ZnanjeImportProduct $source, array $details): void
    {
        $remoteId = (int) ($details['remote_product_id'] ?? 0);
        if ($remoteId > 0 && $remoteId !== (int) $source->remote_product_id) {
            throw new ZnanjeTerminalException(
                'Znanje stranica vratila je podatke drugog artikla. Provjera je zaustavljena.'
            );
        }

        $externalId = trim((string) ($details['external_id'] ?? ''));
        if ($externalId !== '' && ! hash_equals((string) $source->external_id, $externalId)) {
            throw new ZnanjeTerminalException(
                'Znanje identitet artikla ne odgovara zapisu iz feeda.'
            );
        }

        $detailUrl = trim((string) ($details['source_url'] ?? ''));
        if ($detailUrl !== ''
            && ! hash_equals(
                $this->normalizedProductUrl((string) $source->source_url),
                $this->normalizedProductUrl($detailUrl)
            )) {
            throw new ZnanjeTerminalException(
                'Znanje poveznica artikla ne odgovara zapisu iz feeda.'
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

    private function firstDetailAuthor(array $details): ?string
    {
        $authors = $this->stringList($details['authors'] ?? []);
        if ($authors !== []) {
            return $this->canonicalAuthorName($authors[0]);
        }

        $author = trim((string) ($details['author'] ?? ''));

        return $author !== '' ? $this->canonicalAuthorName($author) : null;
    }

    /**
     * Znanje exposes individual authors separately. A single author written
     * as "Surname, Given name" is normalized to the catalogue's natural
     * "Given name Surname" form before matching or persistence.
     */
    private function canonicalAuthorName(string $author): string
    {
        $author = AuthorResolver::normalizeName($author);
        $parts = array_map('trim', explode(',', $author));
        if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
            // Znanje's surname-first records use a single surname before the
            // comma (for example "Bataille, Josephine"). Older cached listing
            // rows may instead contain multiple complete authors separated by
            // a comma; keep only that already-natural first complete author.
            if (preg_match('/\s/u', $parts[0]) === 1) {
                return AuthorResolver::normalizeName($parts[0]);
            }

            return AuthorResolver::normalizeName($parts[1] . ' ' . $parts[0]);
        }

        return $author;
    }

    private function normalizeComparableText(string $value): string
    {
        return mb_strtolower(AuthorResolver::normalizeName($value), 'UTF-8');
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

    private function positiveMoney($value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }
        $number = (float) $value;

        return is_finite($number) && $number > 0 ? $number : null;
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

final class ZnanjeImportConflictDetected extends RuntimeException
{
}
