-- Lokalni Pelion barcode index za brzi dohvat zalihe po ISBN-u / ITEMBARCODE.
CREATE TABLE IF NOT EXISTS `pelion_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_id` BIGINT UNSIGNED NOT NULL,
    `item_barcode` VARCHAR(32) NOT NULL,
    `item_code` VARCHAR(64) NULL,
    `item_name` VARCHAR(255) NULL,
    `item_group_id` VARCHAR(32) NULL,
    `item_active` VARCHAR(8) NULL,
    `item_type` VARCHAR(64) NULL,
    `item_price` DECIMAL(15, 4) NULL,
    `synced_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NULL,
    `updated_at` TIMESTAMP NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `pelion_items_item_id_unique` (`item_id`),
    KEY `pelion_items_item_barcode_index` (`item_barcode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET @db := DATABASE();

SET @isbn_column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'isbn'
);

SET @isbn_index_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'products'
      AND INDEX_NAME = 'products_isbn_index'
);

SET @sql := IF(
    @isbn_column_exists > 0 AND @isbn_index_exists = 0,
    'ALTER TABLE `products` ADD INDEX `products_isbn_index` (`isbn`)',
    'SELECT ''products_isbn_index already exists or products.isbn is missing'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
