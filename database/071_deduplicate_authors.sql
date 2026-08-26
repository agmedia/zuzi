-- Produkcija / phpMyAdmin (MySQL 5.7+): spoji duple autore, prevezi artikle
-- i trajno sprijeci isti normalizirani naziv.
--
-- Normalizacija spremljenog, potpunog imena ista je kao u aplikaciji: Unicode
-- razmaci postaju jedan obicni razmak, rubni razmaci se uklanjaju i naziv se
-- pretvara u mala slova. Interpunkcija (ukljucujuci zarez) i dijakritici se NE
-- uklanjaju. Sam importer odabire prvog autora prije nego sto spremi novi red.
--
-- Skripta je namjerno jednokratna. Prije pokretanja provjerite da stupac
-- authors.normalized_title jos ne postoji. Ne cita sistemske tablice.

-- Cijeli authors i sve promijenjene products.author_id veze ostaju u trajnim
-- backup tablicama. INSERT IGNORE ne prepisuje vec spremljene izvorne retke.
CREATE TABLE IF NOT EXISTS `_backup_authors_before_dedup_20260826` LIKE `authors`;
INSERT IGNORE INTO `_backup_authors_before_dedup_20260826`
SELECT * FROM `authors`;

CREATE TABLE IF NOT EXISTS `_backup_product_authors_before_dedup_20260826` (
    `product_id` BIGINT UNSIGNED NOT NULL,
    `author_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`product_id`),
    KEY `backup_product_authors_author_id` (`author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TEMPORARY TABLE IF EXISTS `tmp_author_normalized_keys`;
CREATE TEMPORARY TABLE `tmp_author_normalized_keys` (
    `author_id` BIGINT UNSIGNED NOT NULL,
    `full_normalized_title` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    `normalized_title` VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL,
    PRIMARY KEY (`author_id`)
) ENGINE=InnoDB;

INSERT INTO `tmp_author_normalized_keys` (`author_id`, `full_normalized_title`)
SELECT `id`, `title`
FROM `authors`;

-- NFC oblici koji postoje u trenutnom katalogu. MySQL 5.7 nema opcu NFC
-- funkciju, zato se poznate rastavljene latinske kombinacije sastave izravno.
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(
    `full_normalized_title`, CONVERT(0x43CC8C USING utf8mb4), CONVERT(0xC48C USING utf8mb4)
);
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(
    `full_normalized_title`, CONVERT(0x63CC8C USING utf8mb4), CONVERT(0xC48D USING utf8mb4)
);
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(
    `full_normalized_title`, CONVERT(0x43CC81 USING utf8mb4), CONVERT(0xC486 USING utf8mb4)
);
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(
    `full_normalized_title`, CONVERT(0x63CC81 USING utf8mb4), CONVERT(0xC487 USING utf8mb4)
);
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(
    `full_normalized_title`, CONVERT(0x45CC88 USING utf8mb4), CONVERT(0xC38B USING utf8mb4)
);
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(
    `full_normalized_title`, CONVERT(0x65CC88 USING utf8mb4), CONVERT(0xC3AB USING utf8mb4)
);
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(
    `full_normalized_title`, CONVERT(0x4FCC81 USING utf8mb4), CONVERT(0xC393 USING utf8mb4)
);
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(
    `full_normalized_title`, CONVERT(0x6FCC81 USING utf8mb4), CONVERT(0xC3B3 USING utf8mb4)
);

-- Ako ostane neka druga rastavljena kombinacija U+0300-U+036F, namjerni
-- duplicate-key prekida skriptu prije ALTER/UPDATE/DELETE zahvata. Tada treba
-- pokrenuti Laravel migraciju, koja ima potpuni PHP intl NFC normalizer.
DROP TEMPORARY TABLE IF EXISTS `tmp_author_nfc_guard`;
CREATE TEMPORARY TABLE `tmp_author_nfc_guard` (
    `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY
) ENGINE=InnoDB;
INSERT INTO `tmp_author_nfc_guard` (`id`) VALUES (1);
INSERT INTO `tmp_author_nfc_guard` (`id`)
SELECT 1
FROM `tmp_author_normalized_keys`
WHERE `full_normalized_title` COLLATE utf8mb4_bin REGEXP CONCAT(
    '[',
    CONVERT(0xCC80 USING utf8mb4),
    '-',
    CONVERT(0xCDAF USING utf8mb4),
    ']'
) COLLATE utf8mb4_bin
LIMIT 1;
DROP TEMPORARY TABLE `tmp_author_nfc_guard`;

-- [\p{Z}\s] znakovi koje koristi PHP normalizer.
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, CONVERT(0x09 USING utf8mb4), ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, CONVERT(0x0A USING utf8mb4), ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, CONVERT(0x0B USING utf8mb4), ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, CONVERT(0x0C USING utf8mb4), ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, CONVERT(0x0D USING utf8mb4), ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, CONVERT(0xC285 USING utf8mb4), ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, CONVERT(0xC2A0 USING utf8mb4), ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, CONVERT(0xE19A80 USING utf8mb4), ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, CONVERT(0xE28080 USING utf8mb4), ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, CONVERT(0xE28081 USING utf8mb4), ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, CONVERT(0xE28082 USING utf8mb4), ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, CONVERT(0xE28083 USING utf8mb4), ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, CONVERT(0xE28084 USING utf8mb4), ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, CONVERT(0xE28085 USING utf8mb4), ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, CONVERT(0xE28086 USING utf8mb4), ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, CONVERT(0xE28087 USING utf8mb4), ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, CONVERT(0xE28088 USING utf8mb4), ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, CONVERT(0xE28089 USING utf8mb4), ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, CONVERT(0xE2808A USING utf8mb4), ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, CONVERT(0xE280A8 USING utf8mb4), ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, CONVERT(0xE280A9 USING utf8mb4), ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, CONVERT(0xE280AF USING utf8mb4), ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, CONVERT(0xE2819F USING utf8mb4), ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, CONVERT(0xE38080 USING utf8mb4), ' ');

-- Osam prolaza pokriva i cijeli VARCHAR(191) ispunjen razmacima.
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, '  ', ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, '  ', ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, '  ', ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, '  ', ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, '  ', ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, '  ', ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, '  ', ' ');
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = REPLACE(`full_normalized_title`, '  ', ' ');

-- PHP mb_strtolower pretvara U+0130 u "i" + U+0307; MySQL 5.7 ga inace
-- skracuje samo na "i", zato tu jedinu ekspanziju napravimo prije LOWER().
UPDATE `tmp_author_normalized_keys`
SET `full_normalized_title` = LOWER(
    REPLACE(
        TRIM(`full_normalized_title`),
        CONVERT(0xC4B0 USING utf8mb4),
        CONVERT(0x69CC87 USING utf8mb4)
    ) COLLATE utf8mb4_unicode_ci
);

-- authors.title je VARCHAR(191), ali lowercase u rijetkim slucajevima moze
-- narasti. Isti overflow format koristi aplikacijski AuthorResolver.
UPDATE `tmp_author_normalized_keys`
SET `normalized_title` = CASE
    WHEN CHAR_LENGTH(`full_normalized_title`) <= 191
        THEN `full_normalized_title`
    ELSE CONCAT(
        SUBSTRING(`full_normalized_title`, 1, 126),
        ':',
        SHA2(`full_normalized_title`, 256)
    )
END;

-- Prazan authors.title prebacuje se na konfigurirani "Nepoznati autor". Fresh
-- baza bez autora smije proci; 3282 je obvezan samo kada postoji prazan kljuc.
-- Namjerni duplicate-key prekida skriptu prije ALTER/UPDATE/DELETE zahvata.
DROP TEMPORARY TABLE IF EXISTS `tmp_author_unknown_guard`;
CREATE TEMPORARY TABLE `tmp_author_unknown_guard` (
    `id` TINYINT UNSIGNED NOT NULL PRIMARY KEY
) ENGINE=InnoDB;
INSERT INTO `tmp_author_unknown_guard` (`id`) VALUES (1);
SET @author_dedup_has_blank := (
    SELECT COUNT(*)
    FROM `tmp_author_normalized_keys`
    WHERE `normalized_title` = ''
);
SET @author_dedup_has_unknown := (
    SELECT COUNT(*)
    FROM `tmp_author_normalized_keys`
    WHERE `author_id` = 3282
      AND `normalized_title` <> ''
);
INSERT INTO `tmp_author_unknown_guard` (`id`)
SELECT 1
WHERE @author_dedup_has_blank > 0
  AND @author_dedup_has_unknown = 0;
DROP TEMPORARY TABLE `tmp_author_unknown_guard`;

ALTER TABLE `authors`
    ADD COLUMN `normalized_title` VARCHAR(191)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NULL AFTER `title`;

UPDATE `authors` AS author
INNER JOIN `tmp_author_normalized_keys` AS normalized
    ON normalized.`author_id` = author.`id`
SET author.`normalized_title` = normalized.`normalized_title`;

DROP TEMPORARY TABLE `tmp_author_normalized_keys`;

DROP TEMPORARY TABLE IF EXISTS `tmp_author_duplicate_map`;
CREATE TEMPORARY TABLE `tmp_author_duplicate_map` (
    `duplicate_id` BIGINT UNSIGNED NOT NULL,
    `canonical_id` BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (`duplicate_id`),
    KEY `tmp_author_duplicate_map_canonical_id` (`canonical_id`)
) ENGINE=InnoDB;

INSERT INTO `tmp_author_duplicate_map` (`duplicate_id`, `canonical_id`)
SELECT duplicate_author.`id`, duplicate_group.`canonical_id`
FROM `authors` AS duplicate_author
INNER JOIN (
    SELECT
        duplicate_candidate.`normalized_title`,
        CASE
            WHEN duplicate_candidate.`normalized_title` = '' THEN 3282
            WHEN MAX(duplicate_candidate.`id` = 3282) = 1 THEN 3282
            ELSE CAST(SUBSTRING_INDEX(GROUP_CONCAT(
                duplicate_candidate.`id` ORDER BY
                    COALESCE(product_links.`product_count`, 0) DESC,
                    duplicate_candidate.`status` DESC,
                    duplicate_candidate.`id` ASC
                SEPARATOR ','
            ), ',', 1) AS UNSIGNED)
        END AS `canonical_id`,
        COUNT(*) AS `author_count`
    FROM `authors` AS duplicate_candidate
    LEFT JOIN (
        SELECT `author_id`, COUNT(*) AS `product_count`
        FROM `products`
        GROUP BY `author_id`
    ) AS product_links
        ON product_links.`author_id` = duplicate_candidate.`id`
    GROUP BY duplicate_candidate.`normalized_title`
    HAVING COUNT(*) > 1 OR duplicate_candidate.`normalized_title` = ''
) AS duplicate_group
    ON duplicate_group.`normalized_title` = duplicate_author.`normalized_title`
WHERE duplicate_author.`id` <> duplicate_group.`canonical_id`;

START TRANSACTION;

INSERT IGNORE INTO `_backup_product_authors_before_dedup_20260826`
    (`product_id`, `author_id`)
SELECT product.`id`, product.`author_id`
FROM `products` AS product
INNER JOIN `tmp_author_duplicate_map` AS duplicate_map
    ON duplicate_map.`duplicate_id` = product.`author_id`;

UPDATE `products` AS product
INNER JOIN `tmp_author_duplicate_map` AS duplicate_map
    ON duplicate_map.`duplicate_id` = product.`author_id`
SET product.`author_id` = duplicate_map.`canonical_id`;

DELETE duplicate_author
FROM `authors` AS duplicate_author
INNER JOIN `tmp_author_duplicate_map` AS duplicate_map
    ON duplicate_map.`duplicate_id` = duplicate_author.`id`;

COMMIT;

DROP TEMPORARY TABLE `tmp_author_duplicate_map`;

ALTER TABLE `authors`
    MODIFY COLUMN `normalized_title` VARCHAR(191)
        CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
    ADD UNIQUE INDEX `authors_normalized_title_unique` (`normalized_title`);

-- Zavrsna kontrola: prvi upit mora vratiti 0 redaka, drugi tocno jedan indeks,
-- treci id=3282, a cetvrti 0 nepostojecih author veza.
SELECT `normalized_title`, COUNT(*) AS `author_count`
FROM `authors`
GROUP BY `normalized_title`
HAVING COUNT(*) > 1;

SHOW INDEX FROM `authors`
WHERE `Key_name` = 'authors_normalized_title_unique';

SELECT `id`, `title`, `normalized_title`
FROM `authors`
WHERE `id` = 3282;

SELECT COUNT(*) AS `orphan_product_author_links`
FROM `products` AS product
LEFT JOIN `authors` AS author ON author.`id` = product.`author_id`
WHERE product.`author_id` > 0
  AND author.`id` IS NULL;

-- RUCNI POVRATAK (pokrenuti samo ako zelite ponistiti deduplikaciju):
-- ALTER TABLE `authors`
--     DROP INDEX `authors_normalized_title_unique`,
--     DROP COLUMN `normalized_title`;
-- INSERT IGNORE INTO `authors`
-- SELECT * FROM `_backup_authors_before_dedup_20260826`;
-- UPDATE `products` AS product
-- INNER JOIN `_backup_product_authors_before_dedup_20260826` AS backup_link
--     ON backup_link.`product_id` = product.`id`
-- INNER JOIN `authors` AS original_author
--     ON original_author.`id` = backup_link.`author_id`
-- SET product.`author_id` = backup_link.`author_id`;
