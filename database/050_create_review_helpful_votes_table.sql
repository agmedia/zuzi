CREATE TABLE IF NOT EXISTS `review_helpful_votes` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `review_id` bigint(20) unsigned NOT NULL,
    `user_id` bigint(20) unsigned NOT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `review_helpful_votes_review_id_user_id_unique` (`review_id`, `user_id`),
    KEY `review_helpful_votes_user_id_index` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
