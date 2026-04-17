ALTER TABLE `orders`
    ADD COLUMN `review_request_sent_at` TIMESTAMP NULL DEFAULT NULL AFTER `printed`,
    ADD INDEX `orders_review_request_sent_at_index` (`review_request_sent_at`);
