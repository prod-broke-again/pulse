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
}

export interface MessageAttachment {
  fileName: string
  sizeLabel: string
}

export type MessageKind = 'incoming' | 'outgoing' | 'system'

export interface ChatMessage {
  id: string
  kind: MessageKind
  text: string
  time?: string
  attachment?: MessageAttachment
  /** Client messages marked read by staff (API + MessageRead broadcast). */
  isRead?: boolean
  /** Optimistic send correlation */
  clientMessageId?: string
}

export interface ChatThreadMeta {
  id: string
  userName: string
  status: TicketStatus
  channel: ChannelSource
  channelLabel: string
  departmentLabel: string
  aiSummaryBar: string
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
