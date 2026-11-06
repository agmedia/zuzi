<?php

return [
    'enabled' => env('ABANDONED_CART_EMAILS_ENABLED', true),

    // The migration records the activation time; old orders are never backfilled.
    'starts_at' => env('ABANDONED_CART_EMAILS_STARTS_AT'),

    'delays_minutes' => [
        1 => 60,
        2 => 24 * 60,
    ],

    'max_reminders' => 2,
    'min_gap_minutes' => 23 * 60,
    'recipient_cooldown_hours' => 72,
    'max_age_days' => 3,
    'batch_size' => 25,
    'recovery_link_days' => 7,
];
