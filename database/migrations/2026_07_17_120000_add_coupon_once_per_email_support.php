<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('product_actions') && ! Schema::hasColumn('product_actions', 'once_per_email')) {
            Schema::table('product_actions', function (Blueprint $table) {
                $table->boolean('once_per_email')->default(false)->after('quantity');
            });
        }

        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'coupon_code')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('coupon_code', 191)->nullable()->after('total')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'coupon_code')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex(['coupon_code']);
                $table->dropColumn('coupon_code');
            });
        }

        if (Schema::hasTable('product_actions') && Schema::hasColumn('product_actions', 'once_per_email')) {
            Schema::table('product_actions', function (Blueprint $table) {
                $table->dropColumn('once_per_email');
            });
        }
    }
};
