import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'
import * as chatApi from '../api/chatRepository'
import { isMutedUntilActive } from '../lib/chatMute'
import { parseApiChatId } from '../lib/chatIds'
import {
  mergeNotificationSoundPrefs,
} from '../lib/notificationSoundPresets'
import {
  playIncomingToneFromPrefs,
  resolveNotificationScenario,
  vibrateIncoming,
} from '../lib/notificationFeedback'
import { getEcho, subscribeModeratorChannel } from '../lib/realtime'
import type { NewChatMessagePayload } from '../lib/realtime'
import { mapApiChatToPreview } from '../mappers/chatMapper'
import { useAuthStore } from './authStore'
import { useSettingsStore } from './settingsStore'
import type { BottomNavId, ChatPreviewItem, FilterId, InboxTab } from '../types/chat'

let moderatorUnsub: (() => void) | null = null

function deriveApiStatus(filters: Set<FilterId>): 'open' | 'closed' | 'all' {
  const hasOpen = filters.has('open')
  const hasClosed = filters.has('closed')
  if (hasOpen && hasClosed) return 'all'
  if (hasOpen) return 'open'
  if (hasClosed) return 'closed'
  return 'all'
}

function deriveChannels(filters: Set<FilterId>): string[] | undefined {
  const ch = (['tg', 'vk', 'web'] as const).filter((c) => filters.has(c))
  return ch.length > 0 ? [...ch] : undefined
}

export const useInboxStore = defineStore('inbox', () => {
  const chats = ref<ChatPreviewItem[]>([])
  const activeTab = ref<InboxTab>('my')
  const activeFilters = ref<Set<FilterId>>(new Set(['open']))
  const searchQuery = ref('')
  const bottomNav = ref<BottomNavId>('inbox')
  const isLoadingList = ref(false)
  const loadError = ref(false)
  const tabBadges = ref({ my: 0, unassigned: 0, all: 0 })
  const inboxBadge = ref(0)

  const historyChats = ref<ChatPreviewItem[]>([])
  const historySearchQuery = ref('')
  const isLoadingHistory = ref(false)

  const filteredChats = computed(() => chats.value)

  const filteredHistoryChats = computed(() => historyChats.value)

  const showEmptyState = computed(
    () => !isLoadingList.value && !loadError.value && filteredChats.value.length === 0,
  )

  const showChatList = computed(
    () => !isLoadingList.value && !loadError.value && filteredChats.value.length > 0,
  )

  function setActiveTab(tab: InboxTab) {
    activeTab.value = tab
  }

  function toggleFilter(id: FilterId) {
    const next = new Set(activeFilters.value)
    if (next.has(id)) {
      next.delete(id)
    } else {
      next.add(id)
    }
    activeFilters.value = next
  }

  function setSearchQuery(value: string) {
    searchQuery.value = value
  }

  function setHistorySearchQuery(value: string) {
    historySearchQuery.value = value
  }

  function setBottomNav(nav: BottomNavId) {
    bottomNav.value = nav
  }

  function setLoadError(value: boolean) {
    loadError.value = value
  }

  function retryLoad() {
    loadError.value = false
    void loadInbox()
  }

  async function loadInbox(): Promise<void> {
    isLoadingList.value = true
    loadError.value = false
    try {
      const status = deriveApiStatus(activeFilters.value)
      const channels = deriveChannels(activeFilters.value)
      const [listRes, counts] = await Promise.all([
        chatApi.fetchChats({
          tab: activeTab.value,
          status,
          search: searchQuery.value.trim() || undefined,
          channels,
          page: 1,
          per_page: 50,
        }),
        chatApi.fetchTabCounts({
          status,
          search: searchQuery.value.trim() || undefined,
          channels,
        }),
      ])
      chats.value = listRes.data.map(mapApiChatToPreview)
      tabBadges.value = counts
      inboxBadge.value = chats.value.reduce((s, c) => s + (c.unreadCount ?? (c.unread ? 1 : 0)), 0)
    } catch {
      loadError.value = true
    } finally {
      isLoadingList.value = false
    }
  }

  async function loadHistory(): Promise<void> {
    isLoadingHistory.value = true
    try {
      const listRes = await chatApi.fetchChats({
        tab: 'all',
        status: 'closed',
        search: historySearchQuery.value.trim() || undefined,
        page: 1,
        per_page: 100,
      })
      historyChats.value = listRes.data.map(mapApiChatToPreview)
    } finally {
      isLoadingHistory.value = false
    }
  }

  watch([activeTab, activeFilters], () => {
    void loadInbox()
  }, { deep: true })

  let searchDebounce: ReturnType<typeof setTimeout> | null = null
  watch(searchQuery, () => {
    if (searchDebounce) clearTimeout(searchDebounce)
    searchDebounce = setTimeout(() => {
      void loadInbox()
    }, 400)
  })

  let historySearchDebounce: ReturnType<typeof setTimeout> | null = null
  watch(historySearchQuery, () => {
    if (historySearchDebounce) clearTimeout(historySearchDebounce)
    historySearchDebounce = setTimeout(() => {
      void loadHistory()
    }, 400)
  })

  /** Coalesce MessageRead (and similar) bursts; refreshes tab badges + list. */
  let realtimeInboxDebounce: ReturnType<typeof setTimeout> | null = null
  function scheduleInboxRefreshFromRealtime(debounceMs = 800): void {
    if (realtimeInboxDebounce != null) {
      clearTimeout(realtimeInboxDebounce)
    }
    realtimeInboxDebounce = window.setTimeout(() => {
      realtimeInboxDebounce = null
      void loadInbox()
    }, debounceMs)
  }

  function setupModeratorRealtimeInbox(): void {
    moderatorUnsub?.()
    moderatorUnsub = null
    const auth = useAuthStore()
    const uid = auth.user?.id
    if (!uid) {
      return
    }
    getEcho()
    moderatorUnsub = subscribeModeratorChannel(uid, {
      onNewMessage: (payload: NewChatMessagePayload) => {
        scheduleInboxRefreshFromRealtime()
        if (payload.sender_type !== 'client') {
          return
        }
        void import('./chatStore').then(({ useChatStore }) => {
          const chatStore = useChatStore()
          const viewing = chatStore.activeChatId ? parseApiChatId(chatStore.activeChatId) : null
          if (viewing === payload.chatId) {
            return
          }
          const preview = chats.value.find((c) => Number(c.id) === payload.chatId)
          if (isMutedUntilActive(preview?.muted_until)) {
            return
          }
          const settings = useSettingsStore()
          if (!settings.sound) {
            return
          }
          const prefs = mergeNotificationSoundPrefs(auth.user?.notification_sound_prefs ?? null)
          if (!prefs.mute) {
            const scenario = resolveNotificationScenario({ isUrgent: false })
            playIncomingToneFromPrefs(prefs, scenario)
          }
          vibrateIncoming(settings.vibration)
        })
      },
    })
  }

  watch(
    () => useAuthStore().user?.id,
    (uid) => {
      if (uid) {
        setupModeratorRealtimeInbox()
      } else {
        moderatorUnsub?.()
        moderatorUnsub = null
      }
    },
    { immediate: true },
  )

  return {
    chats,
    activeTab,
    activeFilters,
    searchQuery,
    bottomNav,
    isLoadingList,
    loadError,
    tabBadges,
    inboxBadge,
    historyChats,
    historySearchQuery,
    isLoadingHistory,
    filteredChats,
    filteredHistoryChats,
    showEmptyState,
    showChatList,
    setActiveTab,
    toggleFilter,
    setSearchQuery,
    setHistorySearchQuery,
    setBottomNav,
    setLoadError,
    retryLoad,
    loadInbox,
    loadHistory,
    scheduleInboxRefreshFromRealtime,
  }
})
