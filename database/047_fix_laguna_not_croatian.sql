-- Svi publisheri koji sadrze "Laguna" ne smiju ostati Hrvatski.
-- Ako je godina prije 1990 => Hrvatskosrbski
-- Inace => Srpski
--
-- Ovaj patch je siri od 044 i 045 jer vise ne ovisi o origin = Beograd.
-- Dira samo zapise koji su jos uvijek language = 'Hrvatski'.

START TRANSACTION;

UPDATE `products` p
JOIN `publishers` pub ON pub.`id` = p.`publisher_id`
SET
    p.`language` = CASE
        WHEN TRIM(COALESCE(p.`year`, '')) REGEXP '^[0-9]{4}$'
             AND CAST(TRIM(p.`year`) AS UNSIGNED) < 1990
            THEN 'Hrvatskosrbski'
        ELSE 'Srpski'
    END,
    p.`updated_at` = NOW()
WHERE LOWER(pub.`title`) LIKE '%laguna%'
  AND p.`language` = 'Hrvatski';

COMMIT;
