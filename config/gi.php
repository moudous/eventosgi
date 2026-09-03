<?php

return [
    'name' => env('GI_NAME', 'Aplicação externa do GI'),
    'gi_url' => env('GI_URL', 'http://localhost:8000'),
    'client_id' => env('GI_CLIENT_ID'),
    'client_secret' => env('GI_CLIENT_SECRET'),
    'frame_ancestors' => env('GI_FRAME_ANCESTORS', env('GI_URL', 'http://localhost:8000')),
    'allow_outside_iframe' => env('GI_ALLOW_OUTSIDE_IFRAME', false),
];