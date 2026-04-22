<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if ($this->usesSqlite()) {
            Schema::create('blog_cta_buttons', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('cta_block_id')->constrained('blog_cta_blocks')->cascadeOnDelete();
                $table->string('label');
                $table->string('url');
                $table->string('icon', 32)->nullable();
                $table->string('style', 32)->default('outline');
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['cta_block_id', 'is_active', 'sort_order']);
            });

            return;
        }

        DB::statement(<<<'SQL'
            CREATE TABLE `blog_cta_buttons` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `cta_block_id` BIGINT UNSIGNED NOT NULL,
                `label` VARCHAR(255) NOT NULL,
                `url` VARCHAR(255) NOT NULL,
                `icon` VARCHAR(32) NULL,
                `style` VARCHAR(32) NOT NULL DEFAULT 'outline',
                `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `blog_cta_buttons_cta_block_id_is_active_sort_order_index` (`cta_block_id`, `is_active`, `sort_order`),
                CONSTRAINT `blog_cta_buttons_cta_block_id_foreign`
                    FOREIGN KEY (`cta_block_id`) REFERENCES `blog_cta_blocks` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->usesSqlite()) {
            Schema::dropIfExists('blog_cta_buttons');

            return;
        }

        DB::statement('DROP TABLE IF EXISTS `blog_cta_buttons`');
    }

    /**
     * Keep SQLite compatibility for tests that override the DB driver.
     */
    private function usesSqlite(): bool
    {
        return DB::getDriverName() === 'sqlite';
    }
};
