<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddUserIdIndexToCartsTable extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('carts') || ! Schema::hasColumn('carts', 'user_id')) {
            return;
        }

        if ($this->indexExists('carts', 'carts_user_id_index')) {
            return;
        }

        DB::statement('ALTER TABLE `carts` ADD INDEX `carts_user_id_index` (`user_id`)');
    }

    public function down()
    {
        if (! Schema::hasTable('carts') || ! $this->indexExists('carts', 'carts_user_id_index')) {
            return;
        }

        DB::statement('ALTER TABLE `carts` DROP INDEX `carts_user_id_index`');
    }

    private function indexExists(string $table, string $index): bool
    {
        $result = DB::selectOne(
            'SELECT COUNT(*) as count FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$table, $index]
        );

        return (int) ($result->count ?? 0) > 0;
    }
}
