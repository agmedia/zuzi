-- Siguran cleanup ISBN podataka.
-- 1) Dodaje products.isbn ako kolona jos ne postoji
-- 2) Normalizira ISBN vrijednosti (mice razmake, crtice i ostali sum)
-- 3) Zadrzava samo stvarno valjane ISBN-10 / ISBN-13 vrijednosti
-- 4) Ako je products.isbn nevaljan ili prazan, uzima products.ean samo ako je to stvarno valjan ISBN
-- 5) Sve ostalo nulira u products.isbn da se makne smece

SET @isbn_column_exists := (
    SELECT COUNT(*)
    FROM `INFORMATION_SCHEMA`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE()
      AND `TABLE_NAME` = 'products'
      AND `COLUMN_NAME` = 'isbn'
);

SET @add_isbn_sql := IF(
    @isbn_column_exists = 0,
    'ALTER TABLE `products` ADD COLUMN `isbn` VARCHAR(32) NULL AFTER `ean`',
    'SELECT ''products.isbn already exists'' AS info'
);

PREPARE add_isbn_stmt FROM @add_isbn_sql;
EXECUTE add_isbn_stmt;
DEALLOCATE PREPARE add_isbn_stmt;

DROP FUNCTION IF EXISTS `normalize_isbn`;
DROP FUNCTION IF EXISTS `is_valid_isbn10`;
DROP FUNCTION IF EXISTS `is_valid_isbn13`;
DROP FUNCTION IF EXISTS `is_valid_isbn`;

DELIMITER $$

CREATE FUNCTION `normalize_isbn`(`value_text` VARCHAR(255))
RETURNS VARCHAR(32)
DETERMINISTIC
BEGIN
    DECLARE cleaned VARCHAR(32);

    SET cleaned = UPPER(IFNULL(TRIM(`value_text`), ''));
    SET cleaned = REPLACE(cleaned, 'ISBN-13', '');
    SET cleaned = REPLACE(cleaned, 'ISBN13', '');
    SET cleaned = REPLACE(cleaned, 'ISBN-10', '');
    SET cleaned = REPLACE(cleaned, 'ISBN10', '');
    SET cleaned = REPLACE(cleaned, 'ISBN', '');
    SET cleaned = REPLACE(cleaned, ':', '');
    SET cleaned = REPLACE(cleaned, '-', '');
    SET cleaned = REPLACE(cleaned, ' ', '');
    SET cleaned = REPLACE(cleaned, '.', '');
    SET cleaned = REPLACE(cleaned, '/', '');
    SET cleaned = REPLACE(cleaned, CHAR(9), '');
    SET cleaned = REPLACE(cleaned, CHAR(10), '');
    SET cleaned = REPLACE(cleaned, CHAR(13), '');

    RETURN NULLIF(cleaned, '');
END$$

CREATE FUNCTION `is_valid_isbn10`(`value_text` VARCHAR(255))
RETURNS TINYINT(1)
DETERMINISTIC
BEGIN
    DECLARE isbn_value VARCHAR(32);
    DECLARE sum_value INT DEFAULT 0;
    DECLARE pos INT DEFAULT 1;
    DECLARE check_char CHAR(1);

    SET isbn_value = normalize_isbn(`value_text`);

    IF isbn_value IS NULL OR isbn_value NOT REGEXP '^[0-9]{9}[0-9X]$' THEN
        RETURN 0;
    END IF;

    WHILE pos <= 9 DO
        SET sum_value = sum_value + (CAST(SUBSTRING(isbn_value, pos, 1) AS UNSIGNED) * (11 - pos));
        SET pos = pos + 1;
    END WHILE;

    SET check_char = SUBSTRING(isbn_value, 10, 1);
    SET sum_value = sum_value + CASE
        WHEN check_char = 'X' THEN 10
        ELSE CAST(check_char AS UNSIGNED)
    END;

    RETURN IF(MOD(sum_value, 11) = 0, 1, 0);
END$$

CREATE FUNCTION `is_valid_isbn13`(`value_text` VARCHAR(255))
RETURNS TINYINT(1)
DETERMINISTIC
BEGIN
    DECLARE isbn_value VARCHAR(32);
    DECLARE sum_value INT DEFAULT 0;
    DECLARE pos INT DEFAULT 1;
    DECLARE expected_check INT;

    SET isbn_value = normalize_isbn(`value_text`);

    IF isbn_value IS NULL OR isbn_value NOT REGEXP '^(978|979)[0-9]{10}$' THEN
        RETURN 0;
    END IF;

    WHILE pos <= 12 DO
        SET sum_value = sum_value + (
            CAST(SUBSTRING(isbn_value, pos, 1) AS UNSIGNED) *
            CASE WHEN MOD(pos, 2) = 1 THEN 1 ELSE 3 END
        );
        SET pos = pos + 1;
    END WHILE;

    SET expected_check = MOD(10 - MOD(sum_value, 10), 10);

    RETURN IF(expected_check = CAST(SUBSTRING(isbn_value, 13, 1) AS UNSIGNED), 1, 0);
END$$

CREATE FUNCTION `is_valid_isbn`(`value_text` VARCHAR(255))
RETURNS TINYINT(1)
DETERMINISTIC
BEGIN
    DECLARE isbn_value VARCHAR(32);

    SET isbn_value = normalize_isbn(`value_text`);

    IF isbn_value IS NULL THEN
        RETURN 0;
    END IF;

    IF CHAR_LENGTH(isbn_value) = 10 THEN
        RETURN is_valid_isbn10(isbn_value);
    END IF;

    IF CHAR_LENGTH(isbn_value) = 13 THEN
        RETURN is_valid_isbn13(isbn_value);
    END IF;

    RETURN 0;
END$$

DELIMITER ;

UPDATE `products`
SET `isbn` = CASE
    WHEN is_valid_isbn(`isbn`) = 1 THEN normalize_isbn(`isbn`)
    WHEN is_valid_isbn(`ean`) = 1 THEN normalize_isbn(`ean`)
    ELSE NULL
END;

-- Brzi izvjestaj nakon ciscenja.
SELECT
    COUNT(*) AS total_products,
    SUM(CASE WHEN `isbn` IS NOT NULL AND `isbn` <> '' THEN 1 ELSE 0 END) AS valid_isbn_saved,
    SUM(CASE WHEN `ean` IS NOT NULL AND `ean` <> '' AND is_valid_isbn(`ean`) = 1 THEN 1 ELSE 0 END) AS valid_isbn_found_in_ean,
    SUM(CASE WHEN `ean` IS NOT NULL AND `ean` <> '' AND is_valid_isbn(`ean`) = 0 THEN 1 ELSE 0 END) AS invalid_or_non_isbn_ean_skipped
FROM `products`;

-- Pocisti helper funkcije nakon runa.
DROP FUNCTION IF EXISTS `is_valid_isbn`;
DROP FUNCTION IF EXISTS `is_valid_isbn13`;
DROP FUNCTION IF EXISTS `is_valid_isbn10`;
DROP FUNCTION IF EXISTS `normalize_isbn`;
