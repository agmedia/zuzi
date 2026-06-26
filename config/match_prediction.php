<?php

return [
    'enabled' => env('MATCH_PREDICTION_ENABLED', true),
    'match_name' => env('MATCH_PREDICTION_MATCH_NAME', 'Hrvatska – Gana'),
    'deadline' => env('MATCH_PREDICTION_DEADLINE', '2026-06-27 23:00:00'),
    'timezone' => env('MATCH_PREDICTION_TIMEZONE', 'Europe/Zagreb'),
    'prize_name' => env('MATCH_PREDICTION_PRIZE_NAME', '30 EUR poklon bon'),
    'prize_url' => env('MATCH_PREDICTION_PRIZE_URL', '/poklon-bon'),
];
