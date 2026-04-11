import { computed, ref, watch, type Ref } from 'vue';
import { api, type ApiMessage } from '@/lib/api';
import {
    leaveChat,
    subscribeToChat,
    type NewChatMessagePayload,
    type MessageReadPayload,
    type ChatGuestUpdatedPayload,
    type ChatTopicGeneratedPayload,
} from '@/composables/useEcho';
import type { ReverbConfig } from '@/composables/useEcho';
import { getAudioService } from '@/services/AudioService';

export type UseChatMessagesOptions = {
    chatId: Ref<number | null>;
    currentUserId: Ref<number | null>;
    reverbConfig: Ref<ReverbConfig | null | undefined>;
    getEcho: (config: ReverbConfig | null | undefined, userId: number | null) => unknown;
    t: (key: string, params?: Record<string, string>) => string;
    onAssigned?: () => void;
    onGuestUpdated?: (payload: ChatGuestUpdatedPayload) => void;
    onTopicGenerated?: (payload: ChatTopicGeneratedPayload) => void;
};

export function useChatMessages(options: UseChatMessagesOptions) {
    const {
        chatId,
        currentUserId,
        reverbConfig,
        getEcho,
        t,
        onAssigned,
        onGuestUpdated,
        onTopicGenerated,
    } = options;

    const messages = ref<ApiMessage[]>([]) as Ref<ApiMessage[]>;
    const messagesLoading = ref(false);
    const messagesError = ref<string | null>(null);
    const oldestMessageId = ref<number | null>(null);
    const loadingOlder = ref(false);
    const sendLoading = ref(false);
    const sendError = ref<string | null>(null);
    const typingName = ref<string | null>(null);

    const echo = computed(() =>
        getEcho(reverbConfig.value, currentUserId.value ?? null) as ReturnType<typeof getEcho>,
    );

    function fetchMessages(chatIdParam: number, beforeId: number | null = null) {
        if (!beforeId) messagesLoading.value = true;
        else loadingOlder.value = true;
        messagesError.value = null;
        const params: Record<string, string | number> = { limit: 50 };
        if (beforeId) params.before_id = beforeId;
        api
            .get<{ data: ApiMessage[] }>(`/chats/${chatIdParam}/messages`, params)
            .then((res) => {
                const list = (res as { data: ApiMessage[] }).data ?? [];
                if (!beforeId) {
                    messages.value = list;
                    oldestMessageId.value = list.length ? (list[0]?.id as number) ?? null : null;
                    const numericIds = list
                        .map((m) => Number(m.id))
                        .filter((n) => Number.isFinite(n) && n > 0);
                    const lastId = numericIds.length > 0 ? Math.max(...numericIds) : 0;
                    if (lastId > 0 && chatId.value === chatIdParam) {
                        api.post(`/chats/${chatIdParam}/read`, { last_message_id: lastId }).catch(() => {});
                    }
                } else {
                    messages.value = [...list, ...messages.value];
                    oldestMessageId.value = list.length ? (list[0]?.id as number) ?? null : oldestMessageId.value;
                }
            })
            .catch((e) => {
                messagesError.value = e instanceof Error ? e.message : t('errors.loadMessages');
            })
            .finally(() => {
                messagesLoading.value = false;
                loadingOlder.value = false;
            });
    }

    function markClientMessagesAsRead() {
        const numericIds = messages.value
            .map((m) => Number(m.id))
            .filter((n) => Number.isFinite(n) && n > 0);
        const lastId = numericIds.length > 0 ? Math.max(...numericIds) : 0;
        if (lastId > 0) markAsRead(lastId);
    }

    function loadOlderMessages() {
        const cid = chatId.value;
        if (!cid || loadingOlder.value || !oldestMessageId.value) return;
        fetchMessages(cid, oldestMessageId.value);
    }

    function sendMessage(text: string, attachments: unknown[] = []): Promise<void> {
        const cid = chatId.value;
        const uid = currentUserId.value;
        if (!cid || !text.trim()) return Promise.resolve();

        const clientMessageId = crypto.randomUUID?.() ?? `temp-${Date.now()}-${Math.random().toString(36).slice(2)}`;
        const tempId = `temp-${clientMessageId}`;
        const optimistic: ApiMessage = {
            id: tempId,
            chat_id: cid,
            sender_id: uid ?? null,
            sender_type: 'moderator',
            text: text.trim(),
            payload: {},
            attachments: [],
            is_read: false,
            created_at: new Date().toISOString(),
            updated_at: new Date().toISOString(),
            client_message_id: clientMessageId,
        };

        sendLoading.value = true;
        sendError.value = null;
        messages.value = [...messages.value, optimistic];

        return api
            .post<{ data: ApiMessage & { client_message_id?: string } }>(
                `/chats/${cid}/send`,
                { text: text.trim(), attachments, client_message_id: clientMessageId },
            )
            .then((res) => {
                const data = res.data;
                if (data) {
                    const withClientId = { ...data, client_message_id: clientMessageId };
                    messages.value = messages.value.map((m) =>
                        (m.client_message_id === clientMessageId ? withClientId : m) as ApiMessage,
                    );
                }
            })
            .catch((e) => {
                sendError.value = e instanceof Error ? e.message : t('errors.sendMessage');
                messages.value = messages.value.filter((m) => m.client_message_id !== clientMessageId);
            })
            .finally(() => {
                sendLoading.value = false;
            });
    }

    function markAsRead(lastMessageId: number) {
        const cid = chatId.value;
        if (!cid || !Number.isFinite(lastMessageId) || lastMessageId <= 0) return;
        api.post(`/chats/${cid}/read`, { last_message_id: lastMessageId }).catch(() => {});
    }

    function applyNewMessage(payload: NewChatMessagePayload) {
        if (payload.chatId !== chatId.value) return;
        if (payload.sender_type === 'moderator' && payload.sender_id === currentUserId.value) return;
        const existingId = payload.messageId;
        const alreadyExists = messages.value.some(
            (m) => Number(m.id) === Number(existingId) || String(m.id) === String(existingId),
        );
        if (alreadyExists) return;
        messages.value = [
            ...messages.value,
            {
                id: payload.messageId,
                chat_id: payload.chatId,
                sender_id: payload.sender_id ?? null,
                sender_type: payload.sender_type || 'client',
                text: payload.text,
                payload: {},
                attachments: [],
                is_read: false,
                created_at: new Date().toISOString(),
                updated_at: new Date().toISOString(),
            } as ApiMessage,
        ];
        if (payload.sender_type !== 'moderator' && !document.hasFocus()) {
            getAudioService().playNewMessageSound();
        }
    }

    function applyMessageRead(payload: MessageReadPayload) {
        if (payload.chatId !== chatId.value) return;
        const ids = new Set(payload.messageIds);
        messages.value = messages.value.map((m) =>
            ids.has(Number(m.id)) ? { ...m, is_read: true } : m,
        );
    }

    watch(
        [echo, chatId],
        () => {
            const e = echo.value;
            const cid = chatId.value;
            if (!e) return;
            if (cid) {
                subscribeToChat(e, cid, {
                    onNewMessage: applyNewMessage,
                    onMessageRead: applyMessageRead,
                    onGuestUpdated,
                    onTopicGenerated,
                    onTyping: (payload) => {
                        if (payload.sender_type === 'moderator') {
                            typingName.value = payload.sender_name ?? null;
                        } else if (payload.sender_type === 'client') {
                            typingName.value = payload.sender_name ? payload.sender_name : t('chat.clientTyping');
                        } else {
                            typingName.value = null;
                        }
                    },
                    onAssigned,
                });
            } else {
                leaveChat(e);
                typingName.value = null;
            }
        },
        { immediate: true },
    );

    return {
        messages,
        messagesLoading,
        messagesError,
        oldestMessageId,
        loadingOlder,
        sendLoading,
        sendError,
        typingName,
        fetchMessages,
        loadOlderMessages,
        sendMessage,
        markAsRead,
        markClientMessagesAsRead,
    };
}
