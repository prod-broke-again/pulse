import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

let echo: Echo<'pusher'> | null = null

export type NewChatMessagePayload = {
  chatId: number
  messageId: number
  text: string
  sender_type: string
  sender_id: number | null
}

export type MessageReadPayload = {
  chatId: number
  messageIds: number[]
}

export type TypingPayload = {
  sender_name?: string | null
  sender_type?: string
}

export type ChatChannelHandlers = {
  onNewMessage?: (payload: NewChatMessagePayload) => void
  onMessageRead?: (payload: MessageReadPayload) => void
  onTyping?: (payload: TypingPayload) => void
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
  ch.listen('.App\\Events\\MessageRead', (e: MessageReadPayload) => handlers.onMessageRead?.(e))
  ch.listen('typing', (e: TypingPayload) => handlers.onTyping?.(e))

  return () => {
    client.leave(`chat.${chatId}`)
  }
}

export function disconnectEcho(): void {
  echo?.disconnect()
  echo = null
}
