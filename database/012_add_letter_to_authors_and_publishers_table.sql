ALTER TABLE `authors`
    ADD COLUMN `letter` VARCHAR(2) NOT NULL AFTER `id`;

ALTER TABLE `publishers`
    ADD COLUMN `letter` VARCHAR(2) NOT NULL AFTER `id`;