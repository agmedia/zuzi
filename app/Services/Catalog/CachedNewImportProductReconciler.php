<?php

namespace App\Services\Catalog;

use App\Models\Back\Catalog\DelfiImportProduct;
use App\Models\Back\Catalog\LagunaImportProduct;
use App\Models\Back\Catalog\NovellaImportProduct;
use App\Models\Back\Catalog\ZnanjeImportProduct;
use App\Models\Back\Catalog\Product\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CachedNewImportProductReconciler
{
    /**
     * Reconcile cached "new" rows in one batch against the current Zuzi catalogue.
     */
    public function reconcile(Collection $sources): Collection
    {
        $candidates = $sources->filter(function ($source) {
            return $source->is_current
                && ! $source->product_id
                && $source->check_status === 'new'
                && hash_equals((string) $source->source_hash, (string) $source->checked_source_hash);
        })->values();

        if ($candidates->isEmpty()) {
            return $sources;
        }

        $sourceIdentifiers = $candidates->mapWithKeys(function ($source) {
            $values = [$source->isbn];
            if (! $source instanceof LagunaImportProduct) {
                $values[] = $source->ean;
            }

            return [(int) $source->id => collect($values)
                ->map(fn ($value) => $this->normalizeIdentifier($value))
                ->filter()
                ->unique()
                ->values()];
        });
        $identifiers = $sourceIdentifiers->flatten()->unique()->values();
        $columns = ['id', 'name', 'sku', 'itemid', 'isbn', 'ean', 'price', 'quantity'];
        $identifierProducts = collect();

        if ($identifiers->isNotEmpty()) {
            $identifierProducts = Product::query()
                ->where(function ($query) use ($identifiers) {
                    $query->whereIn('isbn', $identifiers)->orWhereIn('ean', $identifiers);
                })
                ->get($columns);

            $foundIdentifiers = $identifierProducts->flatMap(function (Product $product) {
                return [
                    $this->normalizeIdentifier($product->isbn),
                    $this->normalizeIdentifier($product->ean),
                ];
            })->filter()->unique();
            $legacyIdentifiers = $identifiers->diff($foundIdentifiers)->values();

            if ($legacyIdentifiers->isNotEmpty()) {
                $normalizedIsbn = DB::raw(
                    "REPLACE(REPLACE(UPPER(COALESCE(isbn, '')), '-', ''), ' ', '')"
                );
                $normalizedEan = DB::raw(
                    "REPLACE(REPLACE(UPPER(COALESCE(ean, '')), '-', ''), ' ', '')"
                );
                $legacyProducts = Product::query()
                    ->where(function ($query) use ($legacyIdentifiers, $normalizedIsbn, $normalizedEan) {
                        $query->whereIn($normalizedIsbn, $legacyIdentifiers)
                            ->orWhereIn($normalizedEan, $legacyIdentifiers);
                    })
                    ->get($columns);
                $identifierProducts = $identifierProducts->concat($legacyProducts);
            }
        }

        $identifierMap = collect();
        foreach ($identifierProducts->unique('id') as $product) {
            foreach (array_unique(array_filter([
                $this->normalizeIdentifier($product->isbn),
                $this->normalizeIdentifier($product->ean),
            ])) as $identifier) {
                $identifierMap->put(
                    $identifier,
                    $identifierMap->get($identifier, collect())->push($product)
                );
            }
        }

        $sourcePairs = $candidates->mapWithKeys(function ($source) {
            return [(int) $source->id => [
                'name' => trim((string) $source->name),
                'author' => trim(explode(',', (string) $source->author)[0]),
            ]];
        });
        $validPairs = $sourcePairs->filter(fn (array $pair) => $pair['name'] !== '' && $pair['author'] !== '');
        $titleAuthorMap = collect();
        if ($validPairs->isNotEmpty()) {
            $titleProducts = Product::query()
                ->join('authors', 'authors.id', '=', 'products.author_id')
                ->whereIn('products.name', $validPairs->pluck('name')->unique()->values())
                ->whereIn('authors.title', $validPairs->pluck('author')->unique()->values())
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
                return $this->titleAuthorKey($product->name, $product->matched_author);
            });
        }

        foreach ($candidates as $source) {
            $identifierMatches = collect();
            foreach ($sourceIdentifiers->get((int) $source->id, collect()) as $identifier) {
                $identifierMatches = $identifierMatches->concat(
                    $identifierMap->get($identifier, collect())
                );
            }
            $identifierMatches = $identifierMatches->unique('id')->values();

            $pair = $sourcePairs->get((int) $source->id, ['name' => '', 'author' => '']);
            $titleMatches = $pair['name'] !== '' && $pair['author'] !== ''
                ? $titleAuthorMap->get($this->titleAuthorKey($pair['name'], $pair['author']), collect())
                : collect();
            $identifierIsAuthoritative = $source instanceof NovellaImportProduct
                || $source instanceof ZnanjeImportProduct;
            $matches = $identifierIsAuthoritative && $identifierMatches->isNotEmpty()
                ? $identifierMatches
                : $identifierMatches->concat($titleMatches)->unique('id')->values();

            $this->applyMatches($source, $matches);
        }

        return $sources;
    }

    private function applyMatches($source, Collection $matches): void
    {
        if ($matches->count() === 1) {
            $this->guardedUpdate($source, [
                'product_id' => (int) $matches->first()->id,
                'check_status' => 'matched',
                'check_message' => $this->matchedMessage($source),
            ]);

            return;
        }

        if ($matches->count() > 1) {
            $this->guardedUpdate($source, [
                'product_id' => null,
                'check_status' => 'conflict',
                'check_message' => $this->conflictPrefix($source)
                    . $matches->pluck('id')->implode(', ') . '.',
            ]);
        }
    }

    private function guardedUpdate($source, array $values): void
    {
        $source->newQuery()
            ->whereKey($source->getKey())
            ->where('is_current', true)
            ->whereNull('product_id')
            ->where('check_status', 'new')
            ->where('source_hash', (string) $source->source_hash)
            ->where('checked_source_hash', (string) $source->checked_source_hash)
            ->update($values);

        // The guarded update may legitimately lose a race with a feed refresh.
        // Always reload so the page renders the state that actually won.
        $source->refresh();
        $source->load('product:id,name,sku,itemid,isbn,price,quantity');
    }

    private function matchedMessage($source): string
    {
        if ($source instanceof LagunaImportProduct) {
            return 'Postojeći Zuzi artikl pronađen po ISBN-u ili kombinaciji naziva i autora.';
        }

        return 'Postojeći Zuzi artikl pronađen po ISBN-u, EAN-u ili kombinaciji naziva i autora.';
    }

    private function conflictPrefix($source): string
    {
        if ($source instanceof LagunaImportProduct) {
            return 'ISBN ili kombinacija naziva i autora odgovara na više Zuzi artikala: ';
        }

        return 'ISBN, EAN ili kombinacija naziva i autora odgovara na više Zuzi artikala: ';
    }

    private function normalizeIdentifier($value): string
    {
        return strtoupper(preg_replace('/[^0-9X]/i', '', (string) $value) ?? '');
    }

    private function titleAuthorKey($name, $author): string
    {
        return mb_strtolower(trim((string) $name)) . "\0"
            . mb_strtolower(trim(explode(',', (string) $author)[0]));
    }
}
