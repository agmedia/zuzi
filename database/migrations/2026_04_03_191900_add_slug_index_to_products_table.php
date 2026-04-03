<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddSlugIndexToProductsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if ( ! Schema::hasColumn('products', 'slug') || $this->hasIndex('products', 'products_slug_index')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->index('slug', 'products_slug_index');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        if ( ! $this->hasIndex('products', 'products_slug_index')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex('products_slug_index');
        });
    }

    /**
     * Check if the given index exists on the current database connection.
     *
     * @param string $table
     * @param string $index
     *
     * @return bool
     */
    private function hasIndex(string $table, string $index): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::connection()->getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
}
