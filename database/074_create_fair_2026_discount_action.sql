-- Početna sajamska akcija 23.09.2026. – 28.09.2026.
-- Ne mijenja strukturu baze i sigurna je za ponovno pokretanje.

INSERT INTO `product_actions` (
    `title`,
    `type`,
    `discount`,
    `group`,
    `links`,
    `date_start`,
    `date_end`,
    `data`,
    `coupon`,
    `quantity`,
    `lock`,
    `status`,
    `created_at`,
    `updated_at`
)
SELECT
    'Sajamski popust 2026',
    'P',
    20,
    'fair_discount',
    '["fair_discount"]',
    '2026-09-23 00:00:00',
    '2026-09-28 23:59:59',
    '{"tiers":[{"min_total":0,"max_total":50,"discount":10},{"min_total":50.01,"max_total":99.99,"discount":15},{"min_total":100,"max_total":null,"discount":20}],"free_boxnow":true}',
    NULL,
    0,
    0,
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM `product_actions`
    WHERE `group` = 'fair_discount'
      AND `title` = 'Sajamski popust 2026'
      AND `date_start` = '2026-09-23 00:00:00'
      AND `date_end` = '2026-09-28 23:59:59'
);

SELECT
    `id`,
    `title`,
    `date_start`,
    `date_end`,
    `discount`,
    `data`,
    `status`
FROM `product_actions`
WHERE `group` = 'fair_discount'
ORDER BY `id` DESC;
