-- Tracks whether the shipment/tracking email was sent to the customer.
-- Safe to run more than once on MySQL/MariaDB.

SET @column_exists := (
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'shipping_tracking_email_sent_at'
);
SET @sql := IF(@column_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `shipping_tracking_email_sent_at` TIMESTAMP NULL DEFAULT NULL AFTER `shipping_tracking_updated_at`',
    'SELECT ''orders.shipping_tracking_email_sent_at already exists'' AS message'
);
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
