<?php

namespace App\Services\Catalog;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ImportedProductLinkResetter
{
    private const TABLES = [
        'laguna_import_products',
        'delfi_import_products',
        'novella_import_products',
        'znanje_import_products',
    ];

    public function reset(int $productId): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $readyForImport = [
                'product_id' => null,
                'check_status' => 'new',
                'check_message' => 'Povezani Zuzi artikl je izbrisan. Spremno za ponovni uvoz.',
                'imported_hash' => null,
                'imported_at' => null,
                'updated_at' => now(),
            ];

            DB::table($table)
                ->where('product_id', $productId)
                ->where('is_current', true)
                ->whereColumn('checked_source_hash', 'source_hash')
                ->update($readyForImport);

            DB::table($table)
                ->where('product_id', $productId)
                ->update([
                    'product_id' => null,
                    'check_status' => 'pending',
                    'check_message' => 'Povezani Zuzi artikl je izbrisan. Potrebna je ponovna provjera prije uvoza.',
                    'imported_hash' => null,
                    'imported_at' => null,
                    'updated_at' => now(),
                ]);
        }
    }
}
