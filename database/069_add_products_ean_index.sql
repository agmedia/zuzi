-- Pokrenuti samo ako završni SHOW INDEX iz 068 skripte ne vrati nijedan redak.
-- Ne čita INFORMATION_SCHEMA i radi s ograničenim phpMyAdmin korisnikom koji
-- ima ALTER ovlast nad Zuzi bazom.

ALTER TABLE `products`
    ADD INDEX `products_ean_index` (`ean`);
