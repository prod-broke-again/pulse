/** API v1 chat list row (subset; backend may add fields). */
export interface ApiChatRow {
  id: number
  external_user_id?: string
  source_id?: number | null
  department_id?: number | null
  assigned_to?: number | null
  assignee?: { id: number; name: string } | null
  status: string
  category_code?: string | null
  category_label?: string | null
  ai_enabled?: boolean | null
  ai_badge?: boolean | null
  channel?: string | null
  channel_label?: string | null
  unread_count?: number
  last_message_preview?: string | null
  last_message_at?: string | null
  user_metadata?: { name?: string } | null
  source?: { type?: string } | null
  department?: { id?: number; name?: string; category?: string } | null
}

export interface ApiPaginatedMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface ApiPaginatedLinks {
  first: string | null
  last: string | null
  prev: string | null
  next: string | null
}

export interface ApiMessageRow {
  id: number
  chat_id: number
  sender_id: number | null
  sender_type: string
  text: string
  payload?: Record<string, unknown>
  reply_markup?: Array<{ text: string; url: string }> | null
  attachments?: Array<{
    id?: number
    name?: string
    mime_type?: string
    size?: number
    url?: string
  }>
  is_read?: boolean
  reply_to?: { id: number; text: string; sender_type: string } | null
  created_at?: string
  updated_at?: string
  client_message_id?: string
}

export interface ApiTabCounts {
  my: number
  unassigned: number
  all: number
}

export interface ApiCannedResponse {
  id: number
  source_id?: number | null
  code?: string | null
  title: string
  text: string
  is_active?: boolean
}

export interface ApiQuickLink {
  id: number
  source_id?: number | null
  title: string
  url: string
  is_active?: boolean
  sort_order?: number
}
