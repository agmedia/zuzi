<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('pages') || Schema::hasColumn('pages', 'related_products')) {
            return;
        }

        Schema::table('pages', function (Blueprint $table) {
            $table->text('related_products')->nullable()->after('featured');
        });
    }

    public function down(): void
    {
        // Intentionally left empty to avoid destructive schema changes on legacy installs.
    }
};
