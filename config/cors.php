<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
    'http://localhost:5173',
    'http://localhost:4173',
    'http://192.168.1.3:5173',
    'http://192.168.1.3:4173',
    'https://bagpipe-stream-ambitious.ngrok-free.dev',
],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];