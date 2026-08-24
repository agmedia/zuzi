<?php

return [
    'feed_url' => env('DELFI_FEED_URL', 'https://delfi.rs/google-feed.xml'),
    'detail_api_url' => env(
        'DELFI_DETAIL_API_URL',
        'https://delfi.rs/api/pc-frontend-api/overview/{remote_product_id}'
    ),
    'allowed_source_categories' => [
        'Knjiga',
        'Strana knjiga',
    ],
    'allowed_product_hosts' => [
        'delfi.rs',
        'www.delfi.rs',
    ],
    'allowed_image_hosts' => [
        'delfi.rs',
        'www.delfi.rs',
    ],
    'cache_path' => storage_path('app/delfi-import/google-feed.xml'),
    'metadata_path' => storage_path('app/delfi-import/google-feed.meta.json'),
    'min_feed_bytes' => 1024 * 1024,
    'max_feed_bytes' => 512 * 1024 * 1024,
    'download_timeout' => (int) env('DELFI_DOWNLOAD_TIMEOUT', 1200),
    'connect_timeout' => (int) env('DELFI_CONNECT_TIMEOUT', 20),
    'sync_batch_size' => (int) env('DELFI_SYNC_BATCH_SIZE', 100),
    'minimum_expected_books' => (int) env('DELFI_MINIMUM_EXPECTED_BOOKS', 100000),
    'minimum_current_ratio' => (float) env('DELFI_MINIMUM_CURRENT_RATIO', 0.80),
    'max_image_bytes' => 15 * 1024 * 1024,
    'max_image_pixels' => 40 * 1000 * 1000,
    'exchange_rate' => (float) env('DELFI_RSD_PER_EUR', 117.2),
    'markup_percent' => (float) env('DELFI_MARKUP_PERCENT', 0),
    'default_quantity' => (int) env('DELFI_DEFAULT_QUANTITY', 1),
    'translate_descriptions' => (bool) env('DELFI_TRANSLATE_DESCRIPTIONS', false),
];
