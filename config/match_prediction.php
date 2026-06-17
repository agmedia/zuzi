<?php

return [
    'enabled' => env('MATCH_PREDICTION_ENABLED', true),
    'match_name' => env('MATCH_PREDICTION_MATCH_NAME', 'Hrvatska – Engleska'),
    'deadline' => env('MATCH_PREDICTION_DEADLINE', '2026-06-18 22:00:00'),
    'timezone' => env('MATCH_PREDICTION_TIMEZONE', 'Europe/Zagreb'),
    'prize_name' => env('MATCH_PREDICTION_PRIZE_NAME', '555 najvećih nogometnih utakmica 21. stoljeća'),
    'prize_url' => env('MATCH_PREDICTION_PRIZE_URL', 'https://www.zuzi.hr/kategorija-proizvoda/nakladnici/begen/robert-hrkac-555-najvecih-nogometnih-utakmica-21-stoljeca'),
];
