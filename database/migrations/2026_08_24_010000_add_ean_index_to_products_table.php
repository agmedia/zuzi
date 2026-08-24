<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products')
            && Schema::hasColumn('products', 'ean')
            && ! $this->hasIndex('products', 'products_ean_index')) {
            Schema::table('products', function (Blueprint $table) {
                $table->index('ean', 'products_ean_index');
            });
        }
    }

    public function down(): void
    {
        // Keep the index: it may have existed before this idempotent migration,
        // and removing it would slow every ISBN/EAN catalogue lookup.
    }

    private function hasIndex(string $table, string $index): bool
    {
        return count(DB::select('SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?', [$index])) > 0;
    }
};
