-- Add an index for cart lookups by logged-in user.
-- Safe to run more than once on MySQL/MariaDB.
SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'carts'
      AND INDEX_NAME = 'carts_user_id_index'
);

SET @sql := IF(
    @index_exists = 0,
    'ALTER TABLE `carts` ADD INDEX `carts_user_id_index` (`user_id`)',
    'SELECT ''carts_user_id_index already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
