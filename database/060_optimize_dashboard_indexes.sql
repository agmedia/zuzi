-- Dashboard index optimizations for orders and order_products.
-- Safe to run more than once on MySQL/MariaDB.

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

-- Dashboard KPI/date range queries and yearly/monthly charts.
CALL add_index_if_missing(
    'orders',
    'idx_orders_created_status_total',
    'ALTER TABLE `orders` ADD INDEX `idx_orders_created_status_total` (`created_at`, `order_status_id`, `total`)'
);

-- Processing/latest order widgets filter by status and sort by created_at.
CALL add_index_if_missing(
    'orders',
    'idx_orders_status_created_total',
    'ALTER TABLE `orders` ADD INDEX `idx_orders_status_created_total` (`order_status_id`, `created_at`, `total`)'
);

-- Dashboard item averages and gift-wrap stats join filtered orders to items.
CALL add_index_if_missing(
    'order_products',
    'idx_order_products_order_product',
    'ALTER TABLE `order_products` ADD INDEX `idx_order_products_order_product` (`order_id`, `product_id`)'
);

-- Latest sold products widget reads the newest real product lines.
CALL add_index_if_missing(
    'order_products',
    'idx_order_products_created_product',
    'ALTER TABLE `order_products` ADD INDEX `idx_order_products_created_product` (`created_at`, `product_id`)'
);

DROP PROCEDURE IF EXISTS add_index_if_missing;

ANALYZE TABLE `orders`, `order_products`;
