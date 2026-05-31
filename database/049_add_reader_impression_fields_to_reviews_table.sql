-- Live patch za reviews:
-- dodaje polja za bogatije dojmove citatelja.

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
    'reviews',
    'title',
    'ALTER TABLE `reviews` ADD COLUMN `title` VARCHAR(120) NULL AFTER `avatar`'
);

CALL add_column_if_missing(
    'reviews',
    'recommended_for',
    'ALTER TABLE `reviews` ADD COLUMN `recommended_for` VARCHAR(255) NULL AFTER `message`'
);

CALL add_column_if_missing(
    'reviews',
    'liked_most',
    'ALTER TABLE `reviews` ADD COLUMN `liked_most` VARCHAR(255) NULL AFTER `recommended_for`'
);

CALL add_column_if_missing(
    'reviews',
    'tags',
    'ALTER TABLE `reviews` ADD COLUMN `tags` TEXT NULL AFTER `liked_most`'
);

CALL add_column_if_missing(
    'reviews',
    'has_spoilers',
    'ALTER TABLE `reviews` ADD COLUMN `has_spoilers` TINYINT(1) NOT NULL DEFAULT 0 AFTER `tags`'
);

CALL add_column_if_missing(
    'reviews',
    'verified_purchase',
    'ALTER TABLE `reviews` ADD COLUMN `verified_purchase` TINYINT(1) NOT NULL DEFAULT 0 AFTER `has_spoilers`'
);

CALL add_column_if_missing(
    'reviews',
    'helpful_count',
    'ALTER TABLE `reviews` ADD COLUMN `helpful_count` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `verified_purchase`'
);

DROP PROCEDURE IF EXISTS add_column_if_missing;
