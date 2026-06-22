-- Polja za Pelion dohvat narudzbi i povrat statusa racuna.
-- Safe to run more than once on MySQL/MariaDB.

SET @db := DATABASE();

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'pelion_status'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `pelion_status` VARCHAR(32) NULL DEFAULT NULL AFTER `invoice`',
    'SELECT ''orders.pelion_status already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'pelion_invoice_number'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `pelion_invoice_number` VARCHAR(191) NULL DEFAULT NULL AFTER `pelion_status`',
    'SELECT ''orders.pelion_invoice_number already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'pelion_invoice_date'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `pelion_invoice_date` DATE NULL DEFAULT NULL AFTER `pelion_invoice_number`',
    'SELECT ''orders.pelion_invoice_date already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'pelion_imported_at'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `pelion_imported_at` TIMESTAMP NULL DEFAULT NULL AFTER `pelion_invoice_date`',
    'SELECT ''orders.pelion_imported_at already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'pelion_invoiced_at'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `pelion_invoiced_at` TIMESTAMP NULL DEFAULT NULL AFTER `pelion_imported_at`',
    'SELECT ''orders.pelion_invoiced_at already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'pelion_error'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `pelion_error` TEXT NULL DEFAULT NULL AFTER `pelion_invoiced_at`',
    'SELECT ''orders.pelion_error already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'orders'
      AND INDEX_NAME = 'orders_pelion_status_index'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE `orders` ADD INDEX `orders_pelion_status_index` (`pelion_status`)',
    'SELECT ''orders_pelion_status_index already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'orders'
      AND INDEX_NAME = 'orders_pelion_invoice_number_index'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE `orders` ADD INDEX `orders_pelion_invoice_number_index` (`pelion_invoice_number`)',
    'SELECT ''orders_pelion_invoice_number_index already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'orders'
      AND INDEX_NAME = 'orders_pelion_imported_at_index'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE `orders` ADD INDEX `orders_pelion_imported_at_index` (`pelion_imported_at`)',
    'SELECT ''orders_pelion_imported_at_index already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'orders'
      AND INDEX_NAME = 'orders_pelion_invoiced_at_index'
);
SET @sql := IF(@index_exists = 0,
    'ALTER TABLE `orders` ADD INDEX `orders_pelion_invoiced_at_index` (`pelion_invoiced_at`)',
    'SELECT ''orders_pelion_invoiced_at_index already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
