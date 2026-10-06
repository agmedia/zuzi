-- Uskladi SEO opis naslovnice sa zaokruženim brojem artikala.
-- Upit je idempotentan i mijenja samo zastarjele formulacije na homepage zapisu.

UPDATE `pages`
SET
    `meta_description` = 'Online knjižara i antikvarijat u Hrvatskoj s više od 73.000 naslova. Pronađi romane, dječje knjige, slikovnice i stručnu literaturu online.',
    `updated_at` = NOW()
WHERE `slug` = 'homepage'
  AND (
      `meta_description` LIKE '%84.000%'
      OR `meta_description` LIKE '%73.071%'
  );

UPDATE `widgets` AS `w`
INNER JOIN `widget_groups` AS `wg` ON `wg`.`id` = `w`.`group_id`
SET
    `w`.`subtitle` = REPLACE(
        REPLACE(`w`.`subtitle`, '84.000', '73.000'),
        '73.071',
        '73.000'
    ),
    `w`.`updated_at` = NOW()
WHERE `wg`.`slug` = 'slider-index'
  AND (
      `w`.`subtitle` LIKE '%84.000%'
      OR `w`.`subtitle` LIKE '%73.071%'
  );

SELECT
    `id`,
    `slug`,
    `meta_description`,
    `updated_at`
FROM `pages`
WHERE `slug` = 'homepage';

SELECT
    `w`.`id`,
    `w`.`title`,
    `w`.`subtitle`,
    `w`.`updated_at`
FROM `widgets` AS `w`
INNER JOIN `widget_groups` AS `wg` ON `wg`.`id` = `w`.`group_id`
WHERE `wg`.`slug` = 'slider-index'
  AND `w`.`subtitle` LIKE '%73.000%';
