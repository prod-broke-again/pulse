<?php

declare(strict_types=1);

$widgetEnabledFromEnv = env('WIDGET_ENABLED');
$forceWidgetEnabled = $widgetEnabledFromEnv === null || $widgetEnabledFromEnv === ''
    ? null
    : filter_var($widgetEnabledFromEnv, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

return [

    /*
    |--------------------------------------------------------------------------
    | Widget allowed origins (postMessage)
    |--------------------------------------------------------------------------
    | Origins allowed to send guest data via postMessage. Defaults to APP_URL
    | for same-origin testing. For production add Aggregator and ACHPP domains.
    |
    */
    'allowed_origins' => array_filter(array_map('trim', explode(',', env('WIDGET_ALLOWED_ORIGINS', env('APP_URL', ''))))),

    /*
    |--------------------------------------------------------------------------
    | Global widget kill-switch (overrides admin / database)
    |--------------------------------------------------------------------------
    | When set in .env, forces the widget on or off. When unset, use Filament/DB.
    | Optional copy and contact URLs override the same fields from admin when non-empty.
    |
    */
    'force_enabled' => $forceWidgetEnabled,

    'force_disabled_title' => env('WIDGET_DISABLED_TITLE'),

    'force_disabled_text' => env('WIDGET_DISABLED_TEXT'),

    'force_telegram_url' => env('WIDGET_TELEGRAM_URL'),

    'force_vk_url' => env('WIDGET_VK_URL'),

    'force_max_url' => env('WIDGET_MAX_URL'),

];
