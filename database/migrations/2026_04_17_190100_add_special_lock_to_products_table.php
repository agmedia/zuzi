<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('products') || Schema::hasColumn('products', 'special_lock')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->boolean('special_lock')->default(0)->after('special_to');
        });
    }

    public function down(): void
    {
        // Intentionally left empty to avoid destructive schema changes on legacy installs.
    }
};
