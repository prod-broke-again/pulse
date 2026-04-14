export type ChannelSource = 'tg' | 'vk' | 'web'

export type InboxTab = 'my' | 'unassigned' | 'all'

export type FilterId = 'open' | 'closed' | 'tg' | 'vk' | 'web'

export type BottomNavId = 'inbox' | 'history' | 'settings'

export type TicketStatus = 'open' | 'closed'

export interface ChatPreviewItem {
  id: string
  initials: string
  name: string
  timeLabel: string
  preview: string
  department: string
  channel: ChannelSource
  unread: boolean
  unreadCount?: number
  hasAiTag: boolean
  status: TicketStatus
  muted_until?: string | null
}

export interface MessageAttachment {
  fileName: string
  sizeLabel: string
}

/** Full attachment row from API (images, audio, files). */
export interface MessageMediaItem {
  id?: number
  url: string
  name: string
  mime_type: string
  size?: number
  sizeLabel?: string
}

export type MessageKind = 'incoming' | 'outgoing' | 'system'

export interface ReplyMarkupButton {
  text: string
  url: string
}

export interface ChatMessage {
  id: string
  kind: MessageKind
  text: string
  time?: string
  /** ISO8601 для копирования в стиле Telegram */
  createdAtIso?: string
  /** @deprecated Prefer mediaAttachments; kept for older cached rows */
  attachment?: MessageAttachment
  /** When API returns attachments (images, audio, documents). */
  mediaAttachments?: MessageMediaItem[]
  /** Quoted message (API + realtime). */
  reply_to?: { id: number | null; text: string; sender_type: string }
  /** Inline URL buttons (moderator presets), shown under bubble. */
  reply_markup?: ReplyMarkupButton[]
  /** Client messages marked read by staff (API + MessageRead broadcast). */
  isRead?: boolean
  /** Optimistic send correlation */
  clientMessageId?: string
}

/** Body for POST /chats/{id}/send */
export interface SendMessagePayload {
  text?: string
  attachments?: string[]
  client_message_id?: string
  reply_to_message_id?: number
  reply_markup?: ReplyMarkupButton[]
}

export interface ChatThreadMeta {
  id: string
  userName: string
  status: TicketStatus
  channel: ChannelSource
  channelLabel: string
  departmentLabel: string
  /** Источник чата — для списка отделов (GET /departments). */
  sourceId: number | null
  /** Текущий отдел (id). */
  departmentId: number | null
  aiSummaryBar: string
  /** Pulse user id of assignee; null if unassigned. */
  assignedToUserId: number | null
  /** Server mute for notifications. */
  muted_until?: string | null
}

export interface AiReplyOption {
  id: string
  text: string
}

export interface AiPanelContent {
  summary: string
  intentTag: string
  replies: AiReplyOption[]
  actionTitle: string
  actionDesc: string
  actionButtonLabel: string
}
