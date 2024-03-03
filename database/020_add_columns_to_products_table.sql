ALTER TABLE `products`
    ADD COLUMN `special_lock` TINYINT(1) NULL DEFAULT '0' AFTER `special_to`;
