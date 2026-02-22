<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { api, type ApiChat, type ApiCannedResponse, type ApiMessage } from '@/lib/api';
import {
    getEcho,
    leaveChat,
    leaveModerator,
    subscribeToChat,
    subscribeToModerator,
} from '@/composables/useEcho';
import Button from '@/components/ui/button/Button.vue';
import { Input } from '@/components/ui/input';
import { Search, UserPlus, XCircle, Send, Loader2, Bell } from 'lucide-vue-next';
import { useWebPush } from '@/composables/useWebPush';

const page = usePage();
const authUser = computed(() => (page.props.auth as { user?: { id: number } })?.user);
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
const messages = ref<ApiMessage[]>([]);
const messagesLoading = ref(false);
const messagesError = ref<string | null>(null);
const oldestMessageId = ref<number | null>(null);
const loadingOlder = ref(false);

const newMessageText = ref('');
const sendLoading = ref(false);
const sendError = ref<string | null>(null);

const cannedResponses = ref<ApiCannedResponse[]>([]);
const typingName = ref<string | null>(null);

function fetchChats() {
    chatsLoading.value = true;
    chatsError.value = null;
    api
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

function fetchMessages(chatId: number, beforeId: number | null = null) {
    if (!beforeId) messagesLoading.value = true;
    else loadingOlder.value = true;
    messagesError.value = null;
    const params: Record<string, string | number> = { limit: 50 };
    if (beforeId) params.before_id = beforeId;
    api
        .get<{ data: ApiMessage[] }>(`/chats/${chatId}/messages`, params)
        .then((res) => {
            const list = (res as { data: ApiMessage[] }).data ?? [];
            if (!beforeId) {
                messages.value = list;
                oldestMessageId.value = list.length ? list[0]?.id ?? null : null;
            } else {
                messages.value = [...list, ...messages.value];
                oldestMessageId.value = list.length ? list[0]?.id ?? null : oldestMessageId.value;
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
    messages.value = [];
    oldestMessageId.value = null;
    newMessageText.value = '';
    sendError.value = null;
    typingName.value = null;
    if (chat.source_id) fetchCannedResponses(chat.source_id);
    fetchMessages(chat.id);
}

function loadOlderMessages() {
    if (!selectedChatId.value || loadingOlder.value || !oldestMessageId.value) return;
    fetchMessages(selectedChatId.value, oldestMessageId.value);
}

function assignToMe() {
    if (!selectedChatId.value) return;
    api.post(`/chats/${selectedChatId.value}/assign-me`).then(() => {
        fetchChats();
        const c = chats.value.find((x) => x.id === selectedChatId.value);
        if (c) selectedChat.value = c;
    }).catch(() => {});
}

function closeChat() {
    if (!selectedChatId.value) return;
    api.post(`/chats/${selectedChatId.value}/close`).then(() => {
        selectedChatId.value = null;
        selectedChat.value = null;
        messages.value = [];
        fetchChats();
    }).catch(() => {});
}

function sendTyping() {
    if (!selectedChatId.value) return;
    api.post(`/chats/${selectedChatId.value}/typing`).catch(() => {});
}

function sendMessage() {
    const text = newMessageText.value.trim();
    if (!selectedChatId.value || (!text && !sendLoading.value)) return;
    sendLoading.value = true;
    sendError.value = null;
    api
        .post<{ data: ApiMessage }>(`/chats/${selectedChatId.value}/send`, { text, attachments: [] })
        .then((res) => {
            const msg = (res as { data: ApiMessage }).data;
            if (msg) messages.value = [...messages.value, msg];
            newMessageText.value = '';
        })
        .catch((e) => {
            sendError.value = e instanceof Error ? e.message : t('errors.sendMessage');
        })
        .finally(() => {
            sendLoading.value = false;
        });
}

function insertCannedResponse(item: ApiCannedResponse) {
    newMessageText.value = item.text;
}

watch([activeTab, statusFilter], () => fetchChats(), { immediate: false });
watch(searchQuery, () => {
    const q = searchQuery.value;
    if (q.length >= 2 || q === '') fetchChats();
});

const echo = computed(() => getEcho(reverbConfig.value, authUser.value?.id ?? null));

watch(
    [echo, selectedChatId],
    () => {
        const e = echo.value;
        const chatId = selectedChatId.value;
        if (!e) return;
        if (chatId) {
            subscribeToChat(e, chatId, {
                onNewMessage: (payload) => {
                    if (payload.chatId !== selectedChatId.value) return;
                    messages.value = [
                        ...messages.value,
                        {
                            id: payload.messageId,
                            chat_id: payload.chatId,
                            sender_id: null,
                            sender_type: 'client',
                            text: payload.text,
                            payload: {},
                            attachments: [],
                            is_read: false,
                            created_at: new Date().toISOString(),
                            updated_at: new Date().toISOString(),
                        } as ApiMessage,
                    ];
                },
                onTyping: (payload) => {
                    typingName.value = payload.sender_type === 'moderator' ? payload.sender_name ?? null : null;
                },
                onAssigned: () => {
                    fetchChats();
                    if (selectedChat.value) {
                        const c = chats.value.find((x) => x.id === selectedChat.value!.id);
                        if (c) selectedChat.value = c;
                    }
                },
            });
        } else {
            leaveChat(e);
            typingName.value = null;
        }
    },
    { immediate: true },
);

onMounted(() => {
    fetchChats();
    const e = echo.value;
    const uid = authUser.value?.id;
    if (e && uid) {
        subscribeToModerator(e, uid, { onAssigned: fetchChats });
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
                            v-for="tab in ['my', 'unassigned', 'all']"
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
                            <span class="truncate font-medium">{{ chat.source?.name ?? '—' }}</span>
                            <span class="truncate text-xs text-muted-foreground">
                                {{ chat.latest_message?.text ?? chat.external_user_id }}
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
                    <!-- Header -->
                    <div
                        class="border-sidebar-border/70 flex flex-wrap items-center justify-between gap-2 border-b p-3 dark:border-sidebar-border"
                    >
                        <div class="min-w-0">
                            <h2 class="truncate font-semibold">{{ selectedChat.source?.name ?? '—' }}</h2>
                            <p v-if="typingName" class="text-xs text-muted-foreground">
                                {{ t('chat.typing', { name: typingName }) }}
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-1">
                            <Button variant="outline" size="sm" class="gap-1" @click="assignToMe">
                                <UserPlus class="size-4" />
                                {{ t('chat.assignToMe') }}
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
                        <div class="flex-1 overflow-y-auto p-4">
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
                                    <div
                                        v-for="msg in messages"
                                        :key="msg.id"
                                        :class="[
                                            'max-w-[75%] rounded-2xl px-4 py-2 text-sm',
                                            msg.sender_type === 'moderator'
                                                ? 'ml-auto rounded-br-md bg-primary text-primary-foreground'
                                                : 'rounded-bl-md bg-muted',
                                        ]"
                                    >
                                        <p class="whitespace-pre-wrap break-words">{{ msg.text }}</p>
                                        <p class="mt-1 text-xs opacity-80">
                                            {{ new Date(msg.created_at).toLocaleString() }}
                                        </p>
                                    </div>
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
                                    @input="sendTyping"
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
