import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { getToken } from '../api/client'

let echo: Echo<'pusher'> | null = null

/** Pending attachment slots while DownloadInboundAttachmentJob runs (see payload.pending_attachments). */
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
  reply_to?: { id: number | null; text: string; sender_type: string } | null
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
  chat_id?: number
  sender_name?: string | null
  sender_type?: string
}

export type ChatAssignedPayload = {
  chatId: number
  assignedToUserId: number
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
  /** Same event as chat channel, for inbox refresh when message targets assigned moderator. */
  onNewMessage?: (payload: NewChatMessagePayload) => void
  onChatMessageUpdated?: (payload: ChatMessageUpdatedPayload) => void
}

function pulseOrigin(): string {
  const base = import.meta.env.VITE_API_BASE_URL || 'https://pulse.test/api/v1'
  try {
    return new URL(base).origin
  } catch {
    return 'https://pulse.test'
  }
}

export function getEcho(): Echo<'pusher'> | null {
  if (echo) {
    return echo
  }

  const key = import.meta.env.VITE_REVERB_APP_KEY
  const host = import.meta.env.VITE_REVERB_HOST
  const port = Number(import.meta.env.VITE_REVERB_PORT ?? 443)
  const scheme = import.meta.env.VITE_REVERB_SCHEME ?? 'https'

  if (!key || !host) {
    return null
  }

  echo = new Echo({
    broadcaster: 'pusher',
    client: new Pusher(key, {
      wsHost: host,
      wsPort: port,
      wssPort: port,
      forceTLS: scheme === 'https',
      enabledTransports: ['ws', 'wss'],
      cluster: 'mt1',
      authEndpoint: `${pulseOrigin()}/broadcasting/auth`,
      auth: {
        headers: {
          Authorization: `Bearer ${getToken() ?? ''}`,
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

/** Subscribe to private moderator channel (same NewChatMessage when chat is assigned to you). */
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

export function disconnectEcho(): void {
  echo?.disconnect()
  echo = null
}
