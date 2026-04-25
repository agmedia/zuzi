-- Laguna d.o.o. / laguna-doo + Beograd ne smije ostati Hrvatski.
-- Za moderne ili nepoznate godine postavlja Srpski.
-- Za godine prije 1990 postavlja Hrvatskosrbski.

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
WHERE pub.`slug` = 'laguna-doo'
  AND UPPER(TRIM(COALESCE(p.`origin`, ''))) = 'BEOGRAD'
  AND p.`language` = 'Hrvatski';

COMMIT;
