ALTER TABLE `orders`
    ADD COLUMN `invoice` VARCHAR(191) NULL DEFAULT NULL AFTER `order_status_id`,
    ADD COLUMN `payment_card` VARCHAR(191) NULL DEFAULT NULL AFTER `payment_code`,
    ADD COLUMN `payment_installment` INT(2) UNSIGNED NULL DEFAULT 0 AFTER `payment_card`;