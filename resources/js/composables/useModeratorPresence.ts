import { onUnmounted, ref, watch, type Ref } from 'vue';
import { api } from '@/lib/api';

export type ModeratorPresenceState = {
    manual_online: boolean;
    last_heartbeat_at: string | null;
    last_activity_at: string | null;
    is_online: boolean;
    is_away: boolean;
};

export function useModeratorPresence(enabled: Ref<boolean>) {
    const presence = ref<ModeratorPresenceState | null>(null);
    const presenceLoading = ref(false);
    const presenceError = ref<string | null>(null);
    let heartbeatTimer: ReturnType<typeof setInterval> | null = null;

    async function fetchMe(): Promise<void> {
        if (!enabled.value) return;
        presenceLoading.value = true;
        presenceError.value = null;
        try {
            const res = await api.get<{ data: ModeratorPresenceState }>('/moderator/presence/me');
            presence.value = res.data;
        } catch (e) {
            presenceError.value = e instanceof Error ? e.message : 'Presence failed';
        } finally {
            presenceLoading.value = false;
        }
    }

    async function setManualOnline(online: boolean): Promise<void> {
        if (!enabled.value) return;
        try {
            const res = await api.post<{ data: ModeratorPresenceState }>('/moderator/presence/toggle', { online });
            presence.value = res.data;
        } catch (e) {
            presenceError.value = e instanceof Error ? e.message : 'Toggle failed';
        }
    }

    async function sendHeartbeat(): Promise<void> {
        if (!enabled.value) return;
        try {
            const res = await api.post<{ data: ModeratorPresenceState }>('/moderator/presence/heartbeat');
            presence.value = res.data;
        } catch {
            /* best-effort */
        }
    }

    async function sendActivity(): Promise<void> {
        if (!enabled.value) return;
        try {
            const res = await api.post<{ data: ModeratorPresenceState }>('/moderator/presence/activity');
            presence.value = res.data;
        } catch {
            /* best-effort */
        }
    }

    function startHeartbeat(): void {
        if (heartbeatTimer) return;
        void sendHeartbeat();
        heartbeatTimer = setInterval(() => {
            void sendHeartbeat();
        }, 30_000);
    }

    function stopHeartbeat(): void {
        if (heartbeatTimer) {
            clearInterval(heartbeatTimer);
            heartbeatTimer = null;
        }
    }

    watch(
        enabled,
        (on) => {
            if (on) {
                void fetchMe().then(() => startHeartbeat());
            } else {
                stopHeartbeat();
            }
        },
        { immediate: true },
    );

    onUnmounted(() => {
        stopHeartbeat();
    });

    return {
        presence,
        presenceLoading,
        presenceError,
        fetchMe,
        setManualOnline,
        sendHeartbeat,
        sendActivity,
        startHeartbeat,
        stopHeartbeat,
    };
}
