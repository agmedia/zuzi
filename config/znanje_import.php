<?php

return [
    'catalog_url' => env(
        'ZNANJE_CATALOG_URL',
        'https://znanje.hr/kategorija-proizvoda/{slug}/{id}'
    ),
    'sitemap_url' => env('ZNANJE_SITEMAP_URL', 'https://znanje.hr/sitemap-products.xml'),
    'root_categories' => [
        500 => ['name' => 'Knjige', 'slug' => 'knjige'],
        505 => ['name' => 'Strane knjige', 'slug' => 'strane-knjige'],
    ],
    'per_page' => 84,
    'sort' => 'date',
    'order' => 'desc',
    'available_only' => true,
    'allowed_product_hosts' => [
        'znanje.hr',
        'www.znanje.hr',
    ],
    'allowed_image_hosts' => [
        'znanje.hr',
        'www.znanje.hr',
    ],
    'snapshot_path' => storage_path('app/znanje-import/products.json'),
    'metadata_path' => storage_path('app/znanje-import/products.meta.json'),
    'sync_batch_size' => (int) env('ZNANJE_SYNC_BATCH_SIZE', 500),
    // Admin refresh deliberately fetches one listing page per HTTP request.
    // The controller additionally applies a hard cap so a stale production
    // env override cannot bring gateway timeouts back.
    'refresh_pages_per_request' => (int) env('ZNANJE_REFRESH_PAGES_PER_REQUEST', 1),
    'sync_session_seconds' => (int) env('ZNANJE_SYNC_SESSION_SECONDS', 14400),
    'minimum_expected_books' => (int) env('ZNANJE_MINIMUM_EXPECTED_BOOKS', 20000),
    'minimum_current_ratio' => (float) env('ZNANJE_MINIMUM_CURRENT_RATIO', 0.75),
    'maximum_catalog_drift_items' => (int) env('ZNANJE_MAXIMUM_CATALOG_DRIFT_ITEMS', 20),
    'maximum_catalog_drift_ratio' => (float) env('ZNANJE_MAXIMUM_CATALOG_DRIFT_RATIO', 0.005),
    'max_pages_per_category' => (int) env('ZNANJE_MAX_PAGES_PER_CATEGORY', 500),
    'request_delay_ms' => (int) env('ZNANJE_REQUEST_DELAY_MS', 350),
    'feed_request_timeout' => (int) env('ZNANJE_FEED_REQUEST_TIMEOUT', 15),
    'feed_connect_timeout' => (int) env('ZNANJE_FEED_CONNECT_TIMEOUT', 5),
    'feed_request_attempts' => (int) env('ZNANJE_FEED_REQUEST_ATTEMPTS', 3),
    'feed_retry_delay_ms' => (int) env('ZNANJE_FEED_RETRY_DELAY_MS', 600),
    'feed_step_lock_seconds' => (int) env('ZNANJE_FEED_STEP_LOCK_SECONDS', 120),
    'request_timeout' => (int) env('ZNANJE_REQUEST_TIMEOUT', 90),
    'connect_timeout' => (int) env('ZNANJE_CONNECT_TIMEOUT', 10),
    'max_html_bytes' => 4 * 1024 * 1024,
    'max_image_bytes' => 15 * 1024 * 1024,
    'max_image_pixels' => 40 * 1000 * 1000,
    'markup_percent' => (float) env('ZNANJE_MARKUP_PERCENT', 0),
    'default_quantity' => (int) env('ZNANJE_DEFAULT_QUANTITY', 1),
];
