<?php

return [
    'name' => env('APP_NAME', 'GI Starter'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL'),
    'locale' => env('APP_LOCALE', 'pt_BR'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'pt_BR'),
    'timezone' => 'America/Sao_Paulo',
    'key' => env('APP_KEY'),
    'cipher' => 'AES-256-CBC',
];
