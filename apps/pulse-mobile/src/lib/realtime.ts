import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

let echo: Echo<'pusher'> | null = null

export type PendingAttachmentMeta = {
  type: string
  source_url?: string
  kind?: string
}

export type NewChatMessagePayload = {
  chatId: number
  messageId: number
  text: string
  sender_type: string
  sender_id: number | null
  attachments?: Array<Record<string, unknown>>
  pending_attachments?: PendingAttachmentMeta[]
  reply_to?: { id: number; text: string; sender_type: string } | null
  source_id?: number | null
  is_new_chat?: boolean
  /** e.g. telegram_app when business owner wrote from Telegram app */
  delivery_channel?: string | null
}

export type ChatMessageUpdatedPayload = {
  chatId: number
  messageId: number
  attachments?: Array<Record<string, unknown>>
  pending_attachments?: PendingAttachmentMeta[]
}

export type MessageReadPayload = {
  chatId: number
  messageIds: number[]
}

export type TypingPayload = {
  sender_name?: string | null
  sender_type?: string
}

export type ChatAssignedPayload = {
  chatId: number
  assignedToUserId: number
}

export type ChatTopicGeneratedPayload = {
  chatId: number
  topic: string
  source_id?: number | null
}

function normalizeChatTopicPayload(raw: unknown): ChatTopicGeneratedPayload | null {
  if (raw === null || typeof raw !== 'object') {
    return null
  }
  const o = raw as Record<string, unknown>
  const chatId =
    typeof o.chatId === 'number'
      ? o.chatId
      : typeof o.chat_id === 'number'
        ? o.chat_id
        : null
  const topic = typeof o.topic === 'string' ? o.topic : null
  if (chatId === null || topic === null) {
    return null
  }
  return { chatId, topic }
}

function normalizeChatAssignedPayload(raw: unknown): ChatAssignedPayload | null {
  if (raw === null || typeof raw !== 'object') {
    return null
  }
  const o = raw as Record<string, unknown>
  const chatId =
    typeof o.chatId === 'number'
      ? o.chatId
      : typeof o.chat_id === 'number'
        ? o.chat_id
        : null
  const assignedToUserId =
    typeof o.assignedToUserId === 'number'
      ? o.assignedToUserId
      : typeof o.assigned_to_user_id === 'number'
        ? o.assigned_to_user_id
        : null
  if (chatId === null || assignedToUserId === null) {
    return null
  }
  return { chatId, assignedToUserId }
}

export type ChatChannelHandlers = {
  onNewMessage?: (payload: NewChatMessagePayload) => void
  onChatMessageUpdated?: (payload: ChatMessageUpdatedPayload) => void
  onMessageRead?: (payload: MessageReadPayload) => void
  onTyping?: (payload: TypingPayload) => void
  onChatAssigned?: (payload: ChatAssignedPayload) => void
}

export type ModeratorChannelHandlers = {
  onNewMessage?: (payload: NewChatMessagePayload) => void
  onChatMessageUpdated?: (payload: ChatMessageUpdatedPayload) => void
}

export type SourceInboxHandlers = {
  onNewMessage?: (payload: NewChatMessagePayload) => void
  onChatTopicGenerated?: (payload: ChatTopicGeneratedPayload) => void
}

export function getEcho(): Echo<'pusher'> | null {
  if (echo) return echo

  const key = import.meta.env.VITE_REVERB_APP_KEY
  const host = import.meta.env.VITE_REVERB_HOST
  const port = Number(import.meta.env.VITE_REVERB_PORT ?? 443)
  const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'https'

  if (!key || !host) return null

  echo = new Echo({
    broadcaster: 'pusher',
    client: new Pusher(key, {
      wsHost: host,
      wsPort: port,
      wssPort: port,
      forceTLS: scheme === 'https',
      enabledTransports: ['ws', 'wss'],
      cluster: 'mt1',
      authEndpoint: `${import.meta.env.VITE_API_ORIGIN ?? 'https://pulse.test'}/broadcasting/auth`,
      auth: {
        headers: {
          Authorization: `Bearer ${localStorage.getItem('api-token') ?? ''}`,
          Accept: 'application/json',
        },
      },
    }),
  })

  return echo
}

/** Subscribe to private chat channel (moderator). Returns unsubscribe (leave channel). */
export function subscribeChatChannel(chatId: number, handlers: ChatChannelHandlers): () => void {
  const client = getEcho()
  if (!client) {
    return () => {}
  }

  const ch = client.private(`chat.${chatId}`)
  ch.listen('.App\\Events\\NewChatMessage', (e: NewChatMessagePayload) => handlers.onNewMessage?.(e))
  ch.listen('.App\\Events\\ChatMessageUpdated', (e: ChatMessageUpdatedPayload) =>
    handlers.onChatMessageUpdated?.(e),
  )
  ch.listen('.App\\Events\\MessageRead', (e: MessageReadPayload) => handlers.onMessageRead?.(e))
  ch.listen('typing', (e: TypingPayload) => handlers.onTyping?.(e))
  ch.listen('.App\\Events\\ChatAssigned', (e: unknown) => {
    const p = normalizeChatAssignedPayload(e)
    if (p) {
      handlers.onChatAssigned?.(p)
    }
  })
  ch.listen('ChatAssigned', (e: unknown) => {
    const p = normalizeChatAssignedPayload(e)
    if (p) {
      handlers.onChatAssigned?.(p)
    }
  })

  return () => {
    client.leave(`chat.${chatId}`)
  }
}

/** Subscribe to private moderator channel (inbox refresh when assigned chats get messages). */
export function subscribeModeratorChannel(
  userId: number,
  handlers: ModeratorChannelHandlers,
): () => void {
  const client = getEcho()
  if (!client) {
    return () => {}
  }

  const ch = client.private(`moderator.${userId}`)
  ch.listen('.App\\Events\\NewChatMessage', (e: NewChatMessagePayload) => handlers.onNewMessage?.(e))
  ch.listen('.App\\Events\\ChatMessageUpdated', (e: ChatMessageUpdatedPayload) =>
    handlers.onChatMessageUpdated?.(e),
  )

  return () => {
    client.leave(`moderator.${userId}`)
  }
}

export function subscribeSourceInbox(sourceId: number, handlers: SourceInboxHandlers): () => void {
  const client = getEcho()
  if (!client) {
    return () => {}
  }

  const ch = client.private(`source-inbox.${sourceId}`)
  ch.listen('.App\\Events\\NewChatMessage', (e: NewChatMessagePayload) => handlers.onNewMessage?.(e))
  if (handlers.onChatTopicGenerated) {
    ch.listen('.App\\Events\\ChatTopicGenerated', (e: unknown) => {
      const p = normalizeChatTopicPayload(e)
      if (p) {
        handlers.onChatTopicGenerated?.(p)
      }
    })
  }

  return () => {
    try {
      client.leave(`source-inbox.${sourceId}`)
    } catch {
      // noop
    }
  }
}

export function disconnectEcho(): void {
  echo?.disconnect()
  echo = null
}
