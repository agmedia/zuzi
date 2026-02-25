-- Katalog indeks optimizacija (products, product_category, product_images)
-- Napomena: skripta dodaje indekse samo ako ne postoje.

SET @db := DATABASE();

DROP PROCEDURE IF EXISTS add_index_if_missing;
DELIMITER $$
CREATE PROCEDURE add_index_if_missing(
    IN p_table VARCHAR(64),
    IN p_index VARCHAR(64),
    IN p_ddl   TEXT
)
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.STATISTICS
        WHERE TABLE_SCHEMA = @db
          AND TABLE_NAME   = p_table
          AND INDEX_NAME   = p_index
    ) THEN
        SET @sql := p_ddl;
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
    END IF;
END$$
DELIMITER ;

DROP PROCEDURE IF EXISTS add_index_if_column_exists;
DELIMITER $$
CREATE PROCEDURE add_index_if_column_exists(
    IN p_table  VARCHAR(64),
    IN p_column VARCHAR(64),
    IN p_index  VARCHAR(64),
    IN p_ddl    TEXT
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = @db
          AND TABLE_NAME   = p_table
          AND COLUMN_NAME  = p_column
    ) THEN
        CALL add_index_if_missing(p_table, p_index, p_ddl);
    END IF;
END$$
DELIMITER ;

-- products: glavni front/admin filteri i sortovi
CALL add_index_if_missing(
    'products',
    'idx_products_status_quantity_created',
    'ALTER TABLE `products` ADD INDEX `idx_products_status_quantity_created` (`status`, `quantity`, `created_at`)'
);

CALL add_index_if_missing(
    'products',
    'idx_products_status_quantity_updated',
    'ALTER TABLE `products` ADD INDEX `idx_products_status_quantity_updated` (`status`, `quantity`, `updated_at`)'
);

CALL add_index_if_missing(
    'products',
    'idx_products_status_quantity_viewed',
    'ALTER TABLE `products` ADD INDEX `idx_products_status_quantity_viewed` (`status`, `quantity`, `viewed`)'
);

CALL add_index_if_column_exists(
    'products',
    'topponuda',
    'idx_products_status_topponuda_updated',
    'ALTER TABLE `products` ADD INDEX `idx_products_status_topponuda_updated` (`status`, `topponuda`, `updated_at`)'
);

CALL add_index_if_missing(
    'products',
    'idx_products_author_status_quantity',
    'ALTER TABLE `products` ADD INDEX `idx_products_author_status_quantity` (`author_id`, `status`, `quantity`)'
);

CALL add_index_if_missing(
    'products',
    'idx_products_publisher_status_quantity',
    'ALTER TABLE `products` ADD INDEX `idx_products_publisher_status_quantity` (`publisher_id`, `status`, `quantity`)'
);

CALL add_index_if_missing(
    'products',
    'idx_products_special_window',
    'ALTER TABLE `products` ADD INDEX `idx_products_special_window` (`special`, `special_from`, `special_to`)'
);

-- product_category: whereHas categories + join prema products
CALL add_index_if_missing(
    'product_category',
    'idx_product_category_category_product',
    'ALTER TABLE `product_category` ADD INDEX `idx_product_category_category_product` (`category_id`, `product_id`)'
);

CALL add_index_if_missing(
    'product_category',
    'idx_product_category_product_category',
    'ALTER TABLE `product_category` ADD INDEX `idx_product_category_product_category` (`product_id`, `category_id`)'
);

-- product_images: dohvat i sortiranje po product_id/sort_order
CALL add_index_if_missing(
    'product_images',
    'idx_product_images_product_sort',
    'ALTER TABLE `product_images` ADD INDEX `idx_product_images_product_sort` (`product_id`, `sort_order`)'
);

DROP PROCEDURE IF EXISTS add_index_if_missing;
DROP PROCEDURE IF EXISTS add_index_if_column_exists;

-- Preporuka nakon dodavanja indeksa:
-- ANALYZE TABLE products, product_category, product_images;
