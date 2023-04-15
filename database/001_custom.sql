ALTER TABLE `order_status`
    CHANGE COLUMN `name` `order_id` BIGINT(20) UNSIGNED NOT NULL ,
    ADD COLUMN `user_id` BIGINT(20) UNSIGNED NOT NULL AFTER `order_id`,
    CHANGE COLUMN `sort_order` `comment` TEXT NULL DEFAULT NULL , RENAME TO `order_history` ;