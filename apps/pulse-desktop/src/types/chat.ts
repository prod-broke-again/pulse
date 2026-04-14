export type ConversationChannel = 'telegram' | 'vk' | 'web' | 'tg'

export interface Conversation {
  id: number
  initials: string
  name: string
  channel: ConversationChannel
  department: string
  message: string
  time: string
  unread?: boolean
  active?: boolean
  sourceId?: number
  assignedTo?: number | null
  assignee?: { id: number; name: string; avatar_url?: string | null } | null
  /** Аватар гостя из user_metadata.avatar_url */
  clientAvatarUrl?: string | null
  status?: string
  isUrgent?: boolean
  /** Server mute for notifications (ISO8601). */
  muted_until?: string | null
}

export interface MessageItem {
  id: number
  from: 'client' | 'moderator' | 'system'
  text: string
  time: string
  /** Сообщение в очереди отправки (офлайн / сеть недоступна). */
  pending?: boolean
  reply_markup?: Array<{ text: string; url: string }>
  /** Для исходящих сообщений модератора: прочитано клиентом */
  is_read?: boolean
  attachments?: Array<{
    id: number
    name: string
    mime_type: string
    size: number
    url: string
  }>
  reply_to?: { id: number | null; text: string; sender_type: string }
}
