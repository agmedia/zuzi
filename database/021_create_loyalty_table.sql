create table if not exists zuzi.loyalty
(
    id           bigint unsigned auto_increment primary key,
    user_id      bigint       not null,
    reference_id bigint       not null,
    reference    varchar(191) not null,
    target       varchar(191) not null,
    earned       bigint       not null,
    spend        bigint       not null,
    comment      text         not null,
    created_at   timestamp    null,
    updated_at   timestamp    null
    )
    engine = InnoDB
    collate = utf8mb4_unicode_ci;