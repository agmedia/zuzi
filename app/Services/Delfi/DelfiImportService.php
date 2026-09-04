<?php

namespace App\Services\Delfi;

use App\Models\Back\Catalog\Category;
use App\Models\Back\Catalog\DelfiImportProduct;
use App\Models\Back\Catalog\Product\Product;
use App\Models\Back\Catalog\Product\ProductImage;
use App\Models\Back\Catalog\Publisher;
use App\Models\Back\Marketing\Action;
use App\Services\Catalog\AuthorResolver;
use App\Services\Catalog\ImportedProductName;
use App\Services\ProductIdentifierAllocator;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use RuntimeException;

class DelfiImportService
{
    private DelfiProductApiClient $api;
    private DelfiProductDetailParser $parser;
    private DelfiProductListApiClient $listApi;
    private DelfiProductListParser $listParser;
    private DelfiTranslationService $translator;
    private DelfiImportSettings $settings;
    private DelfiPriceCalculator $priceCalculator;
    private ProductIdentifierAllocator $identifierAllocator;
    private AuthorResolver $authorResolver;

    public function __construct(
        DelfiProductApiClient $api,
        DelfiProductDetailParser $parser,
        DelfiProductListApiClient $listApi,
        DelfiProductListParser $listParser,
        DelfiTranslationService $translator,
        DelfiImportSettings $settings,
        DelfiPriceCalculator $priceCalculator,
        ProductIdentifierAllocator $identifierAllocator,
        AuthorResolver $authorResolver
    ) {
        $this->api = $api;
        $this->parser = $parser;
        $this->listApi = $listApi;
        $this->listParser = $listParser;
        $this->translator = $translator;
        $this->settings = $settings;
        $this->priceCalculator = $priceCalculator;
        $this->identifierAllocator = $identifierAllocator;
        $this->authorResolver = $authorResolver;
    }

    /**
     * Inspect one Delfi product-list page without making per-product overview calls.
     *
     * Product candidates are prefetched for the whole page so a page costs a
     * constant number of SELECT queries regardless of whether it contains one
     * or one hundred books.
     */
    public function inspectProductListPage(string $feedToken, int $skip = 0, int $limit = 100): array
    {
        $payload = $this->listApi->fetchPage($skip, $limit);
        $page = $this->listParser->parsePage($payload, $skip, $limit);
        $items = collect((array) ($page['items'] ?? []));
        $remoteIds = $items->pluck('remote_product_id')->filter()->map(fn ($id) => (int) $id)->unique()->values();
        $externalIds = $items->pluck('external_id')->filter()->map(fn ($id) => (string) $id)->unique()->values();

        $sources = collect();
        if ($remoteIds->isNotEmpty() || $externalIds->isNotEmpty()) {
            $sources = DelfiImportProduct::query()
                ->where('is_current', true)
                ->where('feed_token', $feedToken)
                ->whereIn('source_category', DelfiProductListApiClient::CATEGORIES)
                ->where(function ($query) {
                    $query->whereNull('checked_source_hash')
                        ->orWhereColumn('checked_source_hash', '!=', 'source_hash');
                })
                ->where(function ($query) use ($remoteIds, $externalIds) {
                    if ($remoteIds->isNotEmpty()) {
                        $query->whereIn('remote_product_id', $remoteIds);
                    }
                    if ($externalIds->isNotEmpty()) {
                        $method = $remoteIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                        $query->{$method}('external_id', $externalIds);
                    }
                })
                ->get();
        }

        $byRemoteId = $sources->whereNotNull('remote_product_id')->keyBy(fn ($source) => (int) $source->remote_product_id);
        $byExternalId = $sources->keyBy(fn ($source) => (string) $source->external_id);
        $records = [];
        $seenSourceIds = [];
        foreach ($items as $item) {
            $remoteId = (int) ($item['remote_product_id'] ?? 0);
            $externalId = trim((string) ($item['external_id'] ?? ''));
            $source = $remoteId > 0 ? $byRemoteId->get($remoteId) : null;
            if (! $source && $externalId !== '') {
                $source = $byExternalId->get($externalId);
            }
            if (! $source || isset($seenSourceIds[$source->id])) {
                continue;
            }

            $seenSourceIds[$source->id] = true;
            $records[] = ['source' => $source, 'details' => (array) $item];
        }

        $matches = $this->prefetchProductMatches($records);
        [$succeeded, $failed, $failures] = DB::transaction(function () use ($records, $matches) {
            $succeeded = 0;
            $failed = 0;
            $failures = [];
            foreach ($records as $record) {
                /** @var DelfiImportProduct $source */
                $source = $record['source'];
                $details = $record['details'];

                try {
                    if ($this->applyProductListInspection(
                        $source,
                        $details,
                        $matches[(int) $source->id] ?? collect()
                    )) {
                        $succeeded++;
                    }
                } catch (DelfiTerminalException $exception) {
                    if ($this->conditionalInspectionUpdate($source, [
                        'detail_payload' => null,
                        'checked_source_hash' => $source->source_hash,
                        'check_status' => 'error',
                        'check_message' => $exception->getMessage(),
                        'checked_at' => now(),
                    ])) {
                        $failed++;
                        $failures[] = [
                            'id' => (int) $source->id,
                            'name' => $source->name,
                            'message' => $exception->getMessage(),
                        ];
                    }
                }
            }

            return [$succeeded, $failed, $failures];
        }, 3);

        Cache::forget('delfi-import-source-genre-counts-by-category');

        return $page + [
            'processed' => $succeeded + $failed,
            'succeeded' => $succeeded,
            'failed' => $failed,
            'ignored' => max(0, $items->count() - $succeeded - $failed),
            'failures' => $failures,
        ];
    }

    public function inspect(DelfiImportProduct $source, bool $force = false): DelfiImportProduct
    {
        if (! $source->is_current) {
            throw new DelfiTerminalException('Artikl više nije prisutan u aktualnom Delfi feedu.');
        }

        if (! $force && $source->checked_source_hash === $source->source_hash
            && in_array($source->check_status, ['new', 'matched', 'conflict'], true)) {
            if ($source->check_status === 'new') {
                return $this->recheckCachedNew($source);
            }

            return $source->fresh(['product']);
        }

        try {
            if (! $source->remote_product_id) {
                throw new DelfiTerminalException('Delfi artiklu nedostaje numerički ID potreban za provjeru.');
            }

            $payload = $this->api->fetch($source->remote_product_id);
            $details = $this->parser->parse($payload);
            if ((int) ($details['remote_product_id'] ?? 0) !== (int) $source->remote_product_id) {
                throw new DelfiTerminalException('Delfi API vratio je podatke drugog artikla. Provjera je zaustavljena.');
            }
            $detailExternalId = trim((string) ($details['external_id'] ?? ''));
            if ($detailExternalId !== ''
                && ! hash_equals((string) $source->external_id, $detailExternalId)) {
                throw new DelfiTerminalException('Delfi API identitet artikla ne odgovara zapisu iz feeda.');
            }
            $author = $details['author'] ?: $source->author;
            $matches = $this->findExistingProducts(
                $details['isbn'] ?? null,
                $details['ean'] ?? null,
                $source->name,
                $author
            );
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
            } elseif (empty($details['isbn']) && empty($details['ean'])) {
                throw new DelfiTerminalException(
                    'ISBN/EAN nije pronađen u Delfi podacima, a naziv i autor ne odgovaraju postojećem Zuzi artiklu.'
                );
            }

            $images = array_values(array_unique(array_filter(array_merge(
                (array) ($details['images'] ?? []),
                [$source->image_url],
                (array) $source->additional_image_urls
            ))));
            $description = trim((string) ($details['description'] ?? ''));

            $source->update([
                'product_id' => $productId,
                'description' => $description !== '' ? $description : $source->description,
                'image_url' => $images[0] ?? $source->image_url,
                'additional_image_urls' => array_slice($images, 1),
                'source_publisher' => $details['publisher'] ?: $source->source_publisher,
                'isbn' => $details['isbn'],
                'ean' => $details['ean'],
                'nav_id' => $details['nav_id'],
                'author' => $author ?: null,
                'source_genres' => $details['source_genres'] ?: [],
                'genre' => $details['genre'] ?: null,
                'format' => $details['format'] ?: null,
                'pages' => $details['pages'],
                'letter' => $details['letter'] ?: null,
                'binding' => $details['binding'] ?: null,
                'publication_year' => $details['publication_year'],
                'language' => $details['language'] ?: null,
                'origin' => $details['origin'] ?: null,
                'detail_payload' => $details,
                'checked_source_hash' => $source->source_hash,
                'check_status' => $status,
                'check_message' => $message,
                'checked_at' => now(),
            ]);
            Cache::forget('delfi-import-source-genre-counts-by-category');

            return $source->fresh(['product']);
        } catch (DelfiRetryableException $exception) {
            $updates = [
                'check_message' => $exception->getMessage(),
            ];
            if (! hash_equals((string) $source->source_hash, (string) $source->checked_source_hash)) {
                $updates['check_status'] = 'pending';
            }
            $source->update($updates);

            throw $exception;
        } catch (DelfiTerminalException $exception) {
            $source->update([
                'checked_source_hash' => $source->source_hash,
                'check_status' => 'error',
                'check_message' => $exception->getMessage(),
                'checked_at' => now(),
            ]);

            throw $exception;
        } catch (\Throwable $exception) {
            // Program, database and other unexpected errors must stay pending.
            // Otherwise "Provjeri sve" could mark the whole feed as terminal.
            throw $exception;
        }
    }

    /**
     * Recheck a stable cached-new row against the local catalog without calling Delfi.
     */
    public function recheckCachedNew(DelfiImportProduct $source): DelfiImportProduct
    {
        if (! $source->is_current
            || $source->product_id
            || $source->check_status !== 'new'
            || $source->checked_source_hash !== $source->source_hash) {
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

    public function import(DelfiImportProduct $source, ?int $additionalCategoryId = null): array
    {
        // Bulk inspection intentionally avoids the per-product overview API.
        // Fetch the complete detail exactly once when that book is actually imported.
        $source = $this->inspect($source, empty($source->detail_payload));
        if ($source->check_status === 'conflict') {
            throw new RuntimeException($source->check_message ?: 'Artikl ima konflikt u Zuzi katalogu.');
        }

        $settings = $this->settings->all();
        $mapping = $this->resolvePublisherMapping($source, $settings);
        $genreCategoryIds = $this->genreCategoryIds($source, $settings);
        $this->validateImportSettings($settings, $mapping, $genreCategoryIds, $additionalCategoryId);

        if ($source->product_id) {
            return $this->handleExisting(
                $source,
                $settings,
                $mapping,
                $genreCategoryIds,
                $additionalCategoryId
            );
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
            ? $this->priceCalculator->convert(
                $source->sale_price_rsd,
                $settings['exchange_rate'],
                $settings['markup_percent']
            )
            : null;
        if ($price <= 0) {
            throw new RuntimeException('Izračunata EUR cijena mora biti veća od nule.');
        }

        $product = DB::transaction(function () use (
            $source,
            $settings,
            $mapping,
            $genreCategoryIds,
            $additionalCategoryId,
            $description,
            $price,
            $special
        ) {
            $locked = DelfiImportProduct::query()->lockForUpdate()->findOrFail($source->id);
            $matches = $this->findExistingProducts($locked->isbn, $locked->ean, $locked->name, $locked->author);
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
                $mapping,
                $genreCategoryIds,
                $additionalCategoryId,
                $description,
                $price,
                $special
            ) {
                // Identifier allocation serializes creation. Re-check with a
                // locking read inside that lock so two source rows for the same
                // ISBN/EAN cannot both create a Zuzi product.
                $serializedMatches = $this->findExistingProducts(
                    $locked->isbn,
                    $locked->ean,
                    $locked->name,
                    $locked->author,
                    true
                );
                if ($serializedMatches->count() > 1) {
                    $locked->update([
                        'check_status' => 'conflict',
                        'check_message' => 'ISBN, EAN ili kombinacija naziva i autora odgovara na više Zuzi artikala: '
                            . $serializedMatches->pluck('id')->implode(', ') . '.',
                    ]);
                    throw new RuntimeException($locked->check_message);
                }
                if ($serializedMatches->count() === 1) {
                    $existing = $serializedMatches->first();
                    $locked->update([
                        'product_id' => $existing->id,
                        'check_status' => 'matched',
                        'check_message' => 'Postojeći Zuzi artikl pronađen neposredno prije spremanja.',
                    ]);

                    return $existing;
                }

                $authorId = $this->authorResolver->resolve($locked->author);
                $productName = ImportedProductName::format($locked->author, $locked->name);
                $request = Request::create('/admin/delfi-import', 'POST', [
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
                        $mapping,
                        $genreCategoryIds,
                        $additionalCategoryId
                    ),
                    'author_id' => $authorId,
                    'publisher_id' => $mapping['publisher_id'],
                    'meta_title' => $productName,
                    'meta_description' => Str::limit($description, 250, ''),
                    'pages' => $locked->pages,
                    'dimensions' => $locked->format,
                    'origin' => $locked->origin,
                    'letter' => $locked->letter,
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

                return $created;
            });
        }, 3);

        $freshSource = $source->fresh();
        if ($freshSource->product_id && (int) $freshSource->product_id === (int) $product->id) {
            return $this->handleExisting(
                $freshSource,
                $settings,
                $mapping,
                $genreCategoryIds,
                $additionalCategoryId
            );
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
        DelfiImportProduct $source,
        array $settings,
        array $mapping,
        array $genreCategoryIds,
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
            $mapping,
            $genreCategoryIds,
            $additionalCategoryId
        );
        $categoryMessage = $categoriesAdded ? ' Dodane su odabrane kategorije.' : '';
        $imageMessage = '';
        if ($source->imported_at && ! $product->image && $source->image_url) {
            $imageWarning = $this->storeImages($product, $source);
            $imageMessage = $imageWarning !== ''
                ? ' ' . $imageWarning
                : ' Slika je uspješno ponovno uvezena.';
        }

        if ($settings['existing_action'] === 'price_stock') {
            $price = $this->priceCalculator->convert(
                $source->price_rsd,
                $settings['exchange_rate'],
                $settings['markup_percent']
            );
            $special = $source->sale_price_rsd
                ? $this->priceCalculator->convert(
                    $source->sale_price_rsd,
                    $settings['exchange_rate'],
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
                'check_message' => 'Postojećem artiklu ažurirane su cijena i količina.' . $categoryMessage . $imageMessage,
            ]);

            return [
                'action' => 'updated',
                'message' => 'Postojećem artiklu ažurirane su cijena i količina.' . $categoryMessage . $imageMessage,
                'product_id' => (int) $product->id,
            ];
        }

        $source->update([
            'check_message' => 'Artikl već postoji u Zuzi katalogu i preskočen je.' . $categoryMessage . $imageMessage,
        ]);

        return [
            'action' => 'skipped',
            'message' => 'Artikl već postoji u Zuzi katalogu i preskočen je.' . $categoryMessage . $imageMessage,
            'product_id' => (int) $product->id,
        ];
    }

    private function importDescription(DelfiImportProduct $source, array $settings): array
    {
        $description = trim((string) $source->description);
        if (! ($settings['translate_descriptions'] ?? false) || $description === '') {
            return ['description' => $description, 'warning' => ''];
        }

        $hash = hash('sha256', $description);
        if ($source->translated_description
            && hash_equals($hash, (string) $source->translation_source_hash)) {
            return ['description' => $source->translated_description, 'warning' => ''];
        }

        try {
            $translated = $this->translator->translateDescription($description);
        } catch (RuntimeException | ConnectionException $exception) {
            logger()->warning('Delfi opis nije preveden; koristi se izvorni opis.', [
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

    private function findExistingProducts(
        ?string $isbn,
        ?string $ean,
        ?string $name,
        ?string $author,
        bool $lockForUpdate = false
    )
    {
        $identifiers = collect([$isbn, $ean])
            ->map(fn ($value) => strtoupper(preg_replace('/[^0-9X]/i', '', (string) $value) ?? ''))
            ->filter()
            ->unique()
            ->values();
        $exactName = trim((string) $name);
        $exactAuthor = trim(explode(',', (string) $author)[0]);
        $catalogNames = ImportedProductName::variants($exactAuthor, $exactName);
        $hasTitleAuthor = $exactName !== '' && $exactAuthor !== '';
        if ($identifiers->isEmpty() && ! $hasTitleAuthor) {
            return collect();
        }

        $columns = ['id', 'name', 'sku', 'itemid', 'isbn', 'ean', 'price', 'quantity'];
        $matches = collect();

        if ($identifiers->isNotEmpty()) {
            foreach (['isbn', 'ean'] as $identifierColumn) {
                $identifierQuery = Product::query()->whereIn($identifierColumn, $identifiers);
                if ($lockForUpdate) {
                    $identifierQuery->lockForUpdate();
                }
                $matches = $matches->concat($identifierQuery->get($columns));
            }

        }

        if ($hasTitleAuthor) {
            // Both columns use case-insensitive indexed collations in Zuzi. An
            // exact comparison therefore keeps the indexes usable while still
            // matching capitalization differences from Delfi.
            $titleAuthorQuery = Product::query()
                ->whereIn('products.name', $catalogNames)
                ->whereHas('author', function ($authorQuery) use ($exactAuthor) {
                    $authorQuery->where('authors.title', $exactAuthor);
                });
            if ($lockForUpdate) {
                $titleAuthorQuery->lockForUpdate();
            }
            $matches = $matches->concat($titleAuthorQuery->get($columns));
        }

        // Only use the legacy separator-tolerant scan when both indexed
        // identifier lookup and indexed title/author lookup miss.
        if ($matches->isEmpty() && $identifiers->isNotEmpty()) {
            $patterns = $identifiers->map(function (string $identifier) {
                $characters = array_map(
                    fn (string $character) => $character === 'X' ? '[Xx]' : $character,
                    str_split($identifier)
                );

                return '^[^0-9Xx]*' . implode('[^0-9Xx]*', $characters) . '[^0-9Xx]*$';
            });
            $legacyIdentifierQuery = Product::query()->where(function ($query) use ($patterns) {
                foreach ($patterns as $index => $pattern) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}(function ($patternQuery) use ($pattern) {
                        $patternQuery->whereRaw('isbn REGEXP ?', [$pattern])
                            ->orWhereRaw('ean REGEXP ?', [$pattern]);
                    });
                }
            });
            if ($lockForUpdate) {
                $legacyIdentifierQuery->lockForUpdate();
            }
            $matches = $matches->concat($legacyIdentifierQuery->get($columns));
        }

        return $matches->unique('id')->values();
    }

    /**
     * @return array<int, \Illuminate\Support\Collection>
     */
    private function prefetchProductMatches(array $records): array
    {
        if ($records === []) {
            return [];
        }

        $identifiers = collect($records)
            ->flatMap(function (array $record) {
                $details = $record['details'];

                return [
                    $details['isbn'] ?? null,
                    $details['ean'] ?? null,
                ];
            })
            ->map(fn ($value) => $this->normalizeIdentifier($value))
            ->filter()
            ->unique()
            ->values();
        $columns = ['id', 'name', 'sku', 'itemid', 'isbn', 'ean', 'price', 'quantity'];
        $isbnProducts = $identifiers->isEmpty()
            ? collect()
            : Product::query()->whereIn('isbn', $identifiers)->get($columns);
        $eanProducts = $identifiers->isEmpty()
            ? collect()
            : Product::query()->whereIn('ean', $identifiers)->get($columns);

        $isbnMap = $isbnProducts->groupBy(fn ($product) => $this->normalizeIdentifier($product->isbn));
        $eanMap = $eanProducts->groupBy(fn ($product) => $this->normalizeIdentifier($product->ean));
        $titleAuthorPairs = collect($records)
            ->map(function (array $record) {
                $source = $record['source'];
                $details = $record['details'];
                $name = trim((string) $source->name);
                $author = $this->firstAuthor($details['author'] ?? $source->author);

                return compact('name', 'author');
            })
            ->filter(fn (array $pair) => $pair['name'] !== '' && $pair['author'] !== '')
            ->unique(fn (array $pair) => $this->titleAuthorKey($pair['name'], $pair['author']))
            ->values();
        $titleAuthorMap = collect();
        if ($titleAuthorPairs->isNotEmpty()) {
            $titleProducts = Product::query()
                ->join('authors', 'authors.id', '=', 'products.author_id')
                ->whereIn('products.name', $titleAuthorPairs->flatMap(
                    fn (array $pair) => ImportedProductName::variants($pair['author'], $pair['name'])
                )->unique()->values())
                ->whereIn('authors.title', $titleAuthorPairs->pluck('author')->unique()->values())
                ->get([
                    'products.id',
                    'products.name',
                    'products.sku',
                    'products.itemid',
                    'products.isbn',
                    'products.ean',
                    'products.price',
                    'products.quantity',
                    'authors.title as matched_author',
                ]);
            $titleAuthorMap = $titleProducts->groupBy(function ($product) {
                return $this->titleAuthorKey(
                    ImportedProductName::withoutAuthor($product->matched_author, $product->name),
                    $product->matched_author
                );
            });
        }

        $matches = [];
        foreach ($records as $record) {
            /** @var DelfiImportProduct $source */
            $source = $record['source'];
            $details = $record['details'];
            $sourceMatches = collect();
            foreach (array_unique(array_filter([
                $this->normalizeIdentifier($details['isbn'] ?? null),
                $this->normalizeIdentifier($details['ean'] ?? null),
            ])) as $identifier) {
                $sourceMatches = $sourceMatches
                    ->concat($isbnMap->get($identifier, collect()))
                    ->concat($eanMap->get($identifier, collect()));
            }

            $name = trim((string) $source->name);
            $author = $this->firstAuthor($details['author'] ?? $source->author);
            if ($name !== '' && $author !== '') {
                $sourceMatches = $sourceMatches->concat(
                    $titleAuthorMap->get($this->titleAuthorKey($name, $author), collect())
                );
            }

            $matches[(int) $source->id] = $sourceMatches->unique('id')->values();
        }

        return $matches;
    }

    private function applyProductListInspection(
        DelfiImportProduct $source,
        array $details,
        $matches
    ): bool {
        $remoteId = (int) ($details['remote_product_id'] ?? 0);
        if ($remoteId < 1 || $remoteId !== (int) $source->remote_product_id) {
            throw new DelfiTerminalException('Delfi bulk API identitet artikla ne odgovara zapisu iz feeda.');
        }
        $externalId = trim((string) ($details['external_id'] ?? ''));
        if ($externalId !== '' && ! hash_equals((string) $source->external_id, $externalId)) {
            throw new DelfiTerminalException('Delfi bulk API identitet artikla ne odgovara zapisu iz feeda.');
        }
        if (($details['source_category'] ?? null) !== $source->source_category) {
            throw new DelfiTerminalException('Delfi bulk API kategorija artikla ne odgovara zapisu iz feeda.');
        }

        $isbn = $details['isbn'] ?? null;
        $ean = $details['ean'] ?? null;
        $author = trim((string) ($details['author'] ?? $source->author)) ?: null;
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
        } elseif (empty($isbn) && empty($ean)) {
            throw new DelfiTerminalException(
                'ISBN/EAN nije pronađen u Delfi bulk podacima, a naziv i autor ne odgovaraju postojećem Zuzi artiklu.'
            );
        }

        $images = array_values(array_unique(array_filter(array_merge(
            (array) ($details['images'] ?? []),
            [$details['image_url'] ?? null, $source->image_url],
            (array) ($details['additional_image_urls'] ?? []),
            (array) $source->additional_image_urls
        ))));
        return $this->conditionalInspectionUpdate($source, [
            'product_id' => $productId,
            'image_url' => $images[0] ?? $source->image_url,
            'additional_image_urls' => array_slice($images, 1),
            'source_publisher' => $details['source_publisher'] ?? $source->source_publisher,
            'isbn' => $isbn,
            'ean' => $ean,
            'nav_id' => $details['nav_id'] ?? $source->nav_id,
            'author' => $author,
            'source_genres' => $details['source_genres'] ?? ($source->source_genres ?: []),
            'genre' => $details['genre'] ?? $source->genre,
            'format' => $details['format'] ?? $source->format,
            'pages' => $details['pages'] ?? $source->pages,
            'letter' => $details['letter'] ?? $source->letter,
            'binding' => $details['binding'] ?? $source->binding,
            'publication_year' => $details['publication_year'] ?? $source->publication_year,
            'language' => $details['language'] ?? $source->language,
            'origin' => $details['origin'] ?? $source->origin,
            // The list payload is sufficient for matching, but it is not the
            // complete overview used by import. Clear an older detail snapshot
            // so importing a changed feed row must fetch fresh details once.
            'detail_payload' => null,
            'checked_source_hash' => $source->source_hash,
            'check_status' => $status,
            'check_message' => $message,
            'checked_at' => now(),
        ]);
    }

    private function conditionalInspectionUpdate(DelfiImportProduct $source, array $attributes): bool
    {
        $snapshotHash = (string) $source->source_hash;
        $snapshotFeedToken = (string) $source->feed_token;
        $source->forceFill($attributes);
        $serialized = [];
        foreach (array_keys($attributes) as $key) {
            $serialized[$key] = $source->getAttributes()[$key] ?? null;
        }

        return DelfiImportProduct::query()
            ->whereKey($source->id)
            ->where('is_current', true)
            ->where('feed_token', $snapshotFeedToken)
            ->where('source_hash', $snapshotHash)
            ->where(function ($query) {
                $query->whereNull('checked_source_hash')
                    ->orWhereColumn('checked_source_hash', '!=', 'source_hash');
            })
            ->update($serialized) === 1;
    }

    private function normalizeIdentifier($value): string
    {
        return strtoupper(preg_replace('/[^0-9X]/i', '', (string) $value) ?? '');
    }

    private function firstAuthor($value): string
    {
        return trim(explode(',', (string) $value)[0]);
    }

    private function titleAuthorKey($name, $author): string
    {
        return mb_strtolower(trim((string) $name)) . "\0" . mb_strtolower(trim((string) $author));
    }

    private function resolvePublisherMapping(DelfiImportProduct $source, array $settings): array
    {
        $mapping = [
            'publisher_id' => (int) $settings['publisher_id'],
            'publisher_category_id' => (int) $settings['publisher_category_id'],
            'source_matched' => false,
        ];
        $name = trim((string) $source->source_publisher);
        if (! ($settings['map_source_publishers'] ?? true) || $name === '') {
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

    private function genreCategoryIds(DelfiImportProduct $source, array $settings): array
    {
        $map = (array) ($settings['genre_category_map'] ?? []);
        $normalizedMap = [];
        foreach ($map as $genre => $categoryId) {
            $normalizedMap[mb_strtolower(trim((string) $genre))] = (int) $categoryId;
        }

        $ids = [];
        foreach ((array) $source->source_genres as $genre) {
            $categoryId = $normalizedMap[mb_strtolower(trim((string) $genre))] ?? 0;
            if ($categoryId > 0) {
                $ids[] = $categoryId;
            }
        }

        return array_values(array_unique($ids));
    }

    private function quantity(DelfiImportProduct $source, array $settings): int
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

    private function validateImportSettings(
        array $settings,
        array $mapping,
        array $genreCategoryIds,
        ?int $additionalCategoryId
    ): void {
        $parent = Category::query()->find((int) $settings['publisher_parent_category_id']);
        $publisherCategory = Category::query()->find((int) $mapping['publisher_category_id']);
        if (! $parent || (int) $parent->parent_id !== 0) {
            throw new RuntimeException('Prije importa mapirajte obaveznu kategoriju Nakladnici.');
        }
        if (! $publisherCategory || (int) $publisherCategory->parent_id !== (int) $parent->id) {
            throw new RuntimeException('Kategorija nakladnika mora biti podkategorija odabrane kategorije Nakladnici.');
        }
        if ((int) $mapping['publisher_id'] < 1
            || ! Publisher::query()->whereKey($mapping['publisher_id'])->exists()) {
            throw new RuntimeException('Prije importa odaberite valjanog rezervnog nakladnika.');
        }

        $requestedIds = array_values(array_unique(array_filter(array_merge(
            $genreCategoryIds,
            [$additionalCategoryId]
        ))));
        if ($requestedIds !== []
            && Category::query()->whereIn('id', $requestedIds)->count() !== count($requestedIds)) {
            throw new RuntimeException('Jedna od mapiranih ili dodatnih kategorija više ne postoji.');
        }
    }

    private function creationCategoryIds(
        array $mapping,
        array $genreCategoryIds,
        ?int $additionalCategoryId
    ): array {
        return array_values(array_unique(array_filter(array_merge(
            [(int) $mapping['publisher_category_id']],
            $genreCategoryIds,
            [$additionalCategoryId]
        ))));
    }

    private function ensureCategories(
        Product $product,
        array $settings,
        array $mapping,
        array $genreCategoryIds,
        ?int $additionalCategoryId
    ): bool {
        $requestedIds = array_values(array_unique(array_filter(array_merge([
            (int) $settings['publisher_parent_category_id'],
            (int) $mapping['publisher_category_id'],
            $additionalCategoryId,
        ], $genreCategoryIds))));
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

    private function storeImages(Product $product, DelfiImportProduct $source): string
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
                    $base = $product->id . '/' . Str::slug($product->name) . '-delfi-' . ($index + 1);
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
        $maximum = max(1024, (int) config('delfi_import.max_image_bytes', 15 * 1024 * 1024));
        $temporaryPath = tempnam(sys_get_temp_dir(), 'zuzi-delfi-image-');
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
                ->withHeaders(['User-Agent' => 'Zuzi-Delfi-Importer/1.0'])
                ->get($url);
            clearstatcache(true, $temporaryPath);
            $bytes = filesize($temporaryPath);
            if (! $response->successful() || $bytes === false || $bytes < 1 || $bytes > $maximum) {
                throw new RuntimeException('Slika nije dostupna ili je prevelika.');
            }

            $dimensions = @getimagesize($temporaryPath);
            $maximumPixels = max(1000000, (int) config('delfi_import.max_image_pixels', 40000000));
            if (! is_array($dimensions)
                || empty($dimensions[0])
                || empty($dimensions[1])
                || ((int) $dimensions[0] * (int) $dimensions[1]) > $maximumPixels) {
                throw new RuntimeException('Datoteka nije podržana slika ili su joj dimenzije prevelike.');
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
        $allowed = array_map('strtolower', (array) config('delfi_import.allowed_image_hosts', []));
        if (($parts['scheme'] ?? '') !== 'https'
            || ! in_array(strtolower($parts['host'] ?? ''), $allowed, true)) {
            throw new RuntimeException('Domena slike nije dopuštena.');
        }
    }
}
