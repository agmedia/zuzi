CREATE TABLE IF NOT EXISTS `account_notice_mail_logs` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `user_id` bigint unsigned NOT NULL,
    `email` varchar(255) NOT NULL,
    `notice_hash` char(40) NOT NULL,
    `notice_title` varchar(255) NULL,
    `sent_at` timestamp NULL,
    `created_at` timestamp NULL,
    `updated_at` timestamp NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `account_notice_mail_logs_user_hash_unique` (`user_id`, `notice_hash`),
    KEY `account_notice_mail_logs_notice_hash_index` (`notice_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
