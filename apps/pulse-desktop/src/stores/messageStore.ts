import { defineStore } from 'pinia'
import { ref } from 'vue'
import { fetchMessages, sendMessage as apiSendMessage } from '../api/messages'
import type { ApiMessage } from '../types/dto/chat.types'
import { isMutedUntilActive } from '../lib/chatMute'
import {
  desktopSoundEnabled,
  effectivePrefs,
  playIncomingToneFromPrefs,
  resolveNotificationScenario,
  shouldDedupeIncomingNotify,
  showDesktopMessageNotification,
} from '../lib/desktopNotifications'
import { markChatRead as apiMarkChatRead } from '../api/chats'
import { processOutboxQueue } from '../lib/outboxProcessor'
import {
  getCachedMessages,
  isElectronLocalStoreAvailable,
  outboxEnqueue,
  setCachedMessages,
} from '../lib/localElectronStore'
import { subscribeChatChannel } from '../lib/realtime'
import type { ChatMessageUpdatedPayload } from '../lib/realtime'
import { mapRealtimePayloadToMessageItem } from '../utils/mappers'
import { useAuthStore } from './authStore'
import { useChatStore } from './chatStore'
import type { OutboxSendPayload } from '../types/outbox'

let pendingIdSeq = 0

function nextPendingMessageId(): number {
  pendingIdSeq -= 1
  return pendingIdSeq
}

function guestAvatarFromMetadata(
  meta: Record<string, unknown> | null | undefined,
): string | undefined {
  if (!meta) {
    return undefined
  }
  const v = meta.avatar_url ?? meta.photo_url ?? meta.avatar
  return typeof v === 'string' && v.trim() ? v.trim() : undefined
}

async function notifyIncomingDesktop(
  chatId: number,
  payload: { sender_type: string; text: string; messageId: number },
): Promise<void> {
  if (payload.sender_type !== 'client') {
    return
  }
  const chat = useChatStore()
  const row = chat.chats.find((c) => c.id === chatId)
  if (isMutedUntilActive(row?.muted_until)) {
    return
  }
  const inThisChat = chat.selectedChatId === chatId
  /** В открытом треде сообщение и так видно — без звука и без системного уведомления. */
  if (inThisChat) {
    return
  }
  if (shouldDedupeIncomingNotify(chatId, payload.messageId)) {
    return
  }
  const auth = useAuthStore()
  const prefs = effectivePrefs(auth.user?.notification_sound_prefs)
  if (!desktopSoundEnabled()) {
    return
  }
  const scenario = resolveNotificationScenario({ isUrgent: row?.is_urgent === true })
  if (!prefs.mute) {
    playIncomingToneFromPrefs(prefs, scenario)
  }
  const meta = (row?.user_metadata ?? {}) as Record<string, unknown>
  const name =
    (typeof meta.first_name === 'string' && meta.first_name) ||
    (typeof meta.username === 'string' && meta.username) ||
    row?.external_user_id ||
    'Чат'
  const icon = guestAvatarFromMetadata(meta)
  await showDesktopMessageNotification({
    title: String(name),
    body: (payload.text ?? '').slice(0, 500) || 'Новое сообщение',
    tag: `pulse-chat-${chatId}`,
    icon: icon ?? null,
  })
}

/** Inbox-wide listener (moderator channel) + same logic as thread listener. */
export async function notifyIncomingForModeratorInbox(payload: {
  chatId: number
  messageId: number
  text: string
  sender_type: string
}): Promise<void> {
  await notifyIncomingDesktop(payload.chatId, {
    sender_type: payload.sender_type,
    text: payload.text,
    messageId: payload.messageId,
  })
}

export const useMessageStore = defineStore('message', () => {
  const messages = ref<ApiMessage[]>([])
  const isLoading = ref(false)
  const isSending = ref(false)
  const loadError = ref<string | null>(null)
  const clientTyping = ref(false)
  /** Активный открытый чат (для подстановки после outbox). */
  let activeThreadChatId: number | null = null

  let unsubscribeRealtime: (() => void) | null = null
  let typingClearTimer: ReturnType<typeof setTimeout> | null = null
  let readNearBottomTimer: ReturnType<typeof setTimeout> | null = null
  let readRequestInFlight = false
  let lastSentReadWatermarkKey: string | null = null

  function maxMessageId(): number {
    let max = 0
    for (const m of messages.value) {
      if (m.id > 0 && m.id > max) {
        max = m.id
      }
    }
    return max
  }

  async function markChatRead(chatId: number): Promise<void> {
    const lastId = maxMessageId()
    if (lastId <= 0) {
      return
    }
    const key = `${chatId}:${lastId}`
    if (readRequestInFlight || lastSentReadWatermarkKey === key) {
      return
    }
    readRequestInFlight = true
    try {
      await apiMarkChatRead(chatId, lastId)
      lastSentReadWatermarkKey = key
    } catch {
      /* ignore */
    } finally {
      readRequestInFlight = false
    }
  }

  function onThreadScrolledNearBottom(chatId: number): void {
    if (readNearBottomTimer != null) {
      clearTimeout(readNearBottomTimer)
    }
    readNearBottomTimer = setTimeout(() => {
      readNearBottomTimer = null
      void markChatRead(chatId)
    }, 400)
  }

  function leaveRealtime(): void {
    unsubscribeRealtime?.()
    unsubscribeRealtime = null
    if (typingClearTimer != null) {
      clearTimeout(typingClearTimer)
      typingClearTimer = null
    }
    if (readNearBottomTimer != null) {
      clearTimeout(readNearBottomTimer)
      readNearBottomTimer = null
    }
    clientTyping.value = false
  }

  function subscribeRealtime(chatId: number): void {
    leaveRealtime()
    const auth = useAuthStore()
    const chat = useChatStore()

    unsubscribeRealtime = subscribeChatChannel(chatId, {
      onNewMessage: (payload) => {
        if (payload.chatId !== chatId) {
          return
        }
        if (
          payload.sender_type === 'moderator' &&
          payload.sender_id != null &&
          auth.user?.id === payload.sender_id
        ) {
          return
        }
        const prevMax = maxMessageId()
        const preview = mapRealtimePayloadToMessageItem(payload)
        void (async () => {
          try {
            const newer = await fetchMessages(chatId, { after_id: prevMax, limit: 30 })
            if (newer.length > 0) {
              const byId = new Map(messages.value.map((m) => [m.id, m]))
              for (const m of newer) {
                byId.set(m.id, m)
              }
              messages.value = Array.from(byId.values()).sort((a, b) => a.id - b.id)
              void persistThreadCache(chatId)
            } else {
              const item = preview
              if (messages.value.some((m) => m.id === item.id)) {
                return
              }
              const apiLike: ApiMessage = {
                id: item.id,
                chat_id: chatId,
                sender_id: payload.sender_id,
                sender_type: item.from,
                text: item.text,
                payload: item.pending_attachments?.length
                  ? { pending_attachments: item.pending_attachments }
                  : {},
                attachments: item.attachments ?? [],
                is_read: item.from !== 'client',
                created_at: new Date().toISOString(),
                updated_at: null,
              }
              messages.value = [...messages.value, apiLike]
              void persistThreadCache(chatId)
            }
          } catch {
            const item = preview
            if (messages.value.some((m) => m.id === item.id)) {
              return
            }
            const apiLike: ApiMessage = {
              id: item.id,
              chat_id: chatId,
              sender_id: payload.sender_id,
              sender_type: item.from,
              text: item.text,
              payload: item.pending_attachments?.length
                ? { pending_attachments: item.pending_attachments }
                : {},
              attachments: item.attachments ?? [],
              is_read: item.from !== 'client',
              created_at: new Date().toISOString(),
              updated_at: null,
            }
            messages.value = [...messages.value, apiLike]
            void persistThreadCache(chatId)
          }
          void notifyIncomingDesktop(chatId, {
            sender_type: payload.sender_type,
            text: payload.text ?? '',
            messageId: payload.messageId,
          })
          chat.bumpChatFromRealtime(payload)
        })()
      },
      onChatMessageUpdated: (p: ChatMessageUpdatedPayload) => {
        if (p.chatId !== chatId) {
          return
        }
        const raw = p.attachments ?? []
        const attachments = raw
          .map((row, i) => {
            if (row === null || typeof row !== 'object') {
              return null
            }
            const o = row as Record<string, unknown>
            const url = typeof o.url === 'string' ? o.url.trim() : ''
            if (!url) {
              return null
            }
            return {
              id: typeof o.id === 'number' ? o.id : -(i + 1),
              name: typeof o.name === 'string' && o.name !== '' ? o.name : 'file',
              mime_type:
                typeof o.mime_type === 'string' && o.mime_type !== ''
                  ? o.mime_type
                  : 'application/octet-stream',
              size: typeof o.size === 'number' ? o.size : 0,
              url,
            }
          })
          .filter((x): x is NonNullable<typeof x> => x !== null)

        const pending = p.pending_attachments ?? []
        messages.value = messages.value.map((m) => {
          if (m.id !== p.messageId) {
            return m
          }
          return {
            ...m,
            attachments,
            payload: {
              ...m.payload,
              pending_attachments: pending,
            },
          }
        })
        void persistThreadCache(chatId)
      },
      onMessageRead: (payload) => {
        if (payload.chatId !== chatId || payload.messageIds.length === 0) {
          return
        }
        const readIds = new Set(payload.messageIds)
        messages.value = messages.value.map((m) =>
          readIds.has(m.id) && m.sender_type === 'client' ? { ...m, is_read: true } : m,
        )
        void persistThreadCache(chatId)
        chat.scheduleListRefreshFromRealtime(600, { silent: true })
      },
      onTyping: (payload) => {
        if (payload.sender_type === 'moderator') {
          clientTyping.value = false
          return
        }
        clientTyping.value = true
        if (typingClearTimer != null) {
          clearTimeout(typingClearTimer)
        }
        typingClearTimer = setTimeout(() => {
          typingClearTimer = null
          clientTyping.value = false
        }, 4000)
      },
      onChatAssigned: (payload) => {
        if (payload.chatId !== chatId) {
          return
        }
        void chat.applyChatAssigned(chatId)
      },
    })
  }

  async function persistThreadCache(chatId: number): Promise<void> {
    if (!isElectronLocalStoreAvailable()) {
      return
    }
    await setCachedMessages(chatId, messages.value)
  }

  async function loadMessages(chatId: number, beforeId?: number): Promise<void> {
    if (!beforeId) {
      activeThreadChatId = chatId
      loadError.value = null
      lastSentReadWatermarkKey = null
      const cached = await getCachedMessages(chatId)
      if (cached?.length) {
        messages.value = cached
      } else {
        messages.value = []
      }
      isLoading.value = true
    }
    try {
      const data = await fetchMessages(chatId, { before_id: beforeId, limit: 50 })
      if (beforeId) {
        messages.value = [...data, ...messages.value]
      } else {
        messages.value = data
        void markChatRead(chatId)
      }
      await persistThreadCache(chatId)
      loadError.value = null
    } catch (e) {
      console.error('Failed to load messages:', e)
      if (!beforeId && messages.value.length > 0) {
        loadError.value = 'Не удалось обновить переписку (показан локальный кэш)'
      } else if (!beforeId) {
        loadError.value = 'Не удалось загрузить переписку'
      }
    } finally {
      if (!beforeId) {
        isLoading.value = false
      }
    }
  }

  function buildOptimisticModeratorMessage(
    chatId: number,
    text: string,
    attachments: string[],
    replyMarkup: { text: string; url: string }[] | undefined,
    clientMessageId: string,
  ): ApiMessage {
    const auth = useAuthStore()
    return {
      id: nextPendingMessageId(),
      chat_id: chatId,
      sender_id: auth.user?.id ?? null,
      sender_type: 'moderator',
      text: text || '\u00a0',
      payload: {},
      attachments: [],
      reply_markup: replyMarkup ?? null,
      is_read: true,
      created_at: new Date().toISOString(),
      updated_at: null,
      client_message_id: clientMessageId,
    }
  }

  async function sendMessage(
    chatId: number,
    text: string,
    attachments: string[] = [],
    replyMarkup?: { text: string; url: string }[],
    replyToMessageId?: number,
  ): Promise<ApiMessage> {
    const clientMessageId = crypto.randomUUID()
    const payload: OutboxSendPayload = {
      chatId,
      text,
      attachments,
      clientMessageId,
      replyMarkup,
      replyToMessageId,
    }

    const optimistic = buildOptimisticModeratorMessage(chatId, text, attachments, replyMarkup, clientMessageId)

    const offline = typeof navigator !== 'undefined' && !navigator.onLine

    if (offline) {
      if (!isElectronLocalStoreAvailable()) {
        throw new Error('Нет сети. Отправка недоступна.')
      }
      isSending.value = true
      try {
        await outboxEnqueue(clientMessageId, chatId, payload)
        messages.value = [...messages.value, optimistic]
        await persistThreadCache(chatId)
        return optimistic
      } finally {
        isSending.value = false
      }
    }

    isSending.value = true
    try {
      const newMessage = await apiSendMessage(
        chatId,
        text,
        attachments,
        clientMessageId,
        replyMarkup,
        replyToMessageId,
      )
      messages.value = [...messages.value, newMessage]
      await persistThreadCache(chatId)
      void markChatRead(chatId)
      void flushOutboxQueue()
      return newMessage
    } catch (e) {
      console.error('Failed to send message:', e)
      if (!isElectronLocalStoreAvailable()) {
        throw e
      }
      await outboxEnqueue(clientMessageId, chatId, payload)
      messages.value = [...messages.value, optimistic]
      await persistThreadCache(chatId)
      return optimistic
    } finally {
      isSending.value = false
    }
  }

  async function flushOutboxQueue(): Promise<void> {
    if (!isElectronLocalStoreAvailable() || !navigator.onLine) {
      return
    }
    await processOutboxQueue({
      onSuccess: (chatId, clientMessageId, message) => {
        if (chatId === activeThreadChatId) {
          messages.value = messages.value.map((m) =>
            m.client_message_id === clientMessageId ? message : m,
          )
          void persistThreadCache(chatId)
        }
        if (chatId === activeThreadChatId) {
          void markChatRead(chatId)
        }
      },
    })
  }

  function clearMessages(): void {
    messages.value = []
    loadError.value = null
    lastSentReadWatermarkKey = null
    activeThreadChatId = null
    leaveRealtime()
  }

  async function retryLoad(chatId: number): Promise<void> {
    await loadMessages(chatId)
    if (!loadError.value) {
      subscribeRealtime(chatId)
    }
  }

  function mergeContextMessages(chatId: number, rows: ApiMessage[]): void {
    const byId = new Map(messages.value.map((m) => [m.id, m]))
    for (const m of rows) {
      byId.set(m.id, m)
    }
    messages.value = Array.from(byId.values()).sort((a, b) => a.id - b.id)
    void persistThreadCache(chatId)
  }

  return {
    messages,
    isLoading,
    isSending,
    loadError,
    clientTyping,
    loadMessages,
    sendMessage,
    clearMessages,
    retryLoad,
    subscribeRealtime,
    leaveRealtime,
    onThreadScrolledNearBottom,
    flushOutboxQueue,
    mergeContextMessages,
  }
})
