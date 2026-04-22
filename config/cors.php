<?php

$baseCorsOrigins = [
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
    // Публичные сайты: виджет (fetch с appp-psy.ru / kukushechka.ru) и веб-клиент Pulse
    'https://pulse.appp-psy.ru',
    'https://appp-psy.ru',
    'https://www.appp-psy.ru',
    'https://kukushechka.ru',
    'https://www.kukushechka.ru',
];

$extraFromEnv = array_filter(
    array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))),
    static fn (string $o): bool => $o !== '',
);

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
    | Доп. origin'ы: CORS_ALLOWED_ORIGINS в .env (через запятую, без пробелов).
    |
    */

    'paths' => ['api/*', 'api/widget/*', 'sanctum/csrf-cookie', 'broadcasting/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_unique([...$baseCorsOrigins, ...$extraFromEnv])),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // Если нужно поддерживать cookie/авторизацию - поставить true
    'supports_credentials' => false,

];
