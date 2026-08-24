-- Delfi import staging tablica za live bazu (phpMyAdmin / MySQL 5.7+).
-- Skripta je idempotentna i ne dira postojeće proizvode ni postavke.

-- Ubrzava provjeru postoji li Delfi EAN već u Zuzi katalogu. MySQL 5.7
-- nema CREATE INDEX IF NOT EXISTS, zato se indeks dodaje uvjetno.
SET @zuzi_delfi_has_ean_index = (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND INDEX_NAME = 'products_ean_index'
);
SET @zuzi_delfi_ean_index_sql = IF(
    @zuzi_delfi_has_ean_index = 0,
    'ALTER TABLE `products` ADD INDEX `products_ean_index` (`ean`)',
    'SELECT 1'
);
PREPARE zuzi_delfi_ean_index_stmt FROM @zuzi_delfi_ean_index_sql;
EXECUTE zuzi_delfi_ean_index_stmt;
DEALLOCATE PREPARE zuzi_delfi_ean_index_stmt;

CREATE TABLE IF NOT EXISTS `delfi_import_feed_rows` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `feed_token` CHAR(36) NOT NULL,
    `external_id` VARCHAR(64) NOT NULL,
    `remote_product_id` BIGINT UNSIGNED NULL,
    `feed_position` INT UNSIGNED NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` MEDIUMTEXT NULL,
    `source_category` VARCHAR(64) NOT NULL,
    `source_publisher` VARCHAR(191) NULL,
    `source_url` VARCHAR(1024) NOT NULL,
    `image_url` VARCHAR(1024) NULL,
    `additional_image_urls` JSON NULL,
    `price_rsd` DECIMAL(15,4) NOT NULL DEFAULT 0,
    `sale_price_rsd` DECIMAL(15,4) NULL,
    `availability` VARCHAR(32) NULL,
    `author` VARCHAR(255) NULL,
    `source_hash` CHAR(64) NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `delfi_feed_rows_token_external_unique` (`feed_token`, `external_id`),
    KEY `delfi_import_feed_rows_feed_token_index` (`feed_token`),
    KEY `delfi_feed_rows_token_remote_index` (`feed_token`, `remote_product_id`),
    KEY `delfi_feed_rows_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `delfi_import_products` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `external_id` VARCHAR(64) NOT NULL,
    `remote_product_id` BIGINT UNSIGNED NULL,
    `feed_position` INT UNSIGNED NULL,
    `product_id` BIGINT UNSIGNED NULL,
    `name` VARCHAR(255) NOT NULL,
    `description` MEDIUMTEXT NULL,
    `source_category` VARCHAR(64) NOT NULL,
    `source_publisher` VARCHAR(191) NULL,
    `source_url` VARCHAR(1024) NOT NULL,
    `image_url` VARCHAR(1024) NULL,
    `additional_image_urls` JSON NULL,
    `price_rsd` DECIMAL(15,4) NOT NULL DEFAULT 0,
    `sale_price_rsd` DECIMAL(15,4) NULL,
    `availability` VARCHAR(32) NULL,
    `isbn` VARCHAR(32) NULL,
    `ean` VARCHAR(32) NULL,
    `nav_id` VARCHAR(64) NULL,
    `author` VARCHAR(255) NULL,
    `source_genres` JSON NULL,
    `genre` VARCHAR(255) NULL,
    `format` VARCHAR(128) NULL,
    `pages` INT UNSIGNED NULL,
    `letter` VARCHAR(64) NULL,
    `binding` VARCHAR(64) NULL,
    `publication_year` SMALLINT UNSIGNED NULL,
    `language` VARCHAR(64) NULL,
    `origin` VARCHAR(128) NULL,
    `detail_payload` JSON NULL,
    `translated_description` MEDIUMTEXT NULL,
    `translation_source_hash` CHAR(64) NULL,
    `source_hash` CHAR(64) NOT NULL,
    `checked_source_hash` CHAR(64) NULL,
    `imported_hash` CHAR(64) NULL,
    `feed_token` CHAR(36) NOT NULL,
    `is_current` TINYINT(1) NOT NULL DEFAULT 0,
    `check_status` VARCHAR(32) NOT NULL DEFAULT 'pending',
    `check_message` TEXT NULL,
    `checked_at` TIMESTAMP NULL DEFAULT NULL,
    `last_seen_at` TIMESTAMP NULL DEFAULT NULL,
    `imported_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `delfi_import_products_external_id_unique` (`external_id`),
    UNIQUE KEY `delfi_import_products_remote_product_id_unique` (`remote_product_id`),
    KEY `delfi_import_products_feed_position_index` (`feed_position`),
    KEY `delfi_import_products_product_id_index` (`product_id`),
    KEY `delfi_import_products_source_category_index` (`source_category`),
    KEY `delfi_import_products_source_publisher_index` (`source_publisher`),
    KEY `delfi_import_products_availability_index` (`availability`),
    KEY `delfi_import_products_isbn_index` (`isbn`),
    KEY `delfi_import_products_ean_index` (`ean`),
    KEY `delfi_import_products_nav_id_index` (`nav_id`),
    KEY `delfi_import_products_feed_token_index` (`feed_token`),
    KEY `delfi_import_products_is_current_index` (`is_current`),
    KEY `delfi_import_products_check_status_index` (`check_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT
    TABLE_NAME,
    TABLE_ROWS
FROM INFORMATION_SCHEMA.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('delfi_import_feed_rows', 'delfi_import_products')
ORDER BY TABLE_NAME;
