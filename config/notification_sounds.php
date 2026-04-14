<?php

declare(strict_types=1);

/**
 * Preset IDs for moderator notification sounds (synced across devices).
 * Paths are relative to public/ for web; desktop/mobile resolve via app URL.
 */
return [
    'presets' => [
        'none' => [
            'label' => 'Без звука',
            'public_path' => null,
        ],
        'notification_simple_01' => [
            'label' => 'Simple 01 (в приложении)',
            'public_path' => 'sounds/notifications/notification_simple-01.wav',
        ],
        'notification_simple_02' => [
            'label' => 'Simple 02 (фон)',
            'public_path' => 'sounds/notifications/notification_simple-02.wav',
        ],
        'notification_high_intensity' => [
            'label' => 'High intensity (важное)',
            'public_path' => 'sounds/notifications/notification_high-intensity.wav',
        ],
        'notification_decorative_01' => [
            'label' => 'Decorative 01',
            'public_path' => 'sounds/notifications/notification_decorative-01.wav',
        ],
        'alert_simple' => [
            'label' => 'Alert simple',
            'public_path' => 'sounds/notifications/alert_simple.wav',
        ],
    ],

    'defaults' => [
        'mute' => false,
        'volume' => 1.0,
        'presets' => [
            'in_chat' => 'none',
            'in_app' => 'notification_simple_01',
            'background' => 'notification_simple_02',
            'important' => 'notification_high_intensity',
        ],
    ],
];
