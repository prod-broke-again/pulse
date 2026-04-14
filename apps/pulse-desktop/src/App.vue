<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { Minus, Square, X, Moon, Sun, Activity, TriangleAlert, RotateCw } from 'lucide-vue-next'
import ProjectSidebar, { type SidebarView } from './components/chat/ProjectSidebar.vue'
import InboxPanel from './components/chat/InboxPanel.vue'
import ChatHeader from './components/chat/ChatHeader.vue'
import ChatMessages from './components/chat/ChatMessages.vue'
import ChatComposer from './components/chat/ChatComposer.vue'
import ThreadAiPanel from './components/chat/ThreadAiPanel.vue'
import SettingsPage from './components/settings/SettingsPage.vue'
import LoginPage from './components/auth/LoginPage.vue'
import TemplatesManagePage from './components/pages/TemplatesManagePage.vue'
import QuickLinksManagePage from './components/pages/QuickLinksManagePage.vue'
import AnalyticsOverviewPage from './components/pages/AnalyticsOverviewPage.vue'
import { useAuthStore } from './stores/authStore'
import { useChatStore } from './stores/chatStore'
import { useMessageStore } from './stores/messageStore'
import { syncChatHistory } from './api/chats'
import { mapChatToConversation, mapApiMessage } from './utils/mappers'
import { completeOAuthFromCallbackUrl } from './lib/completeOAuthFromUrl'
import { disconnectEcho, getEcho, subscribeModeratorChannel } from './lib/realtime'
import { desktopSoundEnabled, setDesktopSoundEnabled } from './lib/desktopNotifications'
import {
  isModeratorStaffUser,
  startModeratorPresenceForDesktop,
  stopModeratorPresenceForDesktop,
} from './lib/moderatorPresenceDesktop'
import type { ApiMessage } from './types/dto/chat.types'
import type { Conversation, MessageItem } from './types/chat'

const authStore = useAuthStore()
const chatStore = useChatStore()
const messageStore = useMessageStore()

const conversations = computed<Conversation[]>(() =>
  chatStore.chats.map((chat) => mapChatToConversation(chat, chatStore.selectedChatId)),
)

/** Порядок: реальные id по возрастанию, локальные «ожидающие» (отрицательные id) внизу треда. */
function sortMessagesForThread(msgs: ApiMessage[]): ApiMessage[] {
  return [...msgs].sort((a, b) => {
    const ka = a.id < 0 ? 1_000_000_000_000 + Math.abs(a.id) : a.id
    const kb = b.id < 0 ? 1_000_000_000_000 + Math.abs(b.id) : b.id
    return ka - kb
  })
}

const messages = computed<MessageItem[]>(() => sortMessagesForThread(messageStore.messages).map(mapApiMessage))

const currentConversation = computed<Conversation | null>(() => {
  if (!chatStore.selectedChat) return null
  return mapChatToConversation(chatStore.selectedChat, chatStore.selectedChatId)
})

const activeView = ref<SidebarView>('chats')
const isDark = ref(false)
const soundEnabled = ref(typeof localStorage !== 'undefined' ? desktopSoundEnabled() : true)
const toastMessage = ref<string | null>(null)
let toastTimer: ReturnType<typeof setTimeout> | null = null
const chatMessagesRef = ref<InstanceType<typeof ChatMessages> | null>(null)
const isElectron = typeof window !== 'undefined' && typeof window.appWindow !== 'undefined'
const isMaximized = ref(false)
const isDevtoolsOpen = ref(false)
const oauthExchangeError = ref<string | null>(null)
const isOnline = ref(typeof navigator !== 'undefined' ? navigator.onLine : true)
let detachWindowListener: (() => void) | null = null
let detachDevtoolsListener: (() => void) | null = null
let detachOAuthListener: (() => void) | null = null
let unsubscribeModeratorChannel: (() => void) | null = null

const replyToMessageId = ref<number | null>(null)

const replyToPreview = computed(() => {
  const id = replyToMessageId.value
  if (id == null || id <= 0) {
    return null
  }
  const m = messageStore.messages.find((x) => x.id === id)
  if (!m) {
    return null
  }
  return { id: m.id, text: (m.text ?? '').slice(0, 500) }
})

function onReplyToMessage(messageId: number): void {
  replyToMessageId.value = messageId
}

function clearReplyTarget(): void {
  replyToMessageId.value = null
}

function setupModeratorRealtime(): void {
  unsubscribeModeratorChannel?.()
  unsubscribeModeratorChannel = null
  const uid = authStore.user?.id
  if (!authStore.isAuthenticated || uid == null) {
    return
  }
  getEcho()
  unsubscribeModeratorChannel = subscribeModeratorChannel(uid, {
    onNewMessage: (payload) => {
      chatStore.bumpChatFromRealtime(payload)
    },
  })
}

watch(
  () => [authStore.isAuthenticated, authStore.user?.id] as const,
  () => {
    setupModeratorRealtime()
  },
  { immediate: true },
)

watch(
  () => [authStore.isAuthenticated, authStore.user] as const,
  ([auth, user]) => {
    if (auth && isModeratorStaffUser(user)) {
      startModeratorPresenceForDesktop()
    } else {
      stopModeratorPresenceForDesktop()
    }
  },
  { immediate: true },
)

function onBrowserOnline(): void {
  isOnline.value = true
  void messageStore.flushOutboxQueue()
}

function onBrowserOffline(): void {
  isOnline.value = false
}

watch(soundEnabled, (v) => {
  setDesktopSoundEnabled(v)
})

onMounted(async () => {
  window.addEventListener('online', onBrowserOnline)
  window.addEventListener('offline', onBrowserOffline)
  isDark.value = localStorage.getItem('app-theme-dark') === '1'

  if (window.appWindow) {
    window.appWindow.isMaximized().then((value: boolean) => {
      isMaximized.value = value
    })
    window.appWindow.isDevtoolsOpened().then((value: boolean) => {
      isDevtoolsOpen.value = value
    })

    detachWindowListener = window.appWindow.onStateChanged(({ isMaximized: nextState }: { isMaximized: boolean }) => {
      isMaximized.value = nextState
    })
    detachDevtoolsListener = window.appWindow.onDevtoolsVisibilityChanged(({ isOpen }: { isOpen: boolean }) => {
      isDevtoolsOpen.value = isOpen
    })
  }

  if (window.electronOAuth) {
    detachOAuthListener = window.electronOAuth.onCallback(async (url) => {
      oauthExchangeError.value = null
      const result = await completeOAuthFromCallbackUrl(url)
      if (result.ok) {
        await onLoginSuccess()
      } else {
        oauthExchangeError.value = result.message
      }
    })
  }

  try {
    await authStore.fetchMe()
    if (authStore.isAuthenticated) {
      getEcho()
      setupModeratorRealtime()
      await chatStore.loadChats()
      void messageStore.flushOutboxQueue()
    }
  } catch (e) {
    console.error('Auth check failed:', e)
  } finally {
    authStore.finishAuthBootstrap()
  }
})

onBeforeUnmount(() => {
  window.removeEventListener('online', onBrowserOnline)
  window.removeEventListener('offline', onBrowserOffline)
  detachWindowListener?.()
  detachDevtoolsListener?.()
  detachOAuthListener?.()
  unsubscribeModeratorChannel?.()
  unsubscribeModeratorChannel = null
  stopModeratorPresenceForDesktop()
  messageStore.leaveRealtime()
  disconnectEcho()
})

watch(isDark, (value) => {
  document.documentElement.dataset.theme = value ? 'dark' : 'light'
  document.documentElement.classList.toggle('dark', value)
  localStorage.setItem('app-theme-dark', value ? '1' : '0')
}, { immediate: true })

function toggleTheme(): void {
  isDark.value = !isDark.value
}

async function minimizeWindow(): Promise<void> {
  await window.appWindow?.minimize()
}

async function toggleMaximizeWindow(): Promise<void> {
  const state = await window.appWindow?.toggleMaximize()
  if (typeof state === 'boolean') {
    isMaximized.value = state
  }
}

async function closeWindow(): Promise<void> {
  await window.appWindow?.close()
}

const syncHistoryLoading = ref(false)

async function onLoginSuccess(): Promise<void> {
  activeView.value = 'chats'
  oauthExchangeError.value = null
  getEcho()
  setupModeratorRealtime()
  await chatStore.loadChats()
}

async function onSyncHistory(): Promise<void> {
  const id = chatStore.selectedChatId
  if (id == null) {
    return
  }
  syncHistoryLoading.value = true
  try {
    await syncChatHistory(id)
    await messageStore.loadMessages(id)
    await chatStore.loadChats(1)
  } catch (e) {
    console.error('syncHistory failed', e)
  } finally {
    syncHistoryLoading.value = false
  }
}

async function selectChat(chatId: number): Promise<void> {
  replyToMessageId.value = null
  messageStore.leaveRealtime()
  chatStore.selectChat(chatId)
  await messageStore.loadMessages(chatId)
  if (!messageStore.loadError) {
    messageStore.subscribeRealtime(chatId)
  }
}

async function sendMsg(
  text: string,
  attachments: string[] = [],
  replyMarkup?: { text: string; url: string }[],
  replyToFromComposer?: number,
): Promise<void> {
  if (!chatStore.selectedChatId) return
  const rid = replyToFromComposer ?? replyToMessageId.value ?? undefined
  replyToMessageId.value = null
  try {
    await messageStore.sendMessage(chatStore.selectedChatId, text, attachments, replyMarkup, rid)
  } catch (e) {
    console.error('sendMessage failed', e)
  }
}

async function assignToMe(chatId?: number): Promise<void> {
  const id = chatId ?? chatStore.selectedChatId
  if (id == null) {
    return
  }
  await chatStore.assignMe(id)
}

async function closeChatAction(): Promise<void> {
  if (!chatStore.selectedChatId) return
  await chatStore.closeChat(chatStore.selectedChatId)
  messageStore.clearMessages()
}

async function closeChatFromList(chatId: number): Promise<void> {
  await chatStore.closeChat(chatId)
  if (chatStore.selectedChatId === chatId) {
    messageStore.clearMessages()
  }
}

async function onMuteChatFromList(chatId: number, mode: '1h' | '8h' | 'forever' | 'unmute'): Promise<void> {
  try {
    await chatStore.muteChat(chatId, mode)
  } catch (e) {
    console.error('muteChat failed', e)
  }
}

async function onChangeDepartment(departmentId: number): Promise<void> {
  if (!chatStore.selectedChatId) return
  try {
    await chatStore.changeDepartment(chatStore.selectedChatId, departmentId)
  } catch (e) {
    console.error('changeDepartment failed:', e)
  }
}

const canLoadMoreMessages = computed(() => {
  const reals = messageStore.messages.filter((m) => m.id > 0)
  return reals.length > 0 && reals.length % 50 === 0
})

const composerLocked = computed(() => {
  const uid = authStore.user?.id ?? null
  const c = chatStore.selectedChat
  if (!c || uid == null) {
    return false
  }
  const a = c.assigned_to
  if (a == null) {
    return false
  }
  return a !== uid
})

const composerLockHint = computed(() => {
  const c = chatStore.selectedChat
  if (!c) {
    return ''
  }
  const name = c.assignee?.name?.trim()
  return name
    ? `Чат в работе у ${name}. Нажмите «Забрать себе», чтобы ответить.`
    : 'Чат назначен другому модератору. Нажмите «Забрать себе», чтобы ответить.'
})

function loadMoreMessages(): void {
  if (!chatStore.selectedChatId || messageStore.messages.length === 0) return
  const reals = messageStore.messages.filter((m) => m.id > 0)
  if (reals.length === 0) return
  const oldestId = Math.min(...reals.map((m) => m.id))
  messageStore.loadMessages(chatStore.selectedChatId, oldestId)
}

function onSelectSidebar(view: SidebarView): void {
  activeView.value = view
  if (view !== 'chats') {
    messageStore.leaveRealtime()
    chatStore.selectChat(null)
    messageStore.clearMessages()
  }
}

function onMessagesNearBottom(): void {
  const id = chatStore.selectedChatId
  if (id != null) {
    messageStore.onThreadScrolledNearBottom(id)
  }
}

function onMergeMessageContext(rows: ApiMessage[]): void {
  const id = chatStore.selectedChatId
  if (id != null) {
    messageStore.mergeContextMessages(id, rows)
  }
}

function showToast(message: string): void {
  toastMessage.value = message
  if (toastTimer != null) {
    clearTimeout(toastTimer)
  }
  toastTimer = setTimeout(() => {
    toastMessage.value = null
    toastTimer = null
  }, 4000)
}
</script>

<template>
  <div class="flex h-screen min-h-screen w-full flex-col overflow-hidden antialiased lg:min-h-[760px] lg:min-w-[1180px]">
    <!-- Titlebar -->
    <div
      :class="[
        isDevtoolsOpen ? 'no-drag-region' : 'app-drag-region',
        'flex h-[38px] shrink-0 items-center justify-between px-3',
      ]"
      style="background: var(--bg-titlebar); user-select: none"
    >
      <div class="flex items-center gap-2.5">
        <div class="flex h-5 w-5 items-center justify-center rounded-[var(--radius-sm)]" style="background: var(--color-brand-200)">
          <Activity class="h-3 w-3 text-white" />
        </div>
        <span class="text-xs font-semibold tracking-wide" style="color: #c4b8d4">Pulse — АЧПП</span>
      </div>

      <div class="no-drag-region flex items-center gap-0.5">
        <button
          type="button"
          class="flex h-[26px] w-8 items-center justify-center rounded text-[11px] transition hover:bg-white/[0.08]"
          style="color: #8b7a9e"
          :title="isDark ? 'Светлая тема' : 'Тёмная тема'"
          @click="toggleTheme"
        >
          <Moon v-if="!isDark" class="h-3.5 w-3.5" />
          <Sun v-else class="h-3.5 w-3.5" />
        </button>
        <template v-if="isElectron">
          <button
            type="button"
            class="flex h-[26px] w-8 items-center justify-center rounded text-[11px] transition hover:bg-white/[0.08]"
            style="color: #8b7a9e"
            title="Свернуть"
            @click="minimizeWindow"
          >
            <Minus class="h-3.5 w-3.5" />
          </button>
          <button
            type="button"
            class="flex h-[26px] w-8 items-center justify-center rounded text-[11px] transition hover:bg-white/[0.08]"
            style="color: #8b7a9e"
            :title="isMaximized ? 'Восстановить' : 'На весь экран'"
            @click="toggleMaximizeWindow"
          >
            <Square class="h-3 w-3" />
          </button>
          <button
            type="button"
            class="flex h-[26px] w-8 items-center justify-center rounded text-[11px] transition hover:bg-[#ef4444] hover:text-white"
            style="color: #8b7a9e"
            title="Закрыть"
            @click="closeWindow"
          >
            <X class="h-3.5 w-3.5" />
          </button>
        </template>
      </div>
    </div>

    <div
      v-if="!isOnline"
      class="flex shrink-0 items-center justify-center gap-2 px-3 py-1.5 text-xs font-medium"
      style="background: rgba(234, 179, 8, 0.12); color: var(--text-secondary, #57534e)"
    >
      <TriangleAlert class="h-3.5 w-3.5 shrink-0" style="color: #ca8a04" />
      Нет сети: история из локального кэша, исходящие в очереди и уйдут при подключении.
    </div>

    <main v-if="authStore.isAuthenticated" class="app-layout flex min-h-0 flex-1 w-full overflow-hidden">
      <ProjectSidebar :active-view="activeView" @select="onSelectSidebar" />

      <template v-if="activeView === 'chats'">
        <InboxPanel
          :conversations="conversations"
          :active-tab="chatStore.activeTab ?? 'my'"
          :tab-counts="chatStore.tabCounts"
          :is-loading="chatStore.isLoading"
          :selected-chat-id="chatStore.selectedChatId"
          :can-load-more="chatStore.pagination.current_page < chatStore.pagination.last_page"
          @select-chat="(id) => void selectChat(id)"
          @change-tab="chatStore.setTab"
          @load-more="chatStore.loadMore"
          @change-status="(s) => chatStore.setFilters({ status: s })"
          @assign-me="assignToMe"
          @close-chat="closeChatFromList"
          @mute-chat="onMuteChatFromList"
        />

        <section class="thread-area flex min-h-0 min-w-0 flex-1 flex-col" style="background: var(--bg-thread)">
          <template v-if="!currentConversation">
            <div class="thread-empty-state">
              <svg class="thread-empty-state-icon h-10 w-10" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
              </svg>
              <div class="thread-empty-state-title">
                Выберите обращение
              </div>
              <p class="thread-empty-state-text">
                Выберите диалог из списка слева, чтобы начать работу с обращением
              </p>
            </div>
          </template>

          <template v-else>
            <ChatHeader
              :active-conversation="currentConversation"
              :current-user-id="authStore.user?.id ?? null"
              :sync-history-loading="syncHistoryLoading"
              @assign-me="assignToMe"
              @close-chat="closeChatAction"
              @change-department="onChangeDepartment"
              @sync-history="onSyncHistory"
            />

            <div
              v-if="messageStore.loadError"
              class="error-state"
            >
              <TriangleAlert class="error-state-icon" aria-hidden="true" />
              <div class="error-state-title">
                Не удалось загрузить переписку
              </div>
              <p class="error-state-text">
                Проверьте подключение к интернету и попробуйте снова
              </p>
              <button
                type="button"
                class="btn btn-primary"
                @click="messageStore.retryLoad(chatStore.selectedChatId!)"
              >
                <RotateCw class="h-3.5 w-3.5" />
                Повторить
              </button>
            </div>

            <div v-else class="flex min-h-0 flex-1 flex-col overflow-hidden">
              <ChatMessages
                ref="chatMessagesRef"
                :timeline="messages"
                :peer-name="currentConversation.name"
                :chat-id="chatStore.selectedChatId"
                :client-typing="messageStore.clientTyping"
                :is-loading="messageStore.isLoading"
                :can-load-more="canLoadMoreMessages"
                @load-more="loadMoreMessages"
                @near-bottom="onMessagesNearBottom"
                @reply="onReplyToMessage"
                @merge-context="onMergeMessageContext"
                @toast="showToast"
              />
              <ThreadAiPanel :chat-id="chatStore.selectedChatId" />
              <ChatComposer
                :is-sending="messageStore.isSending"
                :reply-to-preview="replyToPreview"
                :composer-locked="composerLocked"
                :composer-lock-hint="composerLockHint"
                @clear-reply="clearReplyTarget"
                @send="sendMsg"
              />
            </div>
          </template>
        </section>
      </template>

      <AnalyticsOverviewPage v-else-if="activeView === 'analytics'" />
      <TemplatesManagePage v-else-if="activeView === 'templates'" />
      <QuickLinksManagePage v-else-if="activeView === 'quickLinks'" />
      <SettingsPage
        v-else
        :is-dark="isDark"
        :sound-enabled="soundEnabled"
        @toggle-theme="toggleTheme"
        @toggle-sound="soundEnabled = !soundEnabled"
      />
    </main>

    <div
      v-else-if="authStore.authBootstrapPending"
      class="flex min-h-0 flex-1 flex-col items-center justify-center gap-3 px-6 text-center text-sm"
      style="color: var(--text-secondary)"
    >
      <Activity class="h-8 w-8 shrink-0 animate-pulse" style="color: var(--color-brand-200)" aria-hidden="true" />
      <span>Восстановление сессии…</span>
    </div>

    <div v-else class="flex min-h-0 flex-1 flex-col">
      <p v-if="oauthExchangeError" class="shrink-0 px-4 py-2 text-center text-sm text-red-600 dark:text-red-400" role="alert">
        {{ oauthExchangeError }}
      </p>
      <LoginPage @login-success="onLoginSuccess" />
    </div>

    <div
      v-if="toastMessage"
      class="pointer-events-none fixed bottom-6 left-1/2 z-[100] max-w-md -translate-x-1/2 rounded-lg px-4 py-2 text-center text-sm shadow-lg"
      style="background: var(--bg-inbox); color: var(--text-primary); border: 1px solid var(--border-light)"
      role="status"
    >
      {{ toastMessage }}
    </div>
  </div>
</template>
