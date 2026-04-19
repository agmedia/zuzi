-- Live patch za product_actions:
-- dodaje nedostajuće stupce koje koristi novi modul kombiniranih kategorija.

SET @db := DATABASE();

DROP PROCEDURE IF EXISTS add_column_if_missing;
DELIMITER $$
CREATE PROCEDURE add_column_if_missing(
    IN p_table  VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_ddl    TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @db
          AND TABLE_NAME   = p_table
          AND COLUMN_NAME  = p_column
    ) THEN
        SET @sql := p_ddl;
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

DROP PROCEDURE IF EXISTS add_index_if_missing;
DELIMITER $$
CREATE PROCEDURE add_index_if_missing(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_ddl   TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = @db
          AND TABLE_NAME   = p_table
          AND INDEX_NAME   = p_index
    ) THEN
        SET @sql := p_ddl;
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

CALL add_column_if_missing(
    'product_actions',
    'data',
    'ALTER TABLE `product_actions` ADD COLUMN `data` TEXT NULL AFTER `min_cart`'
);

CALL add_column_if_missing(
    'product_actions',
    'coupon',
    'ALTER TABLE `product_actions` ADD COLUMN `coupon` VARCHAR(191) NULL AFTER `data`'
);

CALL add_column_if_missing(
    'product_actions',
    'quantity',
    'ALTER TABLE `product_actions` ADD COLUMN `quantity` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `coupon`'
);

CALL add_column_if_missing(
    'product_actions',
    'lock',
    'ALTER TABLE `product_actions` ADD COLUMN `lock` TINYINT(1) UNSIGNED NOT NULL DEFAULT 0 AFTER `quantity`'
);

CALL add_index_if_missing(
    'product_actions',
    'product_actions_coupon_index',
    'ALTER TABLE `product_actions` ADD INDEX `product_actions_coupon_index` (`coupon`)'
);

DROP PROCEDURE IF EXISTS add_column_if_missing;
DROP PROCEDURE IF EXISTS add_index_if_missing;
