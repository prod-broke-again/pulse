import { PaginatedResponse } from './api.types'

export interface ApiChat {
  id: number
  source_id: number
  department_id: number
  external_user_id: string
  user_metadata: Record<string, unknown> | null
  status: 'new' | 'active' | 'closed'
  assigned_to: number | null
  /** Краткая тема от AI (GenerateChatTopicJob), не путать с отделом. */
  topic?: string | null
  source?: { id: number; name: string; type: string }
  department?: { id: number; name: string; category?: string; ai_enabled?: boolean; icon?: string | null }
  category_code?: string | null
  category_label?: string | null
  ai_enabled?: boolean | null
  ai_badge?: boolean | null
  ai_suggested_department_id?: number | null
  ai_department_confidence?: number | null
  ai_department_assigned_at?: string | null
  assignee?: { id: number; name: string; avatar_url?: string | null } | null
  /** Непрочитанные сообщения клиента для текущего модератора */
  unread_count?: number
  latest_message?: {
    id: number
    text: string
    sender_type: 'client' | 'moderator' | 'system'
    created_at: string | null
  } | null
  is_urgent: boolean
  /** Mute notifications for this chat until (ISO8601). Server-driven. */
  muted_until?: string | null
  created_at: string | null
  updated_at: string | null
}

export interface ApiMessage {
  id: number
  chat_id: number
  sender_id: number | null
  sender_type: 'client' | 'moderator' | 'system'
  text: string
  payload: Record<string, unknown> & { delivery_channel?: string }
  reply_markup?: Array<{ text: string; url: string }> | null
  reply_to?: { id: number | null; text: string; sender_type: string } | null
  attachments: ApiAttachment[]
  is_read: boolean
  /** Локально: идемпотентность и сопоставление с outbox (Electron). */
  client_message_id?: string
  created_at: string | null
  updated_at: string | null
}

export interface ApiAttachment {
  id: number
  name: string
  mime_type: string
  size: number
  url: string
}

export interface ChatListFilters {
  tab?: 'my' | 'unassigned' | 'all'
  source_id?: number
  department_id?: number
  search?: string
  status?: 'open' | 'closed' | 'all'
  per_page?: number
  page?: number
}

export interface TabCountsData {
  my: number
  unassigned: number
  all: number
}

export interface MessageListParams {
  before_id?: number
  after_id?: number
  limit?: number
  per_page?: number
}

export type ChatsListResponse = PaginatedResponse<ApiChat>

export interface MessagesListResponse {
  data: ApiMessage[]
}

export interface ChatResponse {
  data: ApiChat
}

export interface MessageResponse {
  data: ApiMessage
}
