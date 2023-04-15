ALTER TABLE `product_images`
    ADD COLUMN `published` TINYINT(1) NOT NULL DEFAULT 1 AFTER `alt`;