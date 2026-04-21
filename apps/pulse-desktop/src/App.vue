<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
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
import { useMessageStore, notifyIncomingForModeratorInbox } from './stores/messageStore'
import { syncChatHistory } from './api/chats'
import { mapChatToConversation, mapApiMessage } from './utils/mappers'
import { completeOAuthFromCallbackUrl } from './lib/completeOAuthFromUrl'
import { disconnectEcho, getEcho, subscribeModeratorChannel, subscribeSourceInbox } from './lib/realtime'
import { desktopSoundEnabled, setDesktopSoundEnabled } from './lib/desktopNotifications'
import { patchNotificationSoundPreferences } from './api/notificationSoundPreferences'
import {
  isModeratorStaffUser,
  startModeratorPresenceForDesktop,
  stopModeratorPresenceForDesktop,
} from './lib/moderatorPresenceDesktop'
import {
  installDesktopUpdateNow,
  maybeCheckDesktopUpdate,
  onDesktopUpdaterStatus,
  type DesktopUpdateStatus,
} from './lib/desktopUpdater'
import type { ApiMessage } from './types/dto/chat.types'
import type { Conversation, MessageItem } from './types/chat'

const authStore = useAuthStore()
const chatStore = useChatStore()
const messageStore = useMessageStore()

const THEME_MODE_KEY = 'app-theme-mode'
const LEGACY_THEME_DARK_KEY = 'app-theme-dark'
type ThemeMode = 'light' | 'dark' | 'system'

function detectSystemDark(): boolean {
  if (typeof window === 'undefined' || typeof window.matchMedia !== 'function') {
    return false
  }
  return window.matchMedia('(prefers-color-scheme: dark)').matches
}

function readStoredThemeMode(): ThemeMode {
  if (typeof localStorage === 'undefined') {
    return 'system'
  }
  const raw = localStorage.getItem(THEME_MODE_KEY)
  if (raw === 'light' || raw === 'dark' || raw === 'system') {
    return raw
  }
  // Backward compatibility for old boolean flag.
  const legacy = localStorage.getItem(LEGACY_THEME_DARK_KEY)
  if (legacy === '1') {
    return 'dark'
  }
  if (legacy === '0') {
    return 'light'
  }
  return 'system'
}

function resolveDarkByMode(mode: ThemeMode): boolean {
  if (mode === 'dark') {
    return true
  }
  if (mode === 'light') {
    return false
  }
  return detectSystemDark()
}

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
const themeMode = ref<ThemeMode>(readStoredThemeMode())
const isDark = ref(resolveDarkByMode(themeMode.value))
const soundEnabled = ref(typeof localStorage !== 'undefined' ? desktopSoundEnabled() : true)
const toastMessage = ref<string | null>(null)
let toastTimer: ReturnType<typeof setTimeout> | null = null
const chatMessagesRef = ref<InstanceType<typeof ChatMessages> | null>(null)
const chatComposerRef = ref<InstanceType<typeof ChatComposer> | null>(null)
/** Панель AI скрыта по умолчанию; открытие с кнопки Sparkles в композере. */
const showThreadAiPanel = ref(false)
const isElectron = typeof window !== 'undefined' && typeof window.appWindow !== 'undefined'
const isMaximized = ref(false)
const isDevtoolsOpen = ref(false)
const oauthExchangeError = ref<string | null>(null)
const isOnline = ref(typeof navigator !== 'undefined' ? navigator.onLine : true)
const updateDialogOpen = ref(false)
const updateDialogVersion = ref<string | null>(null)
const updateDialogNotes = ref<string | null>(null)
const updateDialogMessage = ref<string>('Обновление загружено и готово к установке.')
const updateDialogCustomMessage = ref(
  'В этом обновлении улучшены стабильность, установка и интерфейс. Рекомендуем установить сейчас.',
)
let detachWindowListener: (() => void) | null = null
let detachDevtoolsListener: (() => void) | null = null
let detachOAuthListener: (() => void) | null = null
let detachDesktopUpdaterListener: (() => void) | null = null
let unsubscribeModeratorChannel: (() => void) | null = null
/** Unsubscribe all `source-inbox.{id}` channels for current user. */
let teardownSourceInboxRealtime: (() => void) | null = null

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

function toggleThreadAiPanel(): void {
  showThreadAiPanel.value = !showThreadAiPanel.value
}

watch(
  () => chatStore.selectedChatId,
  () => {
    showThreadAiPanel.value = false
  },
)

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
      void notifyIncomingForModeratorInbox({
        chatId: payload.chatId,
        messageId: payload.messageId,
        text: payload.text ?? '',
        sender_type: payload.sender_type,
      })
    },
    onChatTopicGenerated: (payload) => {
      chatStore.applyChatTopicFromRealtime(payload.chatId, payload.topic)
    },
  })
}

function setupSourceInboxRealtime(): void {
  teardownSourceInboxRealtime?.()
  teardownSourceInboxRealtime = null
  if (!authStore.isAuthenticated || authStore.user?.id == null) {
    return
  }
  const sourceIds = authStore.user.source_ids ?? []
  if (sourceIds.length === 0) {
    return
  }
  getEcho()
  const unsubs: Array<() => void> = []
  for (const sid of sourceIds) {
    unsubs.push(
      subscribeSourceInbox(sid, {
        onNewMessage: (payload) => {
          chatStore.bumpChatFromRealtime(payload)
          void notifyIncomingForModeratorInbox({
            chatId: payload.chatId,
            messageId: payload.messageId,
            text: payload.text ?? '',
            sender_type: payload.sender_type,
          })
        },
        onChatTopicGenerated: (payload) => {
          chatStore.applyChatTopicFromRealtime(payload.chatId, payload.topic)
        },
      }),
    )
  }
  teardownSourceInboxRealtime = () => {
    for (const u of unsubs) {
      try {
        u()
      } catch {
        // noop
      }
    }
  }
}

watch(
  () =>
    [
      authStore.isAuthenticated,
      authStore.user?.id,
      (authStore.user?.source_ids ?? []).slice().sort().join(','),
    ] as const,
  () => {
    setupModeratorRealtime()
    setupSourceInboxRealtime()
  },
  { immediate: true },
)

watch(
  () => [authStore.isAuthenticated, authStore.user] as const,
  ([auth, user]) => {
    if (auth && isModeratorStaffUser(user)) {
      startModeratorPresenceForDesktop({
        onHeartbeatTick: () => {
          void maybeCheckDesktopUpdate()
        },
      })
    } else {
      stopModeratorPresenceForDesktop()
    }
  },
  { immediate: true },
)

function onDesktopUpdateStatus(payload: DesktopUpdateStatus): void {
  if (payload.code === 'downloaded') {
    updateDialogVersion.value = payload.version ?? null
    updateDialogNotes.value = payload.releaseNotes ?? null
    updateDialogMessage.value = payload.message
    updateDialogOpen.value = true
    return
  }
  if (payload.code === 'available' || payload.code === 'error') {
    showToast(payload.message)
  }
}

function closeUpdateDialog(): void {
  updateDialogOpen.value = false
}

function installUpdateFromDialog(): void {
  updateDialogOpen.value = false
  void installDesktopUpdateNow()
}

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

watch(
  () => authStore.user?.notification_sound_prefs,
  (p) => {
    if (p && typeof p.mute === 'boolean') {
      soundEnabled.value = !p.mute
    }
  },
  { deep: true, immediate: true },
)

async function toggleSoundFromHeader(): Promise<void> {
  const next = !soundEnabled.value
  soundEnabled.value = next
  setDesktopSoundEnabled(next)
  if (!authStore.user || !isModeratorStaffUser(authStore.user)) {
    return
  }
  try {
    const data = await patchNotificationSoundPreferences({ mute: !next })
    authStore.applyUserProfile(data.user)
  } catch {
    soundEnabled.value = !next
    setDesktopSoundEnabled(!next)
  }
}

onMounted(async () => {
  detachDesktopUpdaterListener = onDesktopUpdaterStatus(onDesktopUpdateStatus)

  if (window.pulseWindowSettings?.onCloseRequested) {
    detachCloseRequestedListener = window.pulseWindowSettings.onCloseRequested(() => {
      closeChoiceRemember.value = false
      closeChoiceDialogOpen.value = true
    })
  }

  window.addEventListener('online', onBrowserOnline)
  window.addEventListener('offline', onBrowserOffline)

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
    void maybeCheckDesktopUpdate(true)
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
  detachCloseRequestedListener?.()
  detachCloseRequestedListener = null
  detachDesktopUpdaterListener?.()
  detachDesktopUpdaterListener = null
  window.removeEventListener('online', onBrowserOnline)
  window.removeEventListener('offline', onBrowserOffline)
  detachWindowListener?.()
  detachDevtoolsListener?.()
  detachOAuthListener?.()
  teardownSourceInboxRealtime?.()
  teardownSourceInboxRealtime = null
  unsubscribeModeratorChannel?.()
  unsubscribeModeratorChannel = null
  stopModeratorPresenceForDesktop()
  messageStore.leaveRealtime()
  disconnectEcho()
})

watch(isDark, (value) => {
  document.documentElement.dataset.theme = value ? 'dark' : 'light'
  document.documentElement.classList.toggle('dark', value)
}, { immediate: true })

watch(themeMode, (mode) => {
  isDark.value = resolveDarkByMode(mode)
  if (typeof localStorage !== 'undefined') {
    localStorage.setItem(THEME_MODE_KEY, mode)
    localStorage.removeItem(LEGACY_THEME_DARK_KEY)
  }
}, { immediate: true })

if (typeof window !== 'undefined' && typeof window.matchMedia === 'function') {
  const mm = window.matchMedia('(prefers-color-scheme: dark)')
  const onSystemThemeChange = (): void => {
    if (themeMode.value === 'system') {
      isDark.value = mm.matches
    }
  }
  if (typeof mm.addEventListener === 'function') {
    mm.addEventListener('change', onSystemThemeChange)
    onBeforeUnmount(() => mm.removeEventListener('change', onSystemThemeChange))
  } else if (typeof mm.addListener === 'function') {
    mm.addListener(onSystemThemeChange)
    onBeforeUnmount(() => mm.removeListener(onSystemThemeChange))
  }
}

function toggleTheme(): void {
  themeMode.value = isDark.value ? 'light' : 'dark'
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
  await window.appWindow?.requestClose()
}

const closeChoiceDialogOpen = ref(false)
const closeChoiceRemember = ref(false)
let detachCloseRequestedListener: (() => void) | null = null

async function confirmCloseDialog(action: 'quit' | 'hide-to-tray' | 'cancel'): Promise<void> {
  if (action === 'cancel') {
    closeChoiceDialogOpen.value = false
    return
  }
  closeChoiceDialogOpen.value = false
  await window.pulseWindowSettings?.confirmClose({
    action,
    remember: closeChoiceRemember.value,
  })
  closeChoiceRemember.value = false
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

async function onAiInsertComposerText(text: string): Promise<void> {
  if (composerLocked.value) {
    showToast(composerLockHint.value || 'Чат в работе у другого модератора. Заберите чат себе, чтобы ответить.')
    return
  }
  chatComposerRef.value?.insertFromAi(text)
  await nextTick()
  chatComposerRef.value?.focusComposer?.()
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
          :current-user-id="authStore.user?.id ?? null"
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
          @reopen-chat="assignToMe"
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
              @reopen-chat="assignToMe"
              @change-department="onChangeDepartment"
              @sync-history="onSyncHistory"
              @select-messages-for-copy="() => chatMessagesRef?.enterSelectionMode()"
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
                :moderator-name="authStore.user?.name?.trim() || 'Модератор'"
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
              <ThreadAiPanel
                v-show="showThreadAiPanel"
                :reveal="showThreadAiPanel"
                :chat-id="chatStore.selectedChatId"
                @insert-composer-text="onAiInsertComposerText"
                @notify="showToast"
              />
              <ChatComposer
                ref="chatComposerRef"
                :is-sending="messageStore.isSending"
                :reply-to-preview="replyToPreview"
                :composer-locked="composerLocked"
                :composer-lock-hint="composerLockHint"
                :thread-ai-panel-open="showThreadAiPanel"
                @clear-reply="clearReplyTarget"
                @toggle-thread-ai="toggleThreadAiPanel"
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
        @toggle-sound="toggleSoundFromHeader"
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

    <Teleport to="body">
      <div
        v-if="updateDialogOpen && isElectron"
        class="fixed inset-0 z-[290] flex items-center justify-center px-4 py-8"
        style="background: rgba(15, 10, 25, 0.55); backdrop-filter: blur(4px)"
        role="presentation"
        @click.self="closeUpdateDialog"
      >
        <div
          class="no-drag-region w-full max-w-lg rounded-2xl border p-6 shadow-2xl"
          style="background: var(--bg-thread); border-color: var(--border-light)"
          role="dialog"
          aria-modal="true"
          aria-labelledby="pulse-update-title"
          @click.stop
        >
          <h2 id="pulse-update-title" class="text-lg font-bold leading-snug" style="color: var(--text-primary)">
            Доступно обновление{{ updateDialogVersion ? ` ${updateDialogVersion}` : '' }}
          </h2>
          <p class="mt-3 text-sm leading-relaxed" style="color: var(--text-secondary)">
            {{ updateDialogCustomMessage }}
          </p>
          <p class="mt-2 text-sm leading-relaxed" style="color: var(--text-secondary)">
            {{ updateDialogMessage }}
          </p>

          <div
            v-if="updateDialogNotes?.trim()"
            class="mt-4 rounded-[var(--radius-md)] border p-3"
            style="border-color: var(--border-light); background: var(--bg-inbox)"
          >
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide" style="color: var(--text-muted)">
              Что нового
            </p>
            <!-- GitHub / electron-updater отдают release notes как HTML -->
            <div class="update-release-notes text-sm" v-html="updateDialogNotes" />
          </div>

          <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:gap-3">
            <button
              type="button"
              class="rounded-[var(--radius-md)] px-4 py-2.5 text-sm font-semibold transition hover:opacity-90"
              style="border: 1px solid var(--border-light); color: var(--text-primary); background: transparent"
              @click="closeUpdateDialog"
            >
              Позже
            </button>
            <button
              type="button"
              class="rounded-[var(--radius-md)] px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-95"
              style="background: var(--color-brand)"
              @click="installUpdateFromDialog"
            >
              Установить сейчас
            </button>
          </div>
        </div>
      </div>

      <div
        v-if="closeChoiceDialogOpen && isElectron"
        class="fixed inset-0 z-[300] flex items-center justify-center px-4 py-8"
        style="background: rgba(15, 10, 25, 0.55); backdrop-filter: blur(4px)"
        role="presentation"
        @click.self="confirmCloseDialog('cancel')"
      >
        <div
          class="no-drag-region w-full max-w-md rounded-2xl border p-6 shadow-2xl"
          style="background: var(--bg-thread); border-color: var(--border-light)"
          role="dialog"
          aria-modal="true"
          aria-labelledby="pulse-close-choice-title"
          @click.stop
        >
          <h2 id="pulse-close-choice-title" class="text-lg font-bold leading-snug" style="color: var(--text-primary)">
            Закрыть окно?
          </h2>
          <p class="mt-3 text-sm leading-relaxed" style="color: var(--text-secondary)">
            Pulse может продолжить работу в фоне: обращения и уведомления будут приходить, пока вы не выйдете через иконку в
            области уведомлений (трей). Полный выход завершит приложение.
          </p>
          <label class="mt-4 flex cursor-pointer items-start gap-2.5 text-sm" style="color: var(--text-primary)">
            <input
              v-model="closeChoiceRemember"
              type="checkbox"
              class="mt-0.5 h-4 w-4 rounded border"
              style="border-color: var(--border-light); accent-color: var(--color-brand)"
            >
            <span>Запомнить выбор и не показывать это окно снова</span>
          </label>
          <p class="mt-2 text-xs leading-relaxed" style="color: var(--text-muted)">
            Позже это можно изменить в разделе «Настройки».
          </p>
          <div class="mt-6 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end sm:gap-3">
            <button
              type="button"
              class="rounded-[var(--radius-md)] px-4 py-2.5 text-sm font-semibold transition hover:opacity-90"
              style="border: 1px solid var(--border-light); color: var(--text-primary); background: transparent"
              @click="confirmCloseDialog('cancel')"
            >
              Отмена
            </button>
            <button
              type="button"
              class="rounded-[var(--radius-md)] px-4 py-2.5 text-sm font-semibold transition hover:opacity-90"
              style="border: 1px solid var(--border-light); color: var(--text-primary); background: var(--bg-inbox)"
              @click="confirmCloseDialog('hide-to-tray')"
            >
              Оставить в фоне
            </button>
            <button
              type="button"
              class="rounded-[var(--radius-md)] px-4 py-2.5 text-sm font-semibold text-white transition hover:opacity-95"
              style="background: var(--color-brand)"
              @click="confirmCloseDialog('quit')"
            >
              Выйти из приложения
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.update-release-notes :deep(h1),
.update-release-notes :deep(h2),
.update-release-notes :deep(h3) {
  margin: 0 0 0.5rem;
  font-size: 0.9375rem;
  font-weight: 700;
  line-height: 1.35;
  color: var(--text-primary);
}

.update-release-notes :deep(p) {
  margin: 0 0 0.5rem;
  line-height: 1.55;
  color: var(--text-secondary);
}

.update-release-notes :deep(p:last-child) {
  margin-bottom: 0;
}

.update-release-notes :deep(ul),
.update-release-notes :deep(ol) {
  margin: 0.35rem 0 0.5rem;
  padding-left: 1.25rem;
  color: var(--text-secondary);
  line-height: 1.55;
}

.update-release-notes :deep(li) {
  margin: 0.2rem 0;
}

.update-release-notes :deep(strong) {
  font-weight: 600;
  color: var(--text-primary);
}

.update-release-notes :deep(a) {
  color: var(--color-brand-200);
  text-decoration: underline;
}
</style>
