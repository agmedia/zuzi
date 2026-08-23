<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('laguna_import_products')) {
            return;
        }

        Schema::create('laguna_import_products', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('external_id', 64)->unique();
            $table->unsignedInteger('feed_position')->nullable()->index();
            $table->unsignedBigInteger('product_id')->nullable()->index();
            $table->string('name');
            $table->mediumText('description')->nullable();
            $table->string('product_type', 128)->nullable()->index();
            $table->string('source_category', 255)->nullable();
            $table->string('source_url', 1024);
            $table->string('image_url', 1024)->nullable();
            $table->text('additional_image_urls')->nullable();
            $table->decimal('price_rsd', 15, 4)->default(0);
            $table->decimal('sale_price_rsd', 15, 4)->nullable();
            $table->string('availability', 32)->nullable();
            $table->string('isbn', 32)->nullable()->index();
            $table->string('author')->nullable();
            $table->string('genre')->nullable();
            $table->string('format', 64)->nullable();
            $table->unsignedInteger('pages')->nullable();
            $table->string('letter', 64)->nullable();
            $table->string('binding', 64)->nullable();
            $table->unsignedSmallInteger('publication_year')->nullable();
            $table->mediumText('detail_payload')->nullable();
            $table->mediumText('translated_description')->nullable();
            $table->char('translation_source_hash', 64)->nullable();
            $table->char('source_hash', 64);
            $table->char('checked_source_hash', 64)->nullable();
            $table->char('imported_hash', 64)->nullable();
            $table->uuid('feed_token')->index();
            $table->boolean('is_current')->default(true)->index();
            $table->string('check_status', 32)->default('pending')->index();
            $table->text('check_message')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laguna_import_products');
    }
};
