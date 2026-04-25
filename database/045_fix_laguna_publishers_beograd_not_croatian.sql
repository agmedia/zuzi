-- Siri patch od 044:
-- svi publisheri ciji naziv sadrzi "Laguna" + Beograd ne smiju ostati Hrvatski.
-- Ako je godina prije 1990 => Hrvatskosrbski
-- Inace => Srpski
--
-- Ovaj patch pokriva i "Laguna" i "Laguna d.o.o."
-- i sigurno se moze pustiti i nakon 044 jer dira samo zapise koji su jos uvijek Hrvatski.

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
WHERE pub.`title` LIKE '%Laguna%'
  AND UPPER(TRIM(COALESCE(p.`origin`, ''))) = 'BEOGRAD'
  AND p.`language` = 'Hrvatski';

COMMIT;
