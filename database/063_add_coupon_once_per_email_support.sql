-- Dodaje zasebno pravilo za jednokratno korištenje kupona po email adresi.
-- Sigurno je pokrenuti skriptu više puta.

SET @db := DATABASE();

SET @sql := IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @db
          AND TABLE_NAME = 'product_actions'
          AND COLUMN_NAME = 'once_per_email'
    ),
    'SET @coupon_usage_noop := 0',
    'ALTER TABLE `product_actions` ADD COLUMN `once_per_email` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `quantity`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @db
          AND TABLE_NAME = 'orders'
          AND COLUMN_NAME = 'coupon_code'
    ),
    'SET @coupon_usage_noop := 0',
    'ALTER TABLE `orders` ADD COLUMN `coupon_code` VARCHAR(191) NULL AFTER `total`'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql := IF(
    EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = @db
          AND TABLE_NAME = 'orders'
          AND INDEX_NAME = 'orders_coupon_code_index'
    ),
    'SET @coupon_usage_noop := 0',
    'ALTER TABLE `orders` ADD INDEX `orders_coupon_code_index` (`coupon_code`)'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
