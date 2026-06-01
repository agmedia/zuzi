<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('orders') || Schema::hasColumn('orders', 'review_request_sent_at')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('review_request_sent_at')->nullable()->after('printed')->index();
        });
    }

    public function down(): void
    {
        // Intentionally left empty to avoid dropping data on legacy installs.
    }
};
