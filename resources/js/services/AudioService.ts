/**
 * Singleton audio service for notification sounds.
 * Uses a single Audio instance per sound to avoid context leaks and overlapping playback.
 * Fails gracefully when autoplay is blocked (e.g. browser policy).
 */

const SOUNDS = {
    newMessage: '/sounds/new-message.mp3',
    systemNotification: '/sounds/system-notification.mp3',
} as const;

let instance: AudioService | null = null;

export class AudioService {
    private newMessageAudio: HTMLAudioElement | null = null;
    private systemNotificationAudio: HTMLAudioElement | null = null;

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

    private play(src: string): void {
        try {
            const audio = this.getOrCreateAudio(src);
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
}

export function getAudioService(): AudioService {
    if (instance === null) {
        instance = new AudioService();
    }
    return instance;
}
