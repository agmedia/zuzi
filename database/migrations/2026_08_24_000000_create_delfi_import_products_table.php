<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('delfi_import_feed_rows')) {
            Schema::create('delfi_import_feed_rows', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->uuid('feed_token')->index();
                $table->string('external_id', 64);
                $table->unsignedBigInteger('remote_product_id')->nullable();
                $table->unsignedInteger('feed_position')->nullable();
                $table->string('name');
                $table->mediumText('description')->nullable();
                $table->string('source_category', 64);
                $table->string('source_publisher', 191)->nullable();
                $table->string('source_url', 1024);
                $table->string('image_url', 1024)->nullable();
                $table->json('additional_image_urls')->nullable();
                $table->decimal('price_rsd', 15, 4)->default(0);
                $table->decimal('sale_price_rsd', 15, 4)->nullable();
                $table->string('availability', 32)->nullable();
                $table->string('author')->nullable();
                $table->char('source_hash', 64);
                $table->timestamps();

                $table->unique(['feed_token', 'external_id'], 'delfi_feed_rows_token_external_unique');
                $table->index(
                    ['feed_token', 'remote_product_id'],
                    'delfi_feed_rows_token_remote_index'
                );
                $table->index('created_at', 'delfi_feed_rows_created_at_index');
            });
        }

        if (! Schema::hasTable('delfi_import_products')) {
            Schema::create('delfi_import_products', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('external_id', 64)->unique();
                $table->unsignedBigInteger('remote_product_id')->nullable()->unique();
                $table->unsignedInteger('feed_position')->nullable()->index();
                $table->unsignedBigInteger('product_id')->nullable()->index();
                $table->string('name');
                $table->mediumText('description')->nullable();
                $table->string('source_category', 64)->index();
                $table->string('source_publisher', 191)->nullable()->index();
                $table->string('source_url', 1024);
                $table->string('image_url', 1024)->nullable();
                $table->json('additional_image_urls')->nullable();
                $table->decimal('price_rsd', 15, 4)->default(0);
                $table->decimal('sale_price_rsd', 15, 4)->nullable();
                $table->string('availability', 32)->nullable()->index();
                $table->string('isbn', 32)->nullable()->index();
                $table->string('ean', 32)->nullable()->index();
                $table->string('nav_id', 64)->nullable()->index();
                $table->string('author')->nullable();
                $table->json('source_genres')->nullable();
                $table->string('genre')->nullable();
                $table->string('format', 128)->nullable();
                $table->unsignedInteger('pages')->nullable();
                $table->string('letter', 64)->nullable();
                $table->string('binding', 64)->nullable();
                $table->unsignedSmallInteger('publication_year')->nullable();
                $table->string('language', 64)->nullable();
                $table->string('origin', 128)->nullable();
                $table->json('detail_payload')->nullable();
                $table->mediumText('translated_description')->nullable();
                $table->char('translation_source_hash', 64)->nullable();
                $table->char('source_hash', 64);
                $table->char('checked_source_hash', 64)->nullable();
                $table->char('imported_hash', 64)->nullable();
                $table->uuid('feed_token')->index();
                $table->boolean('is_current')->default(false)->index();
                $table->string('check_status', 32)->default('pending')->index();
                $table->text('check_message')->nullable();
                $table->timestamp('checked_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('imported_at')->nullable();
                $table->timestamps();
            });
        }

    }

    public function down(): void
    {
        Schema::dropIfExists('delfi_import_products');
        Schema::dropIfExists('delfi_import_feed_rows');
    }

};
