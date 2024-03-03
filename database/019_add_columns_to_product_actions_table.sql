ALTER TABLE `product_actions`
    ADD COLUMN `lock` TINYINT(1) NULL DEFAULT '0' AFTER `quantity`;