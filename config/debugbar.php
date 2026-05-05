<?php

return [
    'enabled' => env(
        'DEBUGBAR_ENABLED',
        env('APP_ENV') === 'local' && env('APP_DEBUG', false)
    ),
];
