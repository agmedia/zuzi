-- Jednostrani raskid ugovora
-- MySQL 8 ekvivalent migracije:
-- database/migrations/2026_07_29_120000_create_contract_withdrawals_table.php
--
-- Skripta je idempotentna: sigurno ju je pokrenuti više puta.
-- Ne briše postojeće zahtjeve niti prepisuje postavke koje su već spremljene u adminu.

CREATE TABLE IF NOT EXISTS `contract_withdrawals` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `reference` VARCHAR(32) NOT NULL,
    `submission_key` VARCHAR(64) NOT NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `order_id` BIGINT UNSIGNED NULL,
    `order_number` VARCHAR(80) NOT NULL,
    `full_name` VARCHAR(191) NOT NULL,
    `email` VARCHAR(191) NOT NULL,
    `phone` VARCHAR(80) NULL,
    `address_line` VARCHAR(255) NOT NULL,
    `postal_code` VARCHAR(32) NOT NULL,
    `city` VARCHAR(120) NOT NULL,
    `country_code` CHAR(2) NOT NULL DEFAULT 'HR',
    `contract_date` DATE NULL,
    `received_date` DATE NULL,
    `items` TEXT NOT NULL,
    `note` TEXT NULL,
    `declaration` TEXT NOT NULL,
    `request_snapshot` JSON NOT NULL,
    `snapshot_hash` CHAR(64) NOT NULL,
    `status` VARCHAR(32) NOT NULL DEFAULT 'received',
    `internal_note` TEXT NULL,
    `locale` VARCHAR(12) NOT NULL DEFAULT 'hr',
    `submitted_at` TIMESTAMP NOT NULL,
    `consumer_notified_at` TIMESTAMP NULL DEFAULT NULL,
    `admin_notified_at` TIMESTAMP NULL DEFAULT NULL,
    `notification_error` TEXT NULL,
    `handled_by` BIGINT UNSIGNED NULL,
    `handled_at` TIMESTAMP NULL DEFAULT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `ip_address` VARCHAR(64) NULL,
    `user_agent` VARCHAR(512) NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `contract_withdrawals_reference_unique` (`reference`),
    UNIQUE KEY `contract_withdrawals_submission_key_unique` (`submission_key`),
    KEY `contract_withdrawals_user_id_index` (`user_id`),
    KEY `contract_withdrawals_order_id_index` (`order_id`),
    KEY `contract_withdrawals_order_number_index` (`order_number`),
    KEY `contract_withdrawals_email_index` (`email`),
    KEY `contract_withdrawals_status_index` (`status`),
    KEY `contract_withdrawals_submitted_at_index` (`submitted_at`),
    KEY `contract_withdrawals_handled_by_index` (`handled_by`),
    KEY `contract_withdrawals_status_submitted_at_index` (`status`, `submitted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO `settings`
    (`user_id`, `code`, `key`, `value`, `json`, `created_at`, `updated_at`)
SELECT
    NULL,
    'store',
    'contract_withdrawal',
    JSON_OBJECT(
        'admin_email', 'info@zuzi.hr',
        'return_address', 'Antuna Šoljana 33, 10000 Zagreb',
        'return_cost_policy', 'consumer',
        'instructions', 'Robu sigurno zapakirajte i pošaljite bez nepotrebnog odgađanja, a najkasnije u roku od 14 dana od slanja izjave o raskidu. U paket priložite broj narudžbe ili referencu zahtjeva.'
    ),
    1,
    NOW(),
    NOW()
WHERE NOT EXISTS (
    SELECT 1
    FROM `settings`
    WHERE `code` = 'store'
      AND `key` = 'contract_withdrawal'
);
