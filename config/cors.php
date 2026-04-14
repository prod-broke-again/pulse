<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'api/widget/*', 'sanctum/csrf-cookie', 'broadcasting/*'],

    'allowed_methods' => ['*'],

    // Разрешаем только локальные домены и pulse.test
    'allowed_origins' => [
        'http://localhost',
        'http://localhost:3000',
        'http://localhost:5173',
        'http://localhost:5174',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:5173',
        'http://127.0.0.1:5174',
        'http://127.0.0.1',
        'https://localhost',
        'https://localhost:3000',
        'https://localhost:5173',
        'https://localhost:5174',
        'https://127.0.0.1:3000',
        'https://127.0.0.1:5173',
        'https://127.0.0.1:5174',
        'https://127.0.0.1',
        'http://pulse.test',
        'https://pulse.test',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Если нужно поддерживать cookie/авторизацию - поставить true
    'supports_credentials' => false,

];
