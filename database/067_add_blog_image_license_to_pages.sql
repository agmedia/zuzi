-- Live patch za HTML licencu/atribuciju glavne slike blog članka.
-- Skripta je idempotentna i može se sigurno ponovno pokrenuti.

SET @image_license_column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'pages'
      AND COLUMN_NAME = 'image_license'
);

SET @image_license_sql := IF(
    @image_license_column_exists = 0,
    'ALTER TABLE `pages` ADD COLUMN `image_license` LONGTEXT NULL AFTER `image`',
    'SELECT ''pages.image_license already exists'' AS message'
);

PREPARE image_license_statement FROM @image_license_sql;
EXECUTE image_license_statement;
DEALLOCATE PREPARE image_license_statement;
