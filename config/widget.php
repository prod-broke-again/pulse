<?php

declare(strict_types=1);

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

];
