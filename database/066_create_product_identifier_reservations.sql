-- Live patch: automatska rezervacija polja products.sku i products.itemid.
-- Skripta je idempotentna i može se sigurno ponovno pokrenuti.

CREATE TABLE IF NOT EXISTS `product_identifier_allocation_locks` (
    `id` tinyint unsigned NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `product_identifier_allocation_locks` (`id`)
VALUES (1)
ON DUPLICATE KEY UPDATE `id` = VALUES(`id`);

CREATE TABLE IF NOT EXISTS `product_identifier_reservations` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `token` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
    `sku` bigint unsigned NOT NULL,
    `itemid` bigint unsigned NOT NULL,
    `expires_at` timestamp NOT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `product_identifier_reservations_token_unique` (`token`),
    UNIQUE KEY `product_identifier_reservations_sku_unique` (`sku`),
    UNIQUE KEY `product_identifier_reservations_itemid_unique` (`itemid`),
    KEY `product_identifier_reservations_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
