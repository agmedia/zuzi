<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('laguna_import_products')
            || Schema::hasColumn('laguna_import_products', 'feed_position')) {
            return;
        }

        Schema::table('laguna_import_products', function (Blueprint $table) {
            $table->unsignedInteger('feed_position')->nullable()->index()->after('external_id');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('laguna_import_products')
            || ! Schema::hasColumn('laguna_import_products', 'feed_position')) {
            return;
        }

        Schema::table('laguna_import_products', function (Blueprint $table) {
            $table->dropColumn('feed_position');
        });
    }
};
