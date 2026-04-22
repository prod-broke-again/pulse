import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'
import * as chatApi from '../api/chatRepository'
import { isMutedUntilActive } from '../lib/chatMute'
import { parseApiChatId } from '../lib/chatIds'
import { mergeNotificationSoundPrefs } from '../lib/notificationSoundPresets'
import { pendingListFiltersFromPrefs } from '../lib/inboxFilterPrefs'
import {
  playIncomingToneFromPrefs,
  resolveNotificationScenario,
  vibrateIncoming,
} from '../lib/notificationFeedback'
import { getEcho, subscribeModeratorChannel, subscribeSourceInbox } from '../lib/realtime'
import type { NewChatMessagePayload } from '../lib/realtime'
import { mapApiChatToPreview } from '../mappers/chatMapper'
import { useAuthStore } from './authStore'
import { useSettingsStore } from './settingsStore'
import type { BottomNavId, ChatPreviewItem, FilterId, InboxTab } from '../types/chat'

let moderatorUnsub: (() => void) | null = null
let sourceInboxUnsubs: Array<() => void> = []

let lastInboxRealtimeDedupeKey: string | null = null

/** After login / first /auth/me, apply inbox_filter_prefs once per user id. */
let lastSyncedInboxUserId: number | null = null

function deriveApiStatus(filters: Set<FilterId>): 'open' | 'closed' | 'all' {
  const hasOpen = filters.has('open')
  const hasClosed = filters.has('closed')
  if (hasOpen && hasClosed) return 'all'
  if (hasOpen) return 'open'
  if (hasClosed) return 'closed'
  return 'all'
}

function deriveChannelFilters(filters: Set<FilterId>): Array<'tg' | 'vk' | 'web' | 'max'> | undefined {
  const ch = (['tg', 'vk', 'web', 'max'] as const).filter((c) => filters.has(c))
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

  /** `undefined` = все источники; иначе пересечение с доступными на бэкенде. */
  const filterSourceIds = ref<number[] | undefined>(undefined)
  /** `undefined` = все отделы. */
  const filterDepartmentIds = ref<number[] | undefined>(undefined)

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

  function commonListParams(): Pick<
    chatApi.ChatListFilters,
    'search' | 'status' | 'source_id' | 'source_ids' | 'department_id' | 'department_ids' | 'channels'
  > {
    const chans = deriveChannelFilters(activeFilters.value)
    return {
      search: searchQuery.value.trim() || undefined,
      status: deriveApiStatus(activeFilters.value),
      source_ids: filterSourceIds.value,
      department_ids: filterDepartmentIds.value,
      channels: chans,
    }
  }

  function syncInboxFiltersFromUserProfile(): void {
    const auth = useAuthStore()
    const u = auth.user
    if (!u) {
      lastSyncedInboxUserId = null
      return
    }
    if (lastSyncedInboxUserId === u.id) {
      return
    }
    lastSyncedInboxUserId = u.id
    const slice = pendingListFiltersFromPrefs(u.inbox_filter_prefs ?? null)
    filterSourceIds.value = slice.source_ids
    filterDepartmentIds.value = slice.department_ids
    if (slice.channels != null && slice.channels.length > 0) {
      const next = new Set<FilterId>(['open'])
      for (const c of slice.channels) {
        if (c === 'tg' || c === 'vk' || c === 'web' || c === 'max') {
          next.add(c)
        }
      }
      activeFilters.value = next
    }
  }

  function setFilterSourceIds(ids: number[] | undefined): void {
    filterSourceIds.value = ids
  }

  function setFilterDepartmentIds(ids: number[] | undefined): void {
    filterDepartmentIds.value = ids
  }

  function onAuthUserChanged(): void {
    const u = useAuthStore().user
    if (!u) {
      lastSyncedInboxUserId = null
      return
    }
    if (lastSyncedInboxUserId !== u.id) {
      lastSyncedInboxUserId = null
    }
    syncInboxFiltersFromUserProfile()
  }

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
      const base = commonListParams()
      const [listRes, counts] = await Promise.all([
        chatApi.fetchChats({
          tab: activeTab.value,
          ...base,
          page: 1,
          per_page: 50,
        }),
        chatApi.fetchTabCounts({
          ...base,
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
      const base = commonListParams()
      const listRes = await chatApi.fetchChats({
        tab: 'all',
        status: 'closed',
        search: historySearchQuery.value.trim() || undefined,
        source_ids: base.source_ids,
        department_ids: base.department_ids,
        channels: base.channels,
        page: 1,
        per_page: 100,
      })
      historyChats.value = listRes.data.map(mapApiChatToPreview)
    } finally {
      isLoadingHistory.value = false
    }
  }

  watch([activeTab, activeFilters, filterSourceIds, filterDepartmentIds], () => {
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

  watch(
    () => useAuthStore().user?.id,
    () => {
      onAuthUserChanged()
    },
    { immediate: true },
  )

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

  function onModeratorOrSourceInboxMessage(payload: NewChatMessagePayload): void {
    const dedupeKey = `${payload.chatId}:${payload.messageId}`
    if (lastInboxRealtimeDedupeKey === dedupeKey) {
      return
    }
    lastInboxRealtimeDedupeKey = dedupeKey
    window.setTimeout(() => {
      if (lastInboxRealtimeDedupeKey === dedupeKey) {
        lastInboxRealtimeDedupeKey = null
      }
    }, 1500)

    scheduleInboxRefreshFromRealtime()
    if (payload.sender_type !== 'client') {
      return
    }
    const auth = useAuthStore()
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
        const urgent = payload.is_new_chat === true
        const scenario = resolveNotificationScenario({ isUrgent: urgent })
        playIncomingToneFromPrefs(prefs, scenario)
      }
      vibrateIncoming(settings.vibration)
    })
  }

  function teardownSourceInboxRealtime(): void {
    for (const u of sourceInboxUnsubs) {
      try {
        u()
      } catch {
        // noop
      }
    }
    sourceInboxUnsubs = []
  }

  function setupSourceInboxRealtime(): void {
    teardownSourceInboxRealtime()
    const auth = useAuthStore()
    const ids = auth.user?.source_ids ?? []
    if (!auth.user?.id || ids.length === 0) {
      return
    }
    getEcho()
    for (const sid of ids) {
      sourceInboxUnsubs.push(
        subscribeSourceInbox(sid, {
          onNewMessage: onModeratorOrSourceInboxMessage,
          onChatTopicGenerated: () => {
            scheduleInboxRefreshFromRealtime()
          },
        }),
      )
    }
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
      onNewMessage: onModeratorOrSourceInboxMessage,
    })
  }

  watch(
    () => {
      const u = useAuthStore().user
      const sig = (u?.source_ids ?? []).slice().sort().join(',')
      return [u?.id, sig] as const
    },
    ([uid]) => {
      if (uid) {
        setupModeratorRealtimeInbox()
        setupSourceInboxRealtime()
      } else {
        moderatorUnsub?.()
        moderatorUnsub = null
        teardownSourceInboxRealtime()
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
    filterSourceIds,
    filterDepartmentIds,
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
    syncInboxFiltersFromUserProfile,
    setFilterSourceIds,
    setFilterDepartmentIds,
  }
})
