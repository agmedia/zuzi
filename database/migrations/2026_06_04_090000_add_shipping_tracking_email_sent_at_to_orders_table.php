<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('orders') || Schema::hasColumn('orders', 'shipping_tracking_email_sent_at')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('shipping_tracking_email_sent_at')->nullable()->after('shipping_tracking_updated_at');
        });
    }

    public function down(): void
    {
        // Intentionally left empty to avoid losing delivery notification history on legacy installs.
    }
};
