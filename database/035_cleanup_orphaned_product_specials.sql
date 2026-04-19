-- Cleanup zaostale akcijske cijene na proizvodima.
-- Pravila:
-- 1. Ako više nema nijedne akcije, brišu se svi nelockani popusti.
-- 2. Ako akcije postoje, brišu se samo proizvodi koji referenciraju nepostojeću akciju.
-- Ne dira ručno zaključane (`special_lock = 1`) proizvode.

SET @action_count := (SELECT COUNT(*) FROM `product_actions`);

UPDATE `products`
SET
    `action_id` = 0,
    `special` = NULL,
    `special_from` = NULL,
    `special_to` = NULL,
    `special_lock` = 0
WHERE
    `special_lock` = 0
    AND @action_count = 0
    AND (`special` IS NOT NULL OR `action_id` > 0);

UPDATE `products` p
LEFT JOIN `product_actions` a ON a.`id` = p.`action_id`
SET
    p.`action_id` = 0,
    p.`special` = NULL,
    p.`special_from` = NULL,
    p.`special_to` = NULL,
    p.`special_lock` = 0
WHERE
    p.`special_lock` = 0
    AND @action_count > 0
    AND p.`action_id` > 0
    AND a.`id` IS NULL;
