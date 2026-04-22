import { api } from './client'
import type {
  ApiChat,
  ChatListFilters,
  ChatsListResponse,
  ChatResponse,
  TabCountsData,
} from '../types/dto/chat.types'

export type ChatTabCountsParams = Pick<
  ChatListFilters,
  | 'search'
  | 'status'
  | 'source_id'
  | 'source_ids'
  | 'department_id'
  | 'department_ids'
  | 'channels'
>

/** Query string for GET /chats и tab-counts (массивы → ключ[] для Laravel). */
export function serializeChatListQuery(f: ChatListFilters): Record<
  string,
  string | number | (string | number)[] | undefined
> {
  const out: Record<string, string | number | (string | number)[] | undefined> = {}
  if (f.page != null) {
    out.page = f.page
  }
  if (f.per_page != null) {
    out.per_page = f.per_page
  }
  if (f.tab != null) {
    out.tab = f.tab
  }
  const s = f.search?.trim()
  if (s) {
    out.search = s
  }
  if (f.status != null) {
    out.status = f.status
  }
  if (f.source_id != null) {
    out.source_id = f.source_id
  }
  if (f.source_ids != null && f.source_ids.length > 0) {
    out.source_ids = f.source_ids
  }
  if (f.department_id != null) {
    out.department_id = f.department_id
  }
  if (f.department_ids != null && f.department_ids.length > 0) {
    out.department_ids = f.department_ids
  }
  if (f.channels != null && f.channels.length > 0) {
    out.channels = f.channels
  }
  return out
}

export async function fetchChats(filters: ChatListFilters = {}): Promise<ChatsListResponse> {
  return api.get<ChatsListResponse>('/chats', serializeChatListQuery(filters))
}

export async function fetchTabCounts(params: ChatTabCountsParams = {}): Promise<TabCountsData> {
  const response = await api.get<{ data: TabCountsData }>('/chats/tab-counts', serializeChatListQuery(params))
  return response.data
}

export async function markChatRead(chatId: number, lastMessageId: number): Promise<void> {
  await api.post(`/chats/${chatId}/read`, { last_message_id: lastMessageId })
}

export async function sendTypingIndicator(chatId: number): Promise<void> {
  await api.post(`/chats/${chatId}/typing`, {})
}

export async function assignMe(chatId: number): Promise<ApiChat> {
  const response = await api.post<ChatResponse>(`/chats/${chatId}/assign-me`)
  return response.data
}

export async function closeChat(chatId: number): Promise<ApiChat> {
  const response = await api.post<ChatResponse>(`/chats/${chatId}/close`)
  return response.data
}

export async function changeChatDepartment(chatId: number, departmentId: number): Promise<ApiChat> {
  const response = await api.patch<ChatResponse>(`/chats/${chatId}/department`, {
    department_id: departmentId,
  })
  return response.data
}

export async function syncChatHistory(chatId: number): Promise<Record<string, unknown>> {
  const response = await api.post<{ data: Record<string, unknown> }>(`/chats/${chatId}/sync-history`)
  return response.data
}

export type ChatMuteMode = '1h' | '8h' | 'forever' | 'unmute'

export async function muteChat(chatId: number, mode: ChatMuteMode): Promise<ApiChat> {
  const response = await api.post<ChatResponse>(`/chats/${chatId}/mute`, { mode })
  return response.data
}
