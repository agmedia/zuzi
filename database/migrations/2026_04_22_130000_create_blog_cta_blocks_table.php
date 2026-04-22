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
            Schema::create('blog_cta_blocks', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('blog_post_id')->constrained('pages')->cascadeOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index(['blog_post_id', 'is_active', 'sort_order']);
            });

            return;
        }

        DB::statement(<<<'SQL'
            CREATE TABLE `blog_cta_blocks` (
                `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `blog_post_id` BIGINT UNSIGNED NOT NULL,
                `title` VARCHAR(255) NOT NULL,
                `description` TEXT NULL,
                `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
                `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                `created_at` TIMESTAMP NULL DEFAULT NULL,
                `updated_at` TIMESTAMP NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `blog_cta_blocks_blog_post_id_is_active_sort_order_index` (`blog_post_id`, `is_active`, `sort_order`),
                CONSTRAINT `blog_cta_blocks_blog_post_id_foreign`
                    FOREIGN KEY (`blog_post_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if ($this->usesSqlite()) {
            Schema::dropIfExists('blog_cta_blocks');

            return;
        }

        DB::statement('DROP TABLE IF EXISTS `blog_cta_blocks`');
    }

    /**
     * Keep SQLite compatibility for tests that override the DB driver.
     */
    private function usesSqlite(): bool
    {
        return DB::getDriverName() === 'sqlite';
    }
};
