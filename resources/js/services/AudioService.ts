/**
 * Singleton audio service for notification sounds.
 * Uses a single Audio instance per sound to avoid context leaks and overlapping playback.
 * Fails gracefully when autoplay is blocked (e.g. browser policy).
 */

import {
    type NotificationSoundPrefs,
    type NotificationPresetId,
    PRESET_PUBLIC_PATH,
} from '@/constants/notificationSounds';

const SOUNDS = {
    newMessage: '/sounds/notifications/notification_simple-01.wav',
    systemNotification: '/sounds/system-notification.mp3',
} as const;

let instance: AudioService | null = null;

export class AudioService {
    private newMessageAudio: HTMLAudioElement | null = null;
    private systemNotificationAudio: HTMLAudioElement | null = null;
    private readonly presetAudio = new Map<string, HTMLAudioElement>();

    private getOrCreateAudio(src: string): HTMLAudioElement {
        const key = src === SOUNDS.newMessage ? 'newMessageAudio' : 'systemNotificationAudio';
        let audio = key === 'newMessageAudio' ? this.newMessageAudio : this.systemNotificationAudio;
        if (!audio) {
            audio = new Audio(src);
            if (key === 'newMessageAudio') this.newMessageAudio = audio;
            else this.systemNotificationAudio = audio;
        }
        return audio;
    }

    private play(src: string, volume = 1): void {
        try {
            const audio = this.getOrCreateAudio(src);
            audio.volume = volume;
            audio.currentTime = 0;
            audio.play().catch(() => {
                // Autoplay blocked or other error - ignore (graceful degradation)
            });
        } catch {
            // Ignore
        }
    }

    playNewMessageSound(): void {
        this.play(SOUNDS.newMessage);
    }

    playSystemNotificationSound(): void {
        this.play(SOUNDS.systemNotification);
    }

    /**
     * Server-synced moderator presets (volume, mute, per-scenario preset ids).
     */
    playFromNotificationPrefs(
        prefs: NotificationSoundPrefs | null | undefined,
        scenario: 'in_app' | 'background' | 'important',
    ): void {
        if (!prefs || prefs.mute) {
            return;
        }
        const key =
            scenario === 'important'
                ? prefs.presets.important
                : scenario === 'background'
                  ? prefs.presets.background
                  : prefs.presets.in_app;
        if (key === 'none') {
            return;
        }
        const path = PRESET_PUBLIC_PATH[key as Exclude<NotificationPresetId, 'none'>];
        if (!path) {
            return;
        }
        try {
            let audio = this.presetAudio.get(path);
            if (!audio) {
                audio = new Audio(path);
                this.presetAudio.set(path, audio);
            }
            audio.volume = prefs.volume;
            audio.currentTime = 0;
            audio.play().catch(() => {});
        } catch {
            /* ignore */
        }
    }
}

export function getAudioService(): AudioService {
    if (instance === null) {
        instance = new AudioService();
    }
    return instance;
}
