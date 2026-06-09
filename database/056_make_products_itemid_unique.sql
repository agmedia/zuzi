-- Pretvori postojeci products.itemid u unique polje.
-- Nemapirani stari artikli s vrijednoscu 0 postaju NULL kako unique index moze proci.
SET @db := DATABASE();

SET @itemid_column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'itemid'
);

SET @plain_index_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'products'
      AND INDEX_NAME = 'products_itemid_index'
);

SET @unique_index_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'products'
      AND INDEX_NAME = 'products_itemid_unique'
);

SET @sql := IF(
    @itemid_column_exists > 0 AND @plain_index_exists > 0,
    'ALTER TABLE `products` DROP INDEX `products_itemid_index`',
    'SELECT ''products_itemid_index missing or itemid missing'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    @itemid_column_exists > 0,
    'ALTER TABLE `products` MODIFY `itemid` BIGINT UNSIGNED NULL',
    'SELECT ''products.itemid missing'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    @itemid_column_exists > 0,
    'UPDATE `products` SET `itemid` = NULL WHERE `itemid` = 0',
    'SELECT ''products.itemid missing'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @sql := IF(
    @itemid_column_exists > 0 AND @unique_index_exists = 0,
    'ALTER TABLE `products` ADD UNIQUE INDEX `products_itemid_unique` (`itemid`)',
    'SELECT ''products_itemid_unique already exists or itemid missing'' AS info'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
