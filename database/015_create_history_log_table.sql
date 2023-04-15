CREATE TABLE `history_log` (
                               `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                               `user_id` bigint(20) NOT NULL,
                               `type` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
                               `target` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
                               `target_id` bigint(20) NOT NULL DEFAULT '0',
                               `title` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
                               `changes` longtext COLLATE utf8mb4_unicode_ci,
                               `old_model` longtext COLLATE utf8mb4_unicode_ci,
                               `new_model` longtext COLLATE utf8mb4_unicode_ci,
                               `badge` tinyint(4) NOT NULL DEFAULT '0',
                               `comment` text COLLATE utf8mb4_unicode_ci,
                               `created_at` timestamp NULL DEFAULT NULL,
                               `updated_at` timestamp NULL DEFAULT NULL,
                               PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
