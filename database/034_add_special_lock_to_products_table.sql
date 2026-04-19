-- Live patch za products:
-- dodaje special_lock koji koristi batch sync akcija.

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

CALL add_column_if_missing(
    'products',
    'special_lock',
    'ALTER TABLE `products` ADD COLUMN `special_lock` TINYINT(1) NOT NULL DEFAULT 0 AFTER `special_to`'
);

DROP PROCEDURE IF EXISTS add_column_if_missing;
