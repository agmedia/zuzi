<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('products') || Schema::hasColumn('products', 'isbn')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->string('isbn', 32)->nullable()->after('ean');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'isbn')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('isbn');
        });
    }
};
