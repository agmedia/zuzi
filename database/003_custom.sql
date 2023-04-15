ALTER TABLE `order_history`
    ADD COLUMN `status` INT(2) NOT NULL DEFAULT 0 AFTER `user_id`;