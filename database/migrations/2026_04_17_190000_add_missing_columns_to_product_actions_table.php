<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('product_actions')) {
            return;
        }

        Schema::table('product_actions', function (Blueprint $table) {
            if (! Schema::hasColumn('product_actions', 'data')) {
                $table->text('data')->nullable()->after('min_cart');
            }

            if (! Schema::hasColumn('product_actions', 'coupon')) {
                $table->string('coupon')->nullable()->after('data');
            }

            if (! Schema::hasColumn('product_actions', 'quantity')) {
                $table->boolean('quantity')->default(0)->after('coupon');
            }

            if (! Schema::hasColumn('product_actions', 'lock')) {
                $table->boolean('lock')->default(0)->after('quantity');
            }
        });
    }

    public function down(): void
    {
        // Intentionally left empty to avoid destructive column drops on legacy installs.
    }
};
