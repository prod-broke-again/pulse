/** Aligned with config/notification_sounds.php */
export type NotificationPresetId =
    | 'none'
    | 'notification_simple_01'
    | 'notification_simple_02'
    | 'notification_high_intensity'
    | 'notification_decorative_01'
    | 'alert_simple';

export type NotificationSoundPrefs = {
    mute: boolean;
    volume: number;
    presets: {
        in_chat: NotificationPresetId;
        in_app: NotificationPresetId;
        background: NotificationPresetId;
        important: NotificationPresetId;
    };
};

export const PRESET_PUBLIC_PATH: Record<Exclude<NotificationPresetId, 'none'>, string> = {
    notification_simple_01: '/sounds/notifications/notification_simple-01.wav',
    notification_simple_02: '/sounds/notifications/notification_simple-02.wav',
    notification_high_intensity: '/sounds/notifications/notification-high-intensity.wav',
    notification_decorative_01: '/sounds/notifications/notification_decorative-01.wav',
    alert_simple: '/sounds/notifications/alert_simple.wav',
};
