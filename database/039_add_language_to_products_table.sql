-- Live patch za products.language
-- Dodaje nullable language kolonu ako jos ne postoji.
--
-- Napomena:
-- `products` ima FULLTEXT index (`ft_products_search`), pa MySQL za ADD COLUMN
-- ne moze koristiti ALGORITHM=INSTANT i radi rebuild tablice.
-- Ako baza opet vrati #1114 "The table is full", problem je slobodan disk/tmp prostor,
-- a ne sama sintaksa ovog patcha.

SET @has_language := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'products'
      AND COLUMN_NAME = 'language'
);

SET @sql := IF(
    @has_language = 0,
    'ALTER TABLE `products` ADD COLUMN `language` VARCHAR(191) NULL',
    'SELECT ''products.language already exists'' AS info'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
