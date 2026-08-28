-- Mailchimp e-commerce conversion tracking for Zuzi.
-- Safe to run more than once on MySQL/MariaDB.

SET @column_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'checkout_processed_at'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `checkout_processed_at` TIMESTAMP NULL DEFAULT NULL AFTER `printed`',
    'SELECT ''orders.checkout_processed_at already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Queue accepted orders from today's rollout window. Unfinished, cancelled,
-- refunded and declined orders are intentionally excluded.
UPDATE `orders`
SET `checkout_processed_at` = `created_at`
WHERE `checkout_processed_at` IS NULL
  AND `created_at` >= '2026-08-28 00:00:00'
    AND `order_status_id` IN (1, 2, 3, 4, 9, 10, 11);

SET @column_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'mailchimp_campaign_id'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `mailchimp_campaign_id` VARCHAR(100) NULL AFTER `checkout_processed_at`',
    'SELECT ''orders.mailchimp_campaign_id already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'mailchimp_ecommerce_synced_at'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `mailchimp_ecommerce_synced_at` TIMESTAMP NULL DEFAULT NULL AFTER `mailchimp_campaign_id`',
    'SELECT ''orders.mailchimp_ecommerce_synced_at already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'mailchimp_ecommerce_financial_status'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `mailchimp_ecommerce_financial_status` VARCHAR(20) NULL AFTER `mailchimp_ecommerce_synced_at`',
    'SELECT ''orders.mailchimp_ecommerce_financial_status already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'mailchimp_ecommerce_last_attempt_at'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `mailchimp_ecommerce_last_attempt_at` TIMESTAMP NULL DEFAULT NULL AFTER `mailchimp_ecommerce_financial_status`',
    'SELECT ''orders.mailchimp_ecommerce_last_attempt_at already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @column_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'orders' AND COLUMN_NAME = 'mailchimp_ecommerce_last_error'
);
SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `mailchimp_ecommerce_last_error` TEXT NULL AFTER `mailchimp_ecommerce_last_attempt_at`',
    'SELECT ''orders.mailchimp_ecommerce_last_error already exists'' AS message'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
