-- Produkcija: spoji duple izdavace i sprijeci ponovno stvaranje istog naziva.
-- Duplikati se usporeduju bez obzira na velika/mala slova i rubne razmake.
-- Najstariji zapis (najmanji ID) ostaje, a svi njegovi duplikati se uklanjaju.
-- Sigurno je pokrenuti skriptu vise puta na MySQL 8 / MariaDB.

SET @db := DATABASE();

-- Trajni backup prije prve izmjene. Ponovljeno pokretanje ne prepisuje stare retke.
CREATE TABLE IF NOT EXISTS `_backup_publishers_before_dedup_20260806` LIKE `publishers`;
INSERT IGNORE INTO `_backup_publishers_before_dedup_20260806`
SELECT * FROM `publishers`;

CREATE TABLE IF NOT EXISTS `_backup_product_publishers_before_dedup_20260806` (
    `product_id` BIGINT UNSIGNED NOT NULL,
    `publisher_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`product_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TEMPORARY TABLE IF EXISTS `tmp_publisher_duplicate_map`;
CREATE TEMPORARY TABLE `tmp_publisher_duplicate_map` (
    `duplicate_id` BIGINT UNSIGNED NOT NULL,
    `canonical_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`duplicate_id`),
    KEY `tmp_publisher_duplicate_map_canonical_id` (`canonical_id`)
) ENGINE=InnoDB;

START TRANSACTION;

INSERT INTO `tmp_publisher_duplicate_map` (`duplicate_id`, `canonical_id`)
SELECT
    duplicate_publisher.`id`,
    duplicate_group.`canonical_id`
FROM `publishers` AS duplicate_publisher
INNER JOIN (
    SELECT
        LOWER(TRIM(`title`)) AS `normalized_title`,
        MIN(`id`) AS `canonical_id`
    FROM `publishers`
    GROUP BY LOWER(TRIM(`title`))
    HAVING COUNT(*) > 1
) AS duplicate_group
    ON LOWER(TRIM(duplicate_publisher.`title`)) = duplicate_group.`normalized_title`
WHERE duplicate_publisher.`id` <> duplicate_group.`canonical_id`;

-- Sacuvaj originalne veze samo za artikle koje cemo prebaciti.
INSERT IGNORE INTO `_backup_product_publishers_before_dedup_20260806`
    (`product_id`, `publisher_id`)
SELECT product.`id`, product.`publisher_id`
FROM `products` AS product
INNER JOIN `tmp_publisher_duplicate_map` AS duplicate_map
    ON duplicate_map.`duplicate_id` = product.`publisher_id`;

-- Svi artikli s duplog zapisa prelaze na izdavaca kojeg zadrzavamo.
UPDATE `products` AS product
INNER JOIN `tmp_publisher_duplicate_map` AS duplicate_map
    ON duplicate_map.`duplicate_id` = product.`publisher_id`
SET product.`publisher_id` = duplicate_map.`canonical_id`;

-- Tek nakon prebacivanja artikala obrisi duple izdavace.
DELETE duplicate_publisher
FROM `publishers` AS duplicate_publisher
INNER JOIN `tmp_publisher_duplicate_map` AS duplicate_map
    ON duplicate_map.`duplicate_id` = duplicate_publisher.`id`;

-- Nakon uklanjanja konflikata spremi nazive bez rubnih razmaka.
UPDATE `publishers`
SET `title` = TRIM(`title`)
WHERE `title` <> TRIM(`title`);

COMMIT;

-- Baza nakon ciscenja vise ne dopusta dva jednaka naziva.
SET @unique_index_exists := (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = @db
      AND TABLE_NAME = 'publishers'
      AND INDEX_NAME = 'publishers_title_unique'
);

SET @sql := IF(
    @unique_index_exists = 0,
    'ALTER TABLE `publishers` ADD UNIQUE INDEX `publishers_title_unique` (`title`)',
    'SELECT ''publishers_title_unique already exists'' AS info'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Zavrsna kontrola: ovaj upit mora vratiti 0 redaka.
SELECT
    LOWER(TRIM(`title`)) AS `normalized_title`,
    COUNT(*) AS `publisher_count`
FROM `publishers`
GROUP BY LOWER(TRIM(`title`))
HAVING COUNT(*) > 1;

DROP TEMPORARY TABLE IF EXISTS `tmp_publisher_duplicate_map`;
