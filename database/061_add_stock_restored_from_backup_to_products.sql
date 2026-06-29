SET @stock_restored_column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'stock_restored_from_backup'
);

SET @add_stock_restored_column_sql := IF(
    @stock_restored_column_exists = 0,
    'ALTER TABLE `products` ADD COLUMN `stock_restored_from_backup` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `delivery_24h`',
    'SELECT ''products.stock_restored_from_backup already exists'' AS info'
);

PREPARE add_stock_restored_column_stmt FROM @add_stock_restored_column_sql;
EXECUTE add_stock_restored_column_stmt;
DEALLOCATE PREPARE add_stock_restored_column_stmt;
