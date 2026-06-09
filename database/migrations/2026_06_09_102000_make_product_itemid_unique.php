<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'itemid')) {
            return;
        }

        $this->dropIndexIfExists('products', 'products_itemid_index');

        DB::statement('ALTER TABLE `products` MODIFY `itemid` BIGINT UNSIGNED NULL');
        DB::table('products')->where('itemid', 0)->update(['itemid' => null]);

        if (! $this->hasIndex('products', 'products_itemid_unique')) {
            DB::statement('ALTER TABLE `products` ADD UNIQUE INDEX `products_itemid_unique` (`itemid`)');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'itemid')) {
            return;
        }

        $this->dropIndexIfExists('products', 'products_itemid_unique');

        DB::table('products')->whereNull('itemid')->update(['itemid' => 0]);
        DB::statement('ALTER TABLE `products` MODIFY `itemid` BIGINT UNSIGNED NOT NULL DEFAULT 0');

        if (! $this->hasIndex('products', 'products_itemid_index')) {
            DB::statement('ALTER TABLE `products` ADD INDEX `products_itemid_index` (`itemid`)');
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        return count(DB::select('SHOW INDEX FROM `' . $table . '` WHERE Key_name = ?', [$index])) > 0;
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if ($this->hasIndex($table, $index)) {
            DB::statement('ALTER TABLE `' . $table . '` DROP INDEX `' . $index . '`');
        }
    }
};
