-- Pelion ITEMID na proizvodu. Admin validacija trazi stvarni ItemID
-- kod dodavanja/spremanja artikla, a baza cuva unique vrijednosti.
SET @db := DATABASE();

SET @itemid_column_exists := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'itemid'
);

SET @sql := IF(
    @itemid_column_exists = 0,
    'ALTER TABLE `products` ADD COLUMN `itemid` BIGINT UNSIGNED NULL AFTER `isbn`, ADD UNIQUE INDEX `products_itemid_unique` (`itemid`)',
    'SELECT ''products.itemid already exists'' AS info'
);

PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
