<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter([
        env('FRONTEND_URL', 'http://localhost:5173'),
    ])),

    'allowed_origins_patterns' => [
        '#^https?://([a-z0-9-]+\.)?localhost(:\d+)?$#',
        '#^https?://([a-z0-9-]+\.)?127\.0\.0\.1(:\d+)?$#',
        '#^https?://([a-z0-9-]+\.)?restora\.com$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
