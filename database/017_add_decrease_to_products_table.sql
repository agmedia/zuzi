ALTER TABLE `products`
    ADD COLUMN `decrease` INT(1) DEFAULT 1 AFTER `quantity`;