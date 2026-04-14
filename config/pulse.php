<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | SSO-only mode (ACHPP ID)
    |--------------------------------------------------------------------------
    |
    | When true: password API login is disabled; Fortify registration/password
    | reset features are turned off via config. Exchange flow remains available.
    |
    */
    'sso_only' => (bool) env('PULSE_SSO_ONLY', false),

    /*
    |--------------------------------------------------------------------------
    | ACHPP ID (Identity Provider)
    |--------------------------------------------------------------------------
    */
    'id' => [
        /** Public IdP URL for browser redirects/authorize links. */
        'id_url_public' => rtrim((string) env('ACHPP_ID_BASE_URL', ''), '/'),
        /**
         * Internal IdP URL for server-to-server calls (token/profile).
         * Prefer this for local Laragon, e.g. http://id.test
         */
        'id_url_internal' => rtrim((string) env('ACHPP_ID_INTERNAL_URL', (string) env('ACHPP_ID_INTERNAL_BASE_URL', (string) env('ACHPP_ID_BASE_URL', ''))), '/'),
        'timeout_seconds' => (int) env('ACHPP_ID_TIMEOUT', 10),
        /** TCP + TLS connect phase (helps fail fast if host is wrong) */
        'connect_timeout_seconds' => (int) env('ACHPP_ID_CONNECT_TIMEOUT', 10),
        'profile_path' => env('ACHPP_ID_PROFILE_PATH', '/api/v1/user'),
        /** Public Passport client id (PKCE); required for server-side code→token exchange */
        'oauth_client_id' => (string) env('ACHPP_ID_CLIENT_ID', ''),
        /** cURL TLS verify; set false only for local self-signed certs (e.g. Laragon *.test) */
        'verify_ssl' => (bool) filter_var(env('ACHPP_ID_VERIFY_SSL', 'true'), FILTER_VALIDATE_BOOLEAN),
        /**
         * Prefer IPv4 for outbound cURL (Windows/Laragon: avoids IPv6 blackhole → timeout with 0 bytes).
         */
        'force_ipv4' => (bool) filter_var(env('ACHPP_ID_FORCE_IPV4', 'false'), FILTER_VALIDATE_BOOLEAN),
        /**
         * Force host→IP for cURL (comma-separated), e.g. id.test:443:127.0.0.1 — fixes local 0-byte timeouts when DNS/IPv6 misbehaves.
         *
         * @var list<string>
         */
        'curl_resolve' => array_values(array_filter(array_map('trim', explode(',', (string) env('ACHPP_ID_CURL_RESOLVE', ''))))),
    ],

    /*
    |--------------------------------------------------------------------------
    | Inbound webhooks from ACHPP ID (HMAC-signed)
    |--------------------------------------------------------------------------
    */
    'id_webhooks' => [
        'enabled' => (bool) env('PULSE_ID_WEBHOOKS_ENABLED', false),
        'secret' => (string) env('PULSE_ID_WEBHOOK_SECRET', ''),
        'replay_tolerance_seconds' => (int) env('PULSE_ID_WEBHOOK_REPLAY_TOLERANCE', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | VK (community token + Callback API defaults)
    |--------------------------------------------------------------------------
    |
    | Outbound: group access token for messages.send when the source has no
    | settings.access_token. Inbound: optional fallback for webhook secret and
    | confirmation string when the Filament source fields are empty.
    |
    */
    'vk' => [
        'bot_token' => (string) env('VK_BOT_TOKEN', ''),
        'group_id' => (string) env('VK_GROUP_ID', ''),
        'callback_secret' => (string) env('VK_CALLBACK_SECRET', ''),
        'callback_confirmation' => (string) env('VK_CALLBACK_CONFIRMATION', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Telegram inbound media groups (albums)
    |--------------------------------------------------------------------------
    |
    | Multiple webhook updates share media_group_id. We buffer fragments until
    | the buffer is quiet for quiet_ms, then persist one message.
    |
    */
    'telegram_media_group_quiet_ms' => (int) env('PULSE_TELEGRAM_MEDIA_GROUP_QUIET_MS', 450),

    'telegram_media_group_schedule_ms' => (int) env('PULSE_TELEGRAM_MEDIA_GROUP_SCHEDULE_MS', 700),

    /** Max attachments merged from one inbound media_group (Telegram album limit is 10). */
    'telegram_media_group_max_items' => (int) env('PULSE_TELEGRAM_MEDIA_GROUP_MAX_ITEMS', 10),

];
