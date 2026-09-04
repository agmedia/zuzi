<?php

namespace App\Services\Catalog;

use Illuminate\Http\Request;

class ImportFilterMemory
{
    private const FIELDS = [
        'search',
        'source_category',
        'source_genre',
        'status',
    ];

    private const SESSION_PREFIX = 'catalog_import_filters.';

    /**
     * Restore remembered filters when the import page is opened without them.
     * Returns true when the caller should redirect after clearing the filters.
     */
    public function restore(Request $request, string $source): bool
    {
        $sessionKey = $this->sessionKey($source);
        if ($request->boolean('clear_filters')) {
            $request->session()->forget($sessionKey);

            return true;
        }

        if ($this->hasExplicitFilters($request)) {
            return false;
        }

        foreach ((array) $request->session()->get($sessionKey, []) as $field => $value) {
            if (in_array($field, self::FIELDS, true) && is_string($value) && $value !== '') {
                $request->query->set($field, $value);
            }
        }

        return false;
    }

    /**
     * Persist the normalized filters currently used by the listing.
     */
    public function remember(Request $request, string $source): void
    {
        $filters = [];
        foreach (self::FIELDS as $field) {
            $value = $request->query($field);
            if (! is_scalar($value)) {
                continue;
            }

            $value = trim((string) $value);
            if ($value !== '') {
                $filters[$field] = mb_substr($value, 0, 512);
            }
        }

        $request->session()->put($this->sessionKey($source), $filters);
    }

    private function hasExplicitFilters(Request $request): bool
    {
        foreach (self::FIELDS as $field) {
            if ($request->query->has($field)) {
                return true;
            }
        }

        return false;
    }

    private function sessionKey(string $source): string
    {
        return self::SESSION_PREFIX . preg_replace('/[^a-z0-9_-]+/i', '', $source);
    }
}
