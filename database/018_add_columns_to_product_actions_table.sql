ALTER TABLE `product_actions`
    ADD COLUMN `coupon` VARCHAR(15) NULL DEFAULT NULL AFTER `data`,
    ADD COLUMN `quantity` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `logged`,
    CHANGE COLUMN `badge` `data` TEXT NULL DEFAULT NULL ;