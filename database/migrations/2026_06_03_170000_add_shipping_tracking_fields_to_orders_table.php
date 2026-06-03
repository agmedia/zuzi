<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (! Schema::hasColumn('orders', 'shipping_carrier')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('shipping_carrier', 32)->nullable()->after('shipping_code')->index();
            });
        }

        if (! Schema::hasColumn('orders', 'shipping_parcel_id')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('shipping_parcel_id')->nullable()->after('tracking_code')->index();
            });
        }

        if (! Schema::hasColumn('orders', 'shipping_tracking_url')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('shipping_tracking_url')->nullable()->after('shipping_parcel_id');
            });
        }

        if (! Schema::hasColumn('orders', 'shipping_tracking_status_code')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('shipping_tracking_status_code', 32)->nullable()->after('shipping_tracking_url')->index();
            });
        }

        if (! Schema::hasColumn('orders', 'shipping_tracking_status')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('shipping_tracking_status')->nullable()->after('shipping_tracking_status_code');
            });
        }

        if (! Schema::hasColumn('orders', 'shipping_tracking_updated_at')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->timestamp('shipping_tracking_updated_at')->nullable()->after('shipping_tracking_status')->index();
            });
        }

        if (! Schema::hasColumn('orders', 'shipping_tracking_payload')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->longText('shipping_tracking_payload')->nullable()->after('shipping_tracking_updated_at');
            });
        }
    }

    public function down(): void
    {
        // Intentionally left empty to avoid dropping tracking history on legacy installs.
    }
};
