-- Dodaje novu ISBN kolonu na products tablicu.
ALTER TABLE `products`
    ADD COLUMN `isbn` VARCHAR(32) NULL AFTER `ean`;

-- Backfill iz meta_description za retke gdje ISBN jos nije popunjen.
UPDATE `products`
SET `isbn` = TRIM(
    SUBSTRING_INDEX(
        SUBSTRING(
            REPLACE(`meta_description`, '\r', ''),
            LOCATE('ISBN:', REPLACE(`meta_description`, '\r', '')) + CHAR_LENGTH('ISBN:')
        ),
        '\n',
        1
    )
)
WHERE (`isbn` IS NULL OR `isbn` = '')
  AND LOCATE('ISBN:', REPLACE(`meta_description`, '\r', '')) > 0;

-- Primjer ce upisati i vrijednosti poput:
-- ISBN:9789531439282
