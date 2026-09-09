<?php

return [
    'driver' => env('SESSION_DRIVER', 'file'),
    'lifetime' => 120,
    'encrypt' => false,
    'files' => storage_path('framework/sessions'),
    'cookie' => 'gi_external_session',
    'path' => '/',
    'domain' => null,
    'secure' => env('SESSION_SECURE_COOKIE', str_starts_with((string) env('APP_URL', ''), 'https://')),
    'http_only' => true,
    // Iframes de outros sites precisam de SameSite=None e cookies seguros em HTTPS.
    'same_site' => env('SESSION_SAME_SITE', str_starts_with((string) env('APP_URL', ''), 'https://') ? 'none' : 'lax'),
];