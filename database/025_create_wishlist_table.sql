CREATE TABLE `wishlist` (
                            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                            `user_id` bigint(20) NOT NULL DEFAULT '0',
                            `email` varchar(191) COLLATE utf8mb4_unicode_ci NOT NULL,
                            `product_id` bigint(20) NOT NULL DEFAULT '0',
                            `sent` tinyint(4) NOT NULL DEFAULT '0',
                            `status` tinyint(4) NOT NULL DEFAULT '1',
                            `created_at` timestamp NULL DEFAULT NULL,
                            `updated_at` timestamp NULL DEFAULT NULL,
                            PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;