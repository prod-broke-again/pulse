import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { usePage } from '@inertiajs/vue3';
import type { Ref } from 'vue';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo?: InstanceType<typeof Echo>;
    }
}

if (typeof window !== 'undefined') {
    window.Pusher = Pusher;
}

export type ReverbConfig = {
    key: string;
    wsHost: string;
    wsPort: number;
    wssPort: number;
    forceTLS: boolean;
    authEndpoint: string;
};

function getCsrfToken(): string {
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    if (match) return decodeURIComponent(match[1]);
    return '';
}

export function getEcho(config: ReverbConfig | null | undefined, userId: number | null): InstanceType<typeof Echo> | null {
    if (!config || !userId) return null;
    if (window.Echo) return window.Echo;

    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: config.key,
        wsHost: config.wsHost,
        wsPort: config.wsPort,
        wssPort: config.wssPort,
        forceTLS: config.forceTLS,
        cluster: 'reverb',
        authorizer: (channel: { name: string }) => ({
            authorize: (socketId: string, callback: (err: Error | null, data?: unknown) => void) => {
                fetch(config.authEndpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': getCsrfToken(),
                    },
                    body: JSON.stringify({ socket_id: socketId, channel_name: channel.name }),
                    credentials: 'include',
                })
                    .then((r) => r.json())
                    .then((data) => callback(null, data))
                    .catch((err) => callback(err));
            },
        }),
    });

    return window.Echo;
}

export type NewChatMessagePayload = {
    chatId: number;
    messageId: number;
    text: string;
    sender_type: string;
    sender_id: number | null;
};

export type MessageReadPayload = {
    chatId: number;
    messageIds: number[];
};

export type ChatGuestUpdatedPayload = {
    chatId: number;
    user_metadata: { name?: string | null; email?: string | null };
};

export type ChatTopicGeneratedPayload = {
    chatId: number;
    topic: string;
};

export type ChatChannelCallbacks = {
    onNewMessage?: (payload: NewChatMessagePayload) => void;
    onMessageRead?: (payload: MessageReadPayload) => void;
    onGuestUpdated?: (payload: ChatGuestUpdatedPayload) => void;
    onTyping?: (payload: { sender_name?: string; sender_type?: string }) => void;
    onAssigned?: () => void;
    onTopicGenerated?: (payload: ChatTopicGeneratedPayload) => void;
};

export type ModeratorChannelCallbacks = {
    onAssigned?: () => void;
};

let currentChatChannelRef: unknown = null;
let currentModeratorUserId: number | null = null;
let currentChatId: number | null = null;

export function subscribeToChat(
    echo: InstanceType<typeof Echo> | null,
    chatId: number,
    callbacks: ChatChannelCallbacks,
): void {
    if (!echo) return;
    if (currentChatChannelRef && currentChatId === chatId) return;
    if (currentChatChannelRef && currentChatId !== null) {
        echo.leave(`chat.${currentChatId}`);
        currentChatChannelRef = null;
    }
    currentChatId = chatId;
    const ch = echo.private(`chat.${chatId}`);
    currentChatChannelRef = ch;
    ch.listen('.App\\Events\\NewChatMessage', (e: NewChatMessagePayload) => {
        callbacks.onNewMessage?.(e);
    });
    ch.listen('.App\\Events\\MessageRead', (e: MessageReadPayload) => {
        callbacks.onMessageRead?.(e);
    });
    ch.listen('.App\\Events\\ChatGuestUpdated', (e: ChatGuestUpdatedPayload) => {
        callbacks.onGuestUpdated?.(e);
    });
    ch.listen('typing', (e: { sender_name?: string; sender_type?: string }) => {
        callbacks.onTyping?.(e);
    });
    ch.listen('.App\\Events\\ChatAssigned', () => {
        callbacks.onAssigned?.();
    });
    ch.listen('ChatAssigned', () => {
        callbacks.onAssigned?.();
    });
    ch.listen('.App\\Events\\ChatTopicGenerated', (e: ChatTopicGeneratedPayload) => {
        callbacks.onTopicGenerated?.(e);
    });
}

export function leaveChat(echo: InstanceType<typeof Echo> | null): void {
    if (currentChatChannelRef && currentChatId !== null && echo) {
        echo.leave(`chat.${currentChatId}`);
        currentChatChannelRef = null;
        currentChatId = null;
    }
}

export function subscribeToModerator(
    echo: InstanceType<typeof Echo> | null,
    userId: number,
    callbacks: ModeratorChannelCallbacks,
): void {
    if (!echo) return;
    if (currentModeratorUserId !== null) return;
    currentModeratorUserId = userId;
    const ch = echo.private(`moderator.${userId}`);
    ch.listen('.App\\Events\\ChatAssigned', () => {
        callbacks.onAssigned?.();
    });
    ch.listen('ChatAssigned', () => {
        callbacks.onAssigned?.();
    });
}

export function leaveModerator(echo: InstanceType<typeof Echo> | null): void {
    if (currentModeratorUserId !== null && echo) {
        echo.leave(`moderator.${currentModeratorUserId}`);
        currentModeratorUserId = null;
    }
}

export function useEcho(
    userId: Ref<number | null> | number | null,
    reverbConfig: Ref<ReverbConfig | null | undefined> | ReverbConfig | null | undefined,
) {
    const page = usePage();
    const config = typeof reverbConfig === 'object' && reverbConfig && 'value' in reverbConfig
        ? reverbConfig.value
        : reverbConfig;
    const uid = typeof userId === 'object' && userId && 'value' in userId ? userId.value : userId;
    return getEcho(config ?? (page.props.reverb as ReverbConfig | undefined) ?? null, uid);
}
