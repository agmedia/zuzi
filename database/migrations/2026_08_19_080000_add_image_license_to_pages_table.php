<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('pages') || Schema::hasColumn('pages', 'image_license')) {
            return;
        }

        Schema::table('pages', function (Blueprint $table) {
            $table->longText('image_license')->nullable()->after('image');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('pages') || ! Schema::hasColumn('pages', 'image_license')) {
            return;
        }

        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('image_license');
        });
    }
};
