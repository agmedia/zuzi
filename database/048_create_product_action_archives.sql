-- Live patch: arhiva obrisanih promo akcija.
-- Pokrenuti na live bazi prije koristenja gumba "Obrisi istekle kodove".

CREATE TABLE IF NOT EXISTS `product_action_archives` (
    `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    `product_action_id` bigint(20) unsigned DEFAULT NULL,
    `title` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `type` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `discount` int(11) NOT NULL DEFAULT 0,
    `group` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `links` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `date_start` datetime DEFAULT NULL,
    `date_end` datetime DEFAULT NULL,
    `data` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `coupon` varchar(191) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
    `quantity` tinyint(1) NOT NULL DEFAULT 0,
    `lock` tinyint(1) NOT NULL DEFAULT 0,
    `status` tinyint(1) NOT NULL DEFAULT 0,
    `archived_at` timestamp NULL DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `product_action_archives_product_action_id_unique` (`product_action_id`),
    KEY `product_action_archives_group_index` (`group`),
    KEY `product_action_archives_coupon_index` (`coupon`),
    KEY `product_action_archives_archived_at_index` (`archived_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Oznaci Laravel migraciju kao izvrsenu ako koristite artisan migrate i na liveu.
INSERT INTO `migrations` (`migration`, `batch`)
SELECT `migration_name`, `batch_no`
FROM (
    SELECT
        '2026_05_22_090000_create_product_action_archives_table' AS `migration_name`,
        COALESCE(MAX(`batch`), 0) + 1 AS `batch_no`
    FROM `migrations`
) AS `next_batch`
WHERE NOT EXISTS (
    SELECT 1
    FROM (
        SELECT `migration`
        FROM `migrations`
    ) AS `existing_migrations`
    WHERE `existing_migrations`.`migration` = `next_batch`.`migration_name`
);
