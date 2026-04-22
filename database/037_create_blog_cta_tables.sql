-- Live patch za CTA blokove na blog člancima.
-- Kreira tablice za CTA blokove i njihove buttone ako još ne postoje.

CREATE TABLE IF NOT EXISTS `blog_cta_blocks` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `blog_post_id` BIGINT UNSIGNED NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `blog_cta_blocks_blog_post_id_is_active_sort_order_index` (`blog_post_id`, `is_active`, `sort_order`),
    CONSTRAINT `blog_cta_blocks_blog_post_id_foreign`
        FOREIGN KEY (`blog_post_id`) REFERENCES `pages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `blog_cta_buttons` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `cta_block_id` BIGINT UNSIGNED NOT NULL,
    `label` VARCHAR(255) NOT NULL,
    `url` VARCHAR(255) NOT NULL,
    `icon` VARCHAR(32) NULL,
    `style` VARCHAR(32) NOT NULL DEFAULT 'outline',
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `blog_cta_buttons_cta_block_id_is_active_sort_order_index` (`cta_block_id`, `is_active`, `sort_order`),
    CONSTRAINT `blog_cta_buttons_cta_block_id_foreign`
        FOREIGN KEY (`cta_block_id`) REFERENCES `blog_cta_blocks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
