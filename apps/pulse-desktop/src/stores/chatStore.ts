import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import {
  fetchChats,
  fetchTabCounts,
  assignMe as apiAssignMe,
  closeChat as apiCloseChat,
  changeChatDepartment as apiChangeChatDepartment,
  muteChat as apiMuteChat,
} from '../api/chats'
import type { ChatMuteMode } from '../api/chats'
import { api } from '../api/client'
import type { ApiChat, ChatListFilters, ChatResponse, TabCountsData } from '../types/dto/chat.types'
import type { NewChatMessagePayload } from '../lib/realtime'

export const useChatStore = defineStore('chat', () => {
  const chats = ref<ApiChat[]>([])
  const selectedChatId = ref<number | null>(null)
  const activeTab = ref<ChatListFilters['tab']>('my')
  const isLoading = ref(false)
  const filters = ref<Omit<ChatListFilters, 'tab' | 'page' | 'per_page'>>({
    search: '',
    status: 'open',
  })
  const tabCounts = ref<TabCountsData>({ my: 0, unassigned: 0, all: 0 })
  const pagination = ref({
    current_page: 1,
    last_page: 1,
    total: 0,
    next: null as string | null,
    prev: null as string | null,
  })

  let realtimeListDebounce: ReturnType<typeof setTimeout> | null = null
  let tabCountsDebounce: ReturnType<typeof setTimeout> | null = null
  /** Событие может прийти и с chat.*, и с moderator.* — не дублируем bump. */
  let lastBumpDedupeKey: string | null = null

  const selectedChat = computed(() =>
    chats.value.find((c) => c.id === selectedChatId.value) ?? null
  )

  async function refreshTabCounts(): Promise<void> {
    try {
      const data = await fetchTabCounts({
        search: (filters.value.search ?? '').trim() || undefined,
        status: filters.value.status,
      })
      tabCounts.value = data
    } catch (e) {
      console.error('Failed to load tab counts:', e)
    }
  }

  /** Сжать всплески WS (прочитано и т.д.): подтянуть список без спиннера на весь инбокс. */
  function scheduleListRefreshFromRealtime(debounceMs = 800, opts?: { silent?: boolean }): void {
    if (realtimeListDebounce != null) {
      clearTimeout(realtimeListDebounce)
    }
    const silent = opts?.silent ?? false
    realtimeListDebounce = setTimeout(() => {
      realtimeListDebounce = null
      void loadChats(1, { silent })
    }, debounceMs)
  }

  function scheduleTabCountsOnlyDebounce(): void {
    if (tabCountsDebounce != null) {
      clearTimeout(tabCountsDebounce)
    }
    tabCountsDebounce = setTimeout(() => {
      tabCountsDebounce = null
      void refreshTabCounts()
    }, 400)
  }

  /**
   * Как в Telegram: чат поднимается наверх, превью обновляется без полной перезагрузки списка.
   */
  function bumpChatFromRealtime(payload: NewChatMessagePayload): void {
    const dedupeKey = `${payload.chatId}:${payload.messageId}`
    if (lastBumpDedupeKey === dedupeKey) {
      return
    }
    lastBumpDedupeKey = dedupeKey
    window.setTimeout(() => {
      if (lastBumpDedupeKey === dedupeKey) {
        lastBumpDedupeKey = null
      }
    }, 1500)

    const chatId = payload.chatId
    const idx = chats.value.findIndex((c) => c.id === chatId)
    const nowIso = new Date().toISOString()
    const viewing = selectedChatId.value === chatId

    if (idx === -1) {
      scheduleListRefreshFromRealtime(400, { silent: true })
      return
    }

    const row = chats.value[idx]
    const st = payload.sender_type
    const senderType: 'client' | 'moderator' | 'system' =
      st === 'moderator' || st === 'system' ? st : 'client'

    let unreadCount = row.unread_count ?? 0
    if (senderType === 'client' && !viewing) {
      unreadCount += 1
    }
    if (senderType === 'client' && viewing) {
      unreadCount = 0
    }

    const updated: ApiChat = {
      ...row,
      latest_message: {
        id: payload.messageId,
        text: payload.text ?? '',
        sender_type: senderType,
        created_at: nowIso,
      },
      updated_at: nowIso,
      unread_count: unreadCount,
    }

    const next = [...chats.value]
    next.splice(idx, 1)
    next.unshift(updated)
    chats.value = next
    scheduleTabCountsOnlyDebounce()
  }

  async function loadChats(page = 1, options?: { silent?: boolean }) {
    const silent = options?.silent ?? false
    if (!silent) {
      isLoading.value = true
    }
    try {
      const response = await fetchChats({
        tab: activeTab.value,
        page,
        ...filters.value,
      })
      if (page === 1) {
        chats.value = response.data
        await refreshTabCounts()
      } else {
        chats.value = [...chats.value, ...response.data]
      }
      pagination.value = {
        current_page: response.meta.current_page,
        last_page: response.meta.last_page,
        total: response.meta.total,
        next: response.links.next,
        prev: response.links.prev,
      }
    } catch (e) {
      console.error('Failed to load chats:', e)
    } finally {
      if (!silent) {
        isLoading.value = false
      }
    }
  }

  async function setTab(tab: ChatListFilters['tab']) {
    activeTab.value = tab
    await loadChats(1)
  }

  async function setFilters(newFilters: Partial<typeof filters.value>) {
    filters.value = { ...filters.value, ...newFilters }
    await loadChats(1)
  }

  function selectChat(chatId: number | null): void {
    selectedChatId.value = chatId
  }

  async function assignMe(chatId: number) {
    try {
      const updatedChat = await apiAssignMe(chatId)
      const index = chats.value.findIndex((c) => c.id === chatId)
      if (index !== -1) {
        chats.value[index] = updatedChat
      }
    } catch (e) {
      console.error('Failed to assign chat:', e)
      throw e
    }
  }

  /** Refresh assignee after realtime ChatAssigned (takeover). */
  async function applyChatAssigned(chatId: number): Promise<void> {
    const index = chats.value.findIndex((c) => c.id === chatId)
    if (index === -1) {
      scheduleListRefreshFromRealtime(400, { silent: true })
      return
    }
    try {
      const fresh = (await api.get<ChatResponse>(`/chats/${chatId}`)).data
      const next = [...chats.value]
      next[index] = fresh
      chats.value = next
      scheduleTabCountsOnlyDebounce()
    } catch (e) {
      console.error('applyChatAssigned failed:', e)
      scheduleListRefreshFromRealtime(600, { silent: true })
    }
  }

  async function closeChat(chatId: number) {
    try {
      const updatedChat = await apiCloseChat(chatId)
      const index = chats.value.findIndex((c) => c.id === chatId)
      if (index !== -1) {
        chats.value[index] = updatedChat
      }
      if (selectedChatId.value === chatId) {
        selectedChatId.value = null
      }
    } catch (e) {
      console.error('Failed to close chat:', e)
      throw e
    }
  }

  async function loadMore() {
    if (pagination.value.current_page < pagination.value.last_page) {
      await loadChats(pagination.value.current_page + 1, { silent: true })
    }
  }

  async function changeDepartment(chatId: number, departmentId: number): Promise<void> {
    const updated = await apiChangeChatDepartment(chatId, departmentId)
    const index = chats.value.findIndex((c) => c.id === chatId)
    if (index !== -1) {
      chats.value[index] = updated
    }
  }

  async function muteChat(chatId: number, mode: ChatMuteMode): Promise<void> {
    const updated = await apiMuteChat(chatId, mode)
    const index = chats.value.findIndex((c) => c.id === chatId)
    if (index !== -1) {
      chats.value[index] = updated
    }
  }

  /** Тема чата от AI после GenerateChatTopicJob (WS: ChatTopicGenerated). */
  function applyChatTopicFromRealtime(chatId: number, topic: string): void {
    const t = topic.trim()
    if (t === '') {
      return
    }
    const index = chats.value.findIndex((c) => c.id === chatId)
    if (index === -1) {
      scheduleListRefreshFromRealtime(500, { silent: true })
      return
    }
    const row = chats.value[index]
    const next = [...chats.value]
    next[index] = { ...row, topic: t }
    chats.value = next
  }

  return {
    chats,
    selectedChatId,
    selectedChat,
    activeTab,
    isLoading,
    filters,
    tabCounts,
    pagination,
    loadChats,
    refreshTabCounts,
    scheduleListRefreshFromRealtime,
    bumpChatFromRealtime,
    setTab,
    setFilters,
    selectChat,
    assignMe,
    applyChatAssigned,
    closeChat,
    loadMore,
    changeDepartment,
    muteChat,
    applyChatTopicFromRealtime,
  }
})
