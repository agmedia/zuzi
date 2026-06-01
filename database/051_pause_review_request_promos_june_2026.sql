-- Live helper for the June 2026 review-request promo pause.
-- The code change prevents new review-request coupons from being generated
-- from 2026-06-01 00:00:00 until 2026-07-01 00:00:00.

-- 1) Ensure the review-request command can mark processed orders.
-- Safe to run more than once on MySQL/MariaDB.
SET @column_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND COLUMN_NAME = 'review_request_sent_at'
);

SET @sql := IF(
    @column_exists = 0,
    'ALTER TABLE `orders` ADD COLUMN `review_request_sent_at` TIMESTAMP NULL DEFAULT NULL AFTER `printed`',
    'SELECT ''orders.review_request_sent_at already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @index_exists := (
    SELECT COUNT(*)
    FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'orders'
      AND INDEX_NAME = 'orders_review_request_sent_at_index'
);

SET @sql := IF(
    @index_exists = 0,
    'ALTER TABLE `orders` ADD INDEX `orders_review_request_sent_at_index` (`review_request_sent_at`)',
    'SELECT ''orders_review_request_sent_at_index already exists'' AS message'
);

PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2) Optional: disable already-issued active review-request coupons.
-- This does not touch BOGO actions; it targets only DOJAM review coupons.
-- Run the SELECT first to preview affected rows.
SELECT
    id,
    title,
    coupon,
    discount,
    date_end,
    status
FROM product_actions
WHERE `group` = 'total'
  AND status = 1
  AND coupon LIKE 'DOJAM20-%'
  AND (
      title LIKE 'Promo za dojam narudzbe #%'
      OR title LIKE 'Promo za komentar narudzbe #%'
  );

-- Uncomment and run only if you also want existing DOJAM20 review coupons inactive.
-- UPDATE product_actions
-- SET status = 0,
--     updated_at = NOW()
-- WHERE `group` = 'total'
--   AND status = 1
--   AND coupon LIKE 'DOJAM20-%'
--   AND (
--       title LIKE 'Promo za dojam narudzbe #%'
--       OR title LIKE 'Promo za komentar narudzbe #%'
--   );
