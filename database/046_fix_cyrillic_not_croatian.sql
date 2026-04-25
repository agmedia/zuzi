-- Cirilica ne smije ostati Hrvatski.
-- Ako je godina prije 1990 => Hrvatskosrbski
-- Inace => Srpski
--
-- Patch je siguran za pustanje nakon 042/043/044/045 jer dira
-- samo zapise koji su jos uvijek language = 'Hrvatski' i letter = 'Ćirilica'.

START TRANSACTION;

UPDATE `products`
SET
    `language` = CASE
        WHEN TRIM(COALESCE(`year`, '')) REGEXP '^[0-9]{4}$'
             AND CAST(TRIM(`year`) AS UNSIGNED) < 1990
            THEN 'Hrvatskosrbski'
        ELSE 'Srpski'
    END,
    `updated_at` = NOW()
WHERE `letter` = 'Ćirilica'
  AND `language` = 'Hrvatski';

COMMIT;
