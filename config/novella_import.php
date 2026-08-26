<?php

return [
    'products_api_url' => env(
        'NOVELLA_PRODUCTS_API_URL',
        'https://novella.hr/wp-json/wc/store/v1/products'
    ),
    'categories_api_url' => env(
        'NOVELLA_CATEGORIES_API_URL',
        'https://novella.hr/wp-json/wc/store/v1/products/categories'
    ),
    'book_category_id' => (int) env('NOVELLA_BOOK_CATEGORY_ID', 63),
    'product_type' => env('NOVELLA_PRODUCT_TYPE', 'simple'),
    'per_page' => (int) env('NOVELLA_PER_PAGE', 100),
    'allowed_product_hosts' => [
        'novella.hr',
        'www.novella.hr',
    ],
    'allowed_image_hosts' => [
        'novella.hr',
        'www.novella.hr',
    ],
    'snapshot_path' => storage_path('app/novella-import/products.json'),
    'metadata_path' => storage_path('app/novella-import/products.meta.json'),
    'sync_batch_size' => (int) env('NOVELLA_SYNC_BATCH_SIZE', 100),
    'minimum_expected_books' => (int) env('NOVELLA_MINIMUM_EXPECTED_BOOKS', 250),
    'minimum_current_ratio' => (float) env('NOVELLA_MINIMUM_CURRENT_RATIO', 0.70),
    'max_pages' => (int) env('NOVELLA_MAX_PAGES', 100),
    'max_image_bytes' => 15 * 1024 * 1024,
    'max_image_pixels' => 40 * 1000 * 1000,
    'markup_percent' => (float) env('NOVELLA_MARKUP_PERCENT', 0),
    'default_quantity' => (int) env('NOVELLA_DEFAULT_QUANTITY', 1),
];
