-- Rucno pregledane korekcije za sumnjive proizvode trenutno upisane kao Engleski.
-- Generirano 2026-04-25.
--
-- Ostaviti kao Engleski nakon pregleda:
-- 7185  Vjekoslav Karlovčan: A survey of English grammar workbook
-- 61859 Oxenden, Clive: New English File: Intermediate: Student's Book: Six-level general English course for adults
-- 89473 Rudi Stipčić: We’ve Finally Started
--
-- Korekcije ispod su konzervativne:
-- - Beograd + ocito preveden/edukativan naslov => Srpski
-- - nejasni ili mjesoviti zapisi => NULL

START TRANSACTION;

UPDATE `products`
SET `language` = 'Srpski', `updated_at` = NOW()
WHERE `id` IN (
    17963,18503,18560,18742,59878,60004,73538,73665,74254
);

UPDATE `products`
SET `language` = NULL, `updated_at` = NOW()
WHERE `id` IN (
    16985,20832,60460,91035
);

COMMIT;
