<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        if (! Schema::hasColumn('products', 'stock_restored_from_backup')) {
            Schema::table('products', function (Blueprint $table) {
                $table->boolean('stock_restored_from_backup')
                    ->default(false)
                    ->after('delivery_24h');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'stock_restored_from_backup')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('stock_restored_from_backup');
        });
    }
};
