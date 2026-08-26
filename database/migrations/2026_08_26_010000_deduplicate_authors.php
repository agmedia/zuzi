<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const UNKNOWN_AUTHOR_ID = 3282;

    private const AUTHORS_BACKUP_TABLE = '_backup_authors_before_dedup_20260826';

    private const PRODUCT_LINKS_BACKUP_TABLE = '_backup_product_authors_before_dedup_20260826';

    private const UNIQUE_INDEX = 'authors_normalized_title_unique';

    public function up(): void
    {
        if (! Schema::hasTable('authors') || ! Schema::hasTable('products')) {
            return;
        }

        $hasBlankAuthor = false;
        foreach (DB::table('authors')->orderBy('id')->pluck('title') as $title) {
            if ($this->normalizedKey((string) $title) === '') {
                $hasBlankAuthor = true;
                break;
            }
        }

        if ($hasBlankAuthor) {
            $unknownAuthor = DB::table('authors')->where('id', self::UNKNOWN_AUTHOR_ID)->first(['title']);
            if (! $unknownAuthor || $this->normalizedKey((string) $unknownAuthor->title) === '') {
                throw new RuntimeException(
                    'Blank authors can only be merged when the configured unknown author with ID '
                    . self::UNKNOWN_AUTHOR_ID . ' exists.'
                );
            }
        }

        $this->backupAuthors();
        $this->createProductLinksBackup();

        if (! Schema::hasColumn('authors', 'normalized_title')) {
            DB::statement(
                'ALTER TABLE `authors` '
                . 'ADD COLUMN `normalized_title` VARCHAR(191) '
                . 'CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL AFTER `title`'
            );
        }

        $this->backfillNormalizedTitles();
        $this->createDuplicateMap();

        DB::transaction(function (): void {
            DB::statement(
                'INSERT IGNORE INTO `' . self::PRODUCT_LINKS_BACKUP_TABLE . '` '
                . '(`product_id`, `author_id`) '
                . 'SELECT product.`id`, product.`author_id` '
                . 'FROM `products` AS product '
                . 'INNER JOIN `tmp_author_duplicate_map` AS duplicate_map '
                . 'ON duplicate_map.`duplicate_id` = product.`author_id`'
            );

            DB::statement(
                'UPDATE `products` AS product '
                . 'INNER JOIN `tmp_author_duplicate_map` AS duplicate_map '
                . 'ON duplicate_map.`duplicate_id` = product.`author_id` '
                . 'SET product.`author_id` = duplicate_map.`canonical_id`'
            );

            DB::statement(
                'DELETE duplicate_author '
                . 'FROM `authors` AS duplicate_author '
                . 'INNER JOIN `tmp_author_duplicate_map` AS duplicate_map '
                . 'ON duplicate_map.`duplicate_id` = duplicate_author.`id`'
            );
        });

        DB::statement('DROP TEMPORARY TABLE IF EXISTS `tmp_author_duplicate_map`');

        DB::statement(
            'ALTER TABLE `authors` MODIFY COLUMN `normalized_title` VARCHAR(191) '
            . 'CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL'
        );

        if (! $this->hasIndex('authors', self::UNIQUE_INDEX)) {
            DB::statement(
                'ALTER TABLE `authors` ADD UNIQUE INDEX `' . self::UNIQUE_INDEX . '` (`normalized_title`)'
            );
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('authors') || ! Schema::hasTable('products')) {
            return;
        }

        if (Schema::hasColumn('authors', 'normalized_title')) {
            if ($this->hasIndex('authors', self::UNIQUE_INDEX)) {
                DB::statement(
                    'ALTER TABLE `authors` DROP INDEX `' . self::UNIQUE_INDEX . '`'
                );
            }

            DB::statement('ALTER TABLE `authors` DROP COLUMN `normalized_title`');
        }

        if (Schema::hasTable(self::AUTHORS_BACKUP_TABLE)) {
            $columns = $this->commonColumns('authors', self::AUTHORS_BACKUP_TABLE);
            if ($columns !== []) {
                $columnList = $this->quotedColumnList($columns);
                DB::statement(
                    'INSERT IGNORE INTO `authors` (' . $columnList . ') '
                    . 'SELECT ' . $columnList . ' FROM `' . self::AUTHORS_BACKUP_TABLE . '`'
                );
            }
        }

        if (Schema::hasTable(self::PRODUCT_LINKS_BACKUP_TABLE)) {
            DB::statement(
                'UPDATE `products` AS product '
                . 'INNER JOIN `' . self::PRODUCT_LINKS_BACKUP_TABLE . '` AS backup_link '
                . 'ON backup_link.`product_id` = product.`id` '
                . 'INNER JOIN `authors` AS original_author '
                . 'ON original_author.`id` = backup_link.`author_id` '
                . 'SET product.`author_id` = backup_link.`author_id`'
            );
        }

        // The permanent backup tables intentionally remain available for a
        // manual recovery or audit after a rollback.
    }

    private function backupAuthors(): void
    {
        DB::statement(
            'CREATE TABLE IF NOT EXISTS `' . self::AUTHORS_BACKUP_TABLE . '` LIKE `authors`'
        );

        // A retry after an interrupted ALTER may have cloned normalized_title
        // into a newly created backup table. Backups keep the pre-change shape.
        if (Schema::hasColumn(self::AUTHORS_BACKUP_TABLE, 'normalized_title')) {
            DB::statement(
                'ALTER TABLE `' . self::AUTHORS_BACKUP_TABLE . '` DROP COLUMN `normalized_title`'
            );
        }

        $columns = $this->commonColumns('authors', self::AUTHORS_BACKUP_TABLE);
        $columnList = $this->quotedColumnList($columns);

        DB::statement(
            'INSERT IGNORE INTO `' . self::AUTHORS_BACKUP_TABLE . '` (' . $columnList . ') '
            . 'SELECT ' . $columnList . ' FROM `authors`'
        );
    }

    private function createProductLinksBackup(): void
    {
        DB::statement(
            'CREATE TABLE IF NOT EXISTS `' . self::PRODUCT_LINKS_BACKUP_TABLE . '` ('
            . '`product_id` BIGINT UNSIGNED NOT NULL, '
            . '`author_id` BIGINT UNSIGNED NOT NULL, '
            . 'PRIMARY KEY (`product_id`), '
            . 'KEY `backup_product_authors_author_id` (`author_id`)'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    private function backfillNormalizedTitles(): void
    {
        DB::statement('DROP TEMPORARY TABLE IF EXISTS `tmp_author_normalized_keys`');
        DB::statement(
            'CREATE TEMPORARY TABLE `tmp_author_normalized_keys` ('
            . '`author_id` BIGINT UNSIGNED NOT NULL, '
            . '`normalized_title` VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL, '
            . 'PRIMARY KEY (`author_id`)'
            . ') ENGINE=InnoDB'
        );

        DB::table('authors')
            ->select(['id', 'title'])
            ->orderBy('id')
            ->chunkById(500, function ($authors): void {
                $keys = [];
                foreach ($authors as $author) {
                    $keys[] = [
                        'author_id' => (int) $author->id,
                        'normalized_title' => $this->normalizedKey((string) $author->title),
                    ];
                }

                if ($keys !== []) {
                    DB::table('tmp_author_normalized_keys')->insert($keys);
                }
            });

        DB::statement(
            'UPDATE `authors` AS author '
            . 'INNER JOIN `tmp_author_normalized_keys` AS normalized '
            . 'ON normalized.`author_id` = author.`id` '
            . 'SET author.`normalized_title` = normalized.`normalized_title`'
        );

        DB::statement('DROP TEMPORARY TABLE `tmp_author_normalized_keys`');

        if (DB::table('authors')->whereNull('normalized_title')->exists()) {
            throw new RuntimeException('Not every author received a normalized_title value.');
        }
    }

    private function createDuplicateMap(): void
    {
        DB::statement('DROP TEMPORARY TABLE IF EXISTS `tmp_author_duplicate_map`');
        DB::statement(
            'CREATE TEMPORARY TABLE `tmp_author_duplicate_map` ('
            . '`duplicate_id` BIGINT UNSIGNED NOT NULL, '
            . '`canonical_id` BIGINT UNSIGNED NOT NULL, '
            . 'PRIMARY KEY (`duplicate_id`), '
            . 'KEY `tmp_author_duplicate_map_canonical_id` (`canonical_id`)'
            . ') ENGINE=InnoDB'
        );

        DB::statement(
            'INSERT INTO `tmp_author_duplicate_map` (`duplicate_id`, `canonical_id`) '
            . 'SELECT duplicate_author.`id`, duplicate_group.`canonical_id` '
            . 'FROM `authors` AS duplicate_author '
            . 'INNER JOIN ('
            . 'SELECT duplicate_candidate.`normalized_title`, '
            . 'CASE '
            . "WHEN duplicate_candidate.`normalized_title` = '' THEN " . self::UNKNOWN_AUTHOR_ID . ' '
            . 'WHEN MAX(duplicate_candidate.`id` = ' . self::UNKNOWN_AUTHOR_ID . ') = 1 '
            . 'THEN ' . self::UNKNOWN_AUTHOR_ID . ' '
            . 'ELSE CAST(SUBSTRING_INDEX(GROUP_CONCAT('
            . 'duplicate_candidate.`id` ORDER BY '
            . 'COALESCE(product_links.`product_count`, 0) DESC, '
            . 'duplicate_candidate.`status` DESC, '
            . 'duplicate_candidate.`id` ASC SEPARATOR \',\''
            . '), \',\', 1) AS UNSIGNED) END AS `canonical_id`, '
            . 'COUNT(*) AS `author_count` '
            . 'FROM `authors` AS duplicate_candidate '
            . 'LEFT JOIN ('
            . 'SELECT `author_id`, COUNT(*) AS `product_count` '
            . 'FROM `products` GROUP BY `author_id`'
            . ') AS product_links ON product_links.`author_id` = duplicate_candidate.`id` '
            . 'GROUP BY duplicate_candidate.`normalized_title` '
            . "HAVING COUNT(*) > 1 OR duplicate_candidate.`normalized_title` = ''"
            . ') AS duplicate_group '
            . 'ON duplicate_group.`normalized_title` = duplicate_author.`normalized_title` '
            . 'WHERE duplicate_author.`id` <> duplicate_group.`canonical_id`'
        );
    }

    private function normalizedKey(string $title): string
    {
        $name = $title;

        if (class_exists(Normalizer::class)) {
            $normalized = Normalizer::normalize($name, Normalizer::FORM_C);
            if (is_string($normalized)) {
                $name = $normalized;
            }
        }

        $collapsed = preg_replace('/[\p{Z}\s]+/u', ' ', $name);
        $name = trim(is_string($collapsed) ? $collapsed : $name);
        $normalized = mb_strtolower($name, 'UTF-8');

        if (mb_strlen($normalized, 'UTF-8') <= 191) {
            return $normalized;
        }

        return mb_substr($normalized, 0, 126, 'UTF-8')
            . ':' . hash('sha256', $normalized);
    }

    private function hasIndex(string $table, string $index): bool
    {
        return count(DB::select(
            'SHOW INDEX FROM `' . str_replace('`', '``', $table) . '` WHERE Key_name = ?',
            [$index]
        )) > 0;
    }

    private function commonColumns(string $leftTable, string $rightTable): array
    {
        $left = array_map(
            static fn ($column): string => $column->Field,
            DB::select('SHOW COLUMNS FROM `' . str_replace('`', '``', $leftTable) . '`')
        );
        $right = array_map(
            static fn ($column): string => $column->Field,
            DB::select('SHOW COLUMNS FROM `' . str_replace('`', '``', $rightTable) . '`')
        );

        return array_values(array_intersect($left, $right));
    }

    private function quotedColumnList(array $columns): string
    {
        return implode(', ', array_map(
            static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`',
            $columns
        ));
    }
};
