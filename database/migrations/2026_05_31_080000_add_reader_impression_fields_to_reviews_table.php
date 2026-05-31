<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('reviews')) {
            return;
        }

        Schema::table('reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('reviews', 'title')) {
                $table->string('title', 120)->nullable()->after('avatar');
            }

            if (! Schema::hasColumn('reviews', 'recommended_for')) {
                $table->string('recommended_for')->nullable()->after('message');
            }

            if (! Schema::hasColumn('reviews', 'liked_most')) {
                $table->string('liked_most')->nullable()->after('recommended_for');
            }

            if (! Schema::hasColumn('reviews', 'tags')) {
                $table->text('tags')->nullable()->after('liked_most');
            }

            if (! Schema::hasColumn('reviews', 'has_spoilers')) {
                $table->boolean('has_spoilers')->default(false)->after('tags');
            }

            if (! Schema::hasColumn('reviews', 'verified_purchase')) {
                $table->boolean('verified_purchase')->default(false)->after('has_spoilers');
            }

            if (! Schema::hasColumn('reviews', 'helpful_count')) {
                $table->unsignedInteger('helpful_count')->default(0)->after('verified_purchase');
            }
        });
    }

    public function down(): void
    {
        // Intentionally left empty to avoid destructive column drops on legacy installs.
    }
};
