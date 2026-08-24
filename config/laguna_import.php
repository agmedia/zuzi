<?php

return [
    'feed_url' => env('LAGUNA_FEED_URL', 'https://laguna.rs/rss/products/'),
    'allowed_source_categories' => ['Knjige'],
    'allowed_product_host' => 'laguna.rs',
    'allowed_image_hosts' => [
        'laguna.oozmi-cdn.com',
    ],
    'cache_path' => storage_path('app/laguna-import/products.rss'),
    'max_feed_bytes' => 50 * 1024 * 1024,
    'max_image_bytes' => 15 * 1024 * 1024,
    'exchange_rate' => (float) env('LAGUNA_RSD_PER_EUR', 117.2),
    'markup_percent' => (float) env('LAGUNA_MARKUP_PERCENT', 0),
    'default_quantity' => (int) env('LAGUNA_DEFAULT_QUANTITY', 1),
    'translate_descriptions' => (bool) env('LAGUNA_TRANSLATE_DESCRIPTIONS', false),
];
