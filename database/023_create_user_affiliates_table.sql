create table if not exists zuzi.user_affiliates
(
    id                bigint unsigned auto_increment primary key,
    user_id           bigint       not null,
    customer_email    varchar(191) null,
    affiliate_code    varchar(191) not null,
    active            boolean      not null default 1,
    registered_at     timestamp    null,
    first_purchase_at timestamp    null,
    created_at        timestamp    null,
    updated_at        timestamp    null
)
    engine = InnoDB
    collate = utf8mb4_unicode_ci;