<script setup lang="ts">
import { Head, usePage, router } from '@inertiajs/vue3';
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { useDebounceFn } from '@vueuse/core';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { api, type ApiChat, type ApiCannedResponse } from '@/lib/api';
import { getEcho, leaveChat, leaveModerator, subscribeToModerator } from '@/composables/useEcho';
import { useChatMessages } from '@/composables/useChatMessages';
import ChatMessageItem from '@/components/chat/ChatMessageItem.vue';
import Button from '@/components/ui/button/Button.vue';
import { Input } from '@/components/ui/input';
import { Search, UserPlus, XCircle, Send, Loader2, Bell } from 'lucide-vue-next';
import { useWebPush } from '@/composables/useWebPush';

const page = usePage();
const authUser = computed(() => (page.props.auth as { user?: { id: number } })?.user);
const initialChatId = computed(() => (page.props.initialChatId as number | null) ?? null);
const reverbConfig = computed(() => page.props.reverb);
const { supported: pushSupported, loading: pushLoading, error: pushError, subscribe: subscribePush } = useWebPush();
const pushSubscribed = ref(false);

const { t } = useI18n();

const breadcrumbs: BreadcrumbItem[] = [
    { title: t('nav.chat'), href: '/chat' },
];

const activeTab = ref<'my' | 'unassigned' | 'all'>('my');
const statusFilter = ref<'open' | 'closed'>('open');
const searchQuery = ref('');
const chats = ref<ApiChat[]>([]);
const chatsTotal = ref(0);
const chatsLoading = ref(false);
const chatsError = ref<string | null>(null);

const selectedChatId = ref<number | null>(null);
const selectedChat = ref<ApiChat | null>(null);
const newMessageText = ref('');
const cannedResponses = ref<ApiCannedResponse[]>([]);
const assigningInProgress = ref(false);

const {
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
    sendMessage: sendMessageAction,
} = useChatMessages({
    chatId: selectedChatId,
    currentUserId: computed(() => authUser.value?.id ?? null),
    reverbConfig,
    getEcho,
    t,
    onAssigned: () => {
        fetchChats().then(() => {
            if (selectedChat.value) {
                const c = chats.value.find((x) => x.id === selectedChat.value!.id);
                if (c) selectedChat.value = c;
            }
        });
    },
    onGuestUpdated: (payload) => {
        if (selectedChatId.value === payload.chatId && selectedChat.value) {
            selectedChat.value = {
                ...selectedChat.value,
                user_metadata: { ...selectedChat.value.user_metadata, ...payload.user_metadata },
            };
        }
        const idx = chats.value.findIndex((c) => c.id === payload.chatId);
        if (idx !== -1) {
            const next = [...chats.value];
            next[idx] = { ...next[idx], user_metadata: { ...next[idx].user_metadata, ...payload.user_metadata } };
            chats.value = next;
        }
    },
    onTopicGenerated: (payload) => {
        const idx = chats.value.findIndex((c) => c.id === payload.chatId);
        if (idx !== -1) {
            const next = [...chats.value];
            next[idx] = { ...next[idx], topic: payload.topic };
            chats.value = next;
        }
        if (selectedChatId.value === payload.chatId && selectedChat.value) {
            selectedChat.value = { ...selectedChat.value, topic: payload.topic };
        }
    },
});

const debouncedFetchChats = useDebounceFn(() => fetchChats(), 300);
const debouncedSendTyping = useDebounceFn(() => {
    if (!selectedChatId.value) return;
    api.post(`/chats/${selectedChatId.value}/typing`).catch(() => {});
}, 400);

function fetchChats(): Promise<void> {
    chatsLoading.value = true;
    chatsError.value = null;
    return api
        .get<{ data: ApiChat[]; meta?: { total: number } }>('/chats', {
            tab: activeTab.value,
            status: statusFilter.value,
            search: searchQuery.value || undefined,
            per_page: 50,
        })
        .then((res) => {
            chats.value = (res as { data: ApiChat[] }).data ?? [];
            const meta = (res as { meta?: { total: number } }).meta;
            chatsTotal.value = meta?.total ?? chats.value.length;
        })
        .catch((e) => {
            chatsError.value = e instanceof Error ? e.message : t('errors.loadChats');
        })
        .finally(() => {
            chatsLoading.value = false;
        });
}

function fetchCannedResponses(sourceId: number) {
    api
        .get<{ data: ApiCannedResponse[] }>('/canned-responses', { source_id: sourceId })
        .then((res) => {
            cannedResponses.value = (res as { data: ApiCannedResponse[] }).data ?? [];
        })
        .catch(() => {
            cannedResponses.value = [];
        });
}

function selectChat(chat: ApiChat) {
    selectedChatId.value = chat.id;
    selectedChat.value = chat;
    newMessageText.value = '';
    router.get('/chat', { chat: chat.id }, { preserveState: true });
    if (chat.source_id) fetchCannedResponses(chat.source_id);
    fetchMessages(chat.id);
}

function assignToMe() {
    if (!selectedChatId.value) return;
    assigningInProgress.value = true;
    api
        .post<{ data: ApiChat }>(`/chats/${selectedChatId.value}/assign-me`)
        .then((res) => {
            if (res.data) selectedChat.value = res.data;
            fetchChats();
        })
        .catch(() => {})
        .finally(() => {
            assigningInProgress.value = false;
        });
}

function closeChat() {
    if (!selectedChatId.value) return;
    api.post(`/chats/${selectedChatId.value}/close`).then(() => {
        selectedChatId.value = null;
        selectedChat.value = null;
        router.get('/chat', {}, { preserveState: true });
        fetchChats();
    }).catch(() => {});
}

function sendMessage() {
    const text = newMessageText.value.trim();
    if (!selectedChatId.value || !text) return;
    sendMessageAction(text, []).then(() => {
        newMessageText.value = '';
    });
}

function insertCannedResponse(item: ApiCannedResponse) {
    newMessageText.value = item.text;
}

function resolveInterlocutorName(chat: ApiChat): string {
    const meta = chat.user_metadata as { name?: unknown } | undefined;
    const rawName = typeof meta?.name === 'string' ? meta.name.trim() : '';
    const normalized = rawName.toLowerCase();
    if (rawName && !['гость', 'guest', 'клиент', 'client'].includes(normalized)) {
        return rawName;
    }

    return chat.external_user_id ?? '—';
}

const interlocutorDisplay = computed(() => {
    const chat = selectedChat.value;
    if (!chat) return '—';
    return resolveInterlocutorName(chat);
});

const isAssignedToMe = computed(
    () => selectedChat.value?.assigned_to != null && selectedChat.value?.assigned_to === authUser.value?.id,
);

const echo = computed(() => getEcho(reverbConfig.value, authUser.value?.id ?? null));

watch([activeTab, statusFilter], () => fetchChats(), { immediate: false });
watch(searchQuery, () => {
    const q = searchQuery.value;
    if (q.length >= 2 || q === '') debouncedFetchChats();
});

const messagesScrollRef = ref<HTMLElement | null>(null);

function avatarUrlFromChat(chat: ApiChat | null): string | null {
    if (!chat) return null;
    const meta = chat.user_metadata as { avatar_url?: unknown } | undefined;
    const raw = typeof meta?.avatar_url === 'string' ? meta.avatar_url.trim() : '';
    return raw !== '' ? raw : null;
}

watch(messages, () => {
    nextTick(() => {
        const el = messagesScrollRef.value;
        if (el) el.scrollTop = el.scrollHeight;
    });
}, { deep: true });

function handleOpenChatFromPush(chatId: number) {
    activeTab.value = 'all';
    router.get('/chat', { chat: chatId }, { preserveState: true });
    fetchChats().then(() => {
        const chat = chats.value.find((c) => c.id === chatId);
        if (chat) selectChat(chat);
    });
}

onMounted(() => {
    const id = initialChatId.value;
    if (id) {
        activeTab.value = 'all';
    }
    fetchChats().then(() => {
        if (id) {
            const chat = chats.value.find((c) => c.id === id);
            if (chat) selectChat(chat);
        }
    });
    const e = echo.value;
    const uid = authUser.value?.id;
    if (e && uid) {
        subscribeToModerator(e, uid, { onAssigned: fetchChats });
    }
    if (typeof navigator !== 'undefined' && navigator.serviceWorker?.controller) {
        const handler = (event: MessageEvent) => {
            if (event.data?.type === 'OPEN_CHAT' && event.data.chatId != null) {
                handleOpenChatFromPush(Number(event.data.chatId));
            }
        };
        navigator.serviceWorker.addEventListener('message', handler);
        onUnmounted(() => navigator.serviceWorker.removeEventListener('message', handler));
    }
});

onUnmounted(() => {
    const e = echo.value;
    if (e) {
        leaveChat(e);
        leaveModerator(e);
    }
});

const hasOlder = computed(() => oldestMessageId.value != null && messages.value.length > 0);
</script>

<template>
    <Head :title="t('chat.title')" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-[calc(100vh-8rem)] flex-col gap-4 overflow-hidden rounded-xl p-4 md:flex-row">
            <!-- Chat list -->
            <div
                class="flex w-full flex-col rounded-xl border border-sidebar-border/70 bg-sidebar dark:border-sidebar-border md:w-80 md:shrink-0"
            >
                <div class="border-sidebar-border/70 flex flex-wrap items-center gap-2 border-b p-2 dark:border-sidebar-border">
                    <div class="flex rounded-lg bg-muted/50 p-0.5">
                        <button
                            v-for="tab in (['my', 'unassigned', 'all'] as const)"
                            :key="tab"
                            type="button"
                            :class="[
                                'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                activeTab === tab
                                    ? 'bg-background text-foreground shadow'
                                    : 'text-muted-foreground hover:text-foreground',
                            ]"
                            @click="activeTab = tab"
                        >
                            {{ t(`chat.tabs.${tab}`) }}
                        </button>
                    </div>
                    <div class="flex rounded-lg bg-muted/50 p-0.5">
                        <button
                            type="button"
                            :class="[
                                'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                statusFilter === 'open'
                                    ? 'bg-background text-foreground shadow'
                                    : 'text-muted-foreground hover:text-foreground',
                            ]"
                            @click="statusFilter = 'open'"
                        >
                            {{ t('chat.status.open') }}
                        </button>
                        <button
                            type="button"
                            :class="[
                                'rounded-md px-3 py-1.5 text-sm font-medium transition-colors',
                                statusFilter === 'closed'
                                    ? 'bg-background text-foreground shadow'
                                    : 'text-muted-foreground hover:text-foreground',
                            ]"
                            @click="statusFilter = 'closed'"
                        >
                            {{ t('chat.status.closed') }}
                        </button>
                    </div>
                </div>
                <div class="border-sidebar-border/70 flex items-center gap-2 border-b p-2 dark:border-sidebar-border">
                    <Search class="size-4 shrink-0 text-muted-foreground" />
                    <Input
                        v-model="searchQuery"
                        type="search"
                        :placeholder="t('chat.search')"
                        class="h-8 flex-1"
                    />
                </div>
                <div class="flex-1 overflow-y-auto p-2">
                    <template v-if="chatsLoading && chats.length === 0">
                        <div class="flex items-center justify-center py-8">
                            <Loader2 class="size-6 animate-spin text-muted-foreground" />
                        </div>
                    </template>
                    <template v-else-if="chatsError">
                        <p class="py-4 text-center text-sm text-destructive">{{ chatsError }}</p>
                    </template>
                    <template v-else-if="chats.length === 0">
                        <p class="py-8 text-center text-sm text-muted-foreground">{{ t('chat.noChats') }}</p>
                    </template>
                    <template v-else>
                        <button
                            v-for="chat in chats"
                            :key="chat.id"
                            type="button"
                            :class="[
                                'flex w-full flex-col gap-0.5 rounded-lg border px-3 py-2 text-left transition-colors',
                                selectedChatId === chat.id
                                    ? 'border-primary bg-primary/10 dark:bg-primary/20'
                                    : 'border-transparent hover:bg-muted/50',
                            ]"
                            @click="selectChat(chat)"
                        >
                            <div class="mb-1 flex items-center gap-2">
                                <img
                                    v-if="avatarUrlFromChat(chat)"
                                    :src="avatarUrlFromChat(chat) ?? ''"
                                    alt="avatar"
                                    class="h-6 w-6 rounded-full object-cover"
                                    loading="lazy"
                                    referrerpolicy="no-referrer"
                                />
                                <span
                                    v-else
                                    class="flex h-6 w-6 items-center justify-center rounded-full bg-muted text-[10px] text-muted-foreground"
                                >👤</span>
                                <span class="truncate font-medium">{{ chat.source?.name ?? '—' }}</span>
                            </div>
                            <span class="truncate text-xs text-muted-foreground">
                                {{ resolveInterlocutorName(chat) }}
                            </span>
                            <span class="truncate text-xs text-muted-foreground/80">
                                {{ chat.topic || (chat.latest_message?.text ? (chat.latest_message.text.length > 50 ? chat.latest_message.text.slice(0, 50) + '…' : chat.latest_message.text) : '—') }}
                            </span>
                        </button>
                    </template>
                </div>
            </div>

            <!-- Thread -->
            <div
                class="flex min-h-0 flex-1 flex-col rounded-xl border border-sidebar-border/70 bg-sidebar dark:border-sidebar-border"
            >
                <template v-if="!selectedChat">
                    <div class="flex flex-1 items-center justify-center text-muted-foreground">
                        {{ t('chat.selectChat') }}
                    </div>
                </template>
                <template v-else>
                    <!-- Header: source + interlocutor -->
                    <div
                        class="border-sidebar-border/70 flex flex-wrap items-center justify-between gap-2 border-b p-3 dark:border-sidebar-border"
                    >
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <img
                                    v-if="avatarUrlFromChat(selectedChat)"
                                    :src="avatarUrlFromChat(selectedChat) ?? ''"
                                    alt="avatar"
                                    class="h-7 w-7 rounded-full object-cover"
                                    loading="lazy"
                                    referrerpolicy="no-referrer"
                                />
                                <span
                                    v-else
                                    class="flex h-7 w-7 items-center justify-center rounded-full bg-muted text-xs text-muted-foreground"
                                >👤</span>
                                <h2 class="truncate font-semibold">{{ selectedChat.source?.name ?? '—' }}</h2>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{ t('chat.interlocutor') }}: {{ interlocutorDisplay }}
                            </p>
                            <p v-if="selectedChat.assignee" class="text-xs text-muted-foreground">
                                {{ t('chat.inWork') }}: {{ selectedChat.assignee.name }}
                            </p>
                            <p v-if="typingName" class="text-xs text-muted-foreground">
                                {{ t('chat.typing', { name: typingName }) }}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-1">
                            <Button
                                variant="outline"
                                size="sm"
                                class="gap-1"
                                :disabled="isAssignedToMe || assigningInProgress"
                                @click="assignToMe"
                            >
                                <Loader2 v-if="assigningInProgress" class="size-4 animate-spin" />
                                <UserPlus v-else class="size-4" />
                                {{ isAssignedToMe ? t('chat.assignedToMeLabel') : t('chat.assignToMe') }}
                            </Button>
                            <Button variant="outline" size="sm" class="gap-1" @click="closeChat">
                                <XCircle class="size-4" />
                                {{ t('chat.closeChat') }}
                            </Button>
                            <Button
                                v-if="pushSupported && !pushSubscribed"
                                variant="outline"
                                size="sm"
                                class="gap-1"
                                :disabled="pushLoading"
                                @click="subscribePush().then((ok) => ok && (pushSubscribed = true))"
                            >
                                <Loader2 v-if="pushLoading" class="size-4 animate-spin" />
                                <Bell v-else class="size-4" />
                                {{ t('chat.enableNotifications') }}
                            </Button>
                            <span v-else-if="pushSubscribed" class="text-muted-foreground text-xs">{{ t('chat.notificationsEnabled') }}</span>
                            <p v-if="pushError" class="text-xs text-destructive">{{ pushError }}</p>
                        </div>
                    </div>

                    <!-- Messages -->
                    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
                        <div
                            ref="messagesScrollRef"
                            class="min-h-0 flex-1 overflow-y-auto overflow-x-hidden p-4"
                        >
                            <div v-if="loadingOlder" class="flex justify-center py-2">
                                <Loader2 class="size-5 animate-spin text-muted-foreground" />
                            </div>
                            <button
                                v-if="hasOlder"
                                type="button"
                                class="mb-2 w-full rounded-md py-1 text-center text-sm text-muted-foreground hover:bg-muted/50"
                                @click="loadOlderMessages"
                            >
                                {{ t('chat.loadOlder') }}
                            </button>
                            <template v-if="messagesLoading && messages.length === 0">
                                <div class="flex justify-center py-8">
                                    <Loader2 class="size-6 animate-spin text-muted-foreground" />
                                </div>
                            </template>
                            <template v-else-if="messagesError">
                                <p class="py-4 text-center text-sm text-destructive">{{ messagesError }}</p>
                            </template>
                            <template v-else-if="messages.length === 0">
                                <p class="py-8 text-center text-sm text-muted-foreground">{{ t('chat.noMessages') }}</p>
                            </template>
                            <template v-else>
                                <div class="flex flex-col gap-3">
                                    <ChatMessageItem
                                        v-for="msg in messages"
                                        :key="String(msg.id)"
                                        :message="msg"
                                    />
                                </div>
                            </template>
                        </div>

                        <!-- Canned responses -->
                        <div v-if="cannedResponses.length" class="border-sidebar-border/70 flex flex-wrap gap-1 border-t p-2 dark:border-sidebar-border">
                            <Button
                                v-for="cr in cannedResponses.slice(0, 5)"
                                :key="cr.id"
                                variant="ghost"
                                size="sm"
                                class="text-xs"
                                @click="insertCannedResponse(cr)"
                            >
                                {{ cr.title || cr.code }}
                            </Button>
                        </div>

                        <!-- Input -->
                        <div class="border-sidebar-border/70 border-t p-3 dark:border-sidebar-border">
                            <form
                                class="flex gap-2"
                                @submit.prevent="sendMessage"
                            >
                                <Input
                                    v-model="newMessageText"
                                    type="text"
                                    :placeholder="t('chat.typeMessage')"
                                    class="min-w-0 flex-1"
                                    @input="debouncedSendTyping"
                                />
                                <Button type="submit" :disabled="sendLoading || !newMessageText.trim()">
                                    <Loader2 v-if="sendLoading" class="size-4 animate-spin" />
                                    <Send v-else class="size-4" />
                                </Button>
                            </form>
                            <p v-if="sendError" class="mt-1 text-xs text-destructive">{{ sendError }}</p>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
