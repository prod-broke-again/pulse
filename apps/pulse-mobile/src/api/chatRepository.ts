import { http } from '../lib/http'
import type { ApiChatRow, ApiPaginatedLinks, ApiPaginatedMeta, ApiTabCounts } from './types'

export type ChatTab = 'my' | 'unassigned' | 'all'

export type ChatListFilters = {
  tab?: ChatTab
  source_id?: number
  source_ids?: number[]
  department_id?: number
  department_ids?: number[]
  channels?: Array<'tg' | 'vk' | 'web' | 'max'>
  search?: string
  status?: 'open' | 'closed' | 'all'
  per_page?: number
  page?: number
}

/** Query for GET /chats и /chats/tab-counts (массивы → key[] для Laravel). */
export function serializeChatListQuery(
  f: ChatListFilters,
): Record<string, string | number | (string | number)[] | undefined> {
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

export type ListChatsParams = ChatListFilters

export async function fetchChat(chatId: number): Promise<ApiChatRow> {
  const res = await http.get<{ data: ApiChatRow }>(`/chats/${chatId}`)
  return res.data.data
}

export async function fetchChats(
  params: ListChatsParams,
): Promise<{ data: ApiChatRow[]; meta: ApiPaginatedMeta; links: ApiPaginatedLinks }> {
  const res = await http.get<{ data: ApiChatRow[]; meta: ApiPaginatedMeta; links: ApiPaginatedLinks }>(
    '/chats',
    { params: serializeChatListQuery(params) },
  )
  return res.data
}

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

export async function fetchTabCounts(params: ChatTabCountsParams): Promise<ApiTabCounts> {
  const res = await http.get<{ data: ApiTabCounts }>('/chats/tab-counts', {
    params: serializeChatListQuery(params),
  })
  return res.data.data
}

export async function markChatRead(chatId: number, lastMessageId: number): Promise<void> {
  await http.post(`/chats/${chatId}/read`, { last_message_id: lastMessageId })
}

export async function sendTypingIndicator(chatId: number): Promise<void> {
  await http.post(`/chats/${chatId}/typing`, {})
}

export async function assignMe(chatId: number): Promise<ApiChatRow> {
  const res = await http.post<{ data: ApiChatRow }>(`/chats/${chatId}/assign-me`)
  return res.data.data
}

export async function closeChat(chatId: number): Promise<ApiChatRow> {
  const res = await http.post<{ data: ApiChatRow }>(`/chats/${chatId}/close`)
  return res.data.data
}

export interface DepartmentOption {
  id: number
  name: string
  slug: string
  icon?: string
}

export async function fetchDepartments(sourceId: number): Promise<DepartmentOption[]> {
  const res = await http.get<{ data: DepartmentOption[] }>('/departments', { params: { source_id: sourceId } })
  return res.data.data
}

export async function changeChatDepartment(chatId: number, departmentId: number): Promise<ApiChatRow> {
  const res = await http.patch<{ data: ApiChatRow }>(`/chats/${chatId}/department`, { department_id: departmentId })
  return res.data.data
}

export async function syncChatHistory(chatId: number): Promise<Record<string, unknown>> {
  const res = await http.post<{ data: Record<string, unknown> }>(`/chats/${chatId}/sync-history`)
  return res.data.data
}

export type ChatMuteMode = '1h' | '8h' | 'forever' | 'unmute'

export async function muteChat(chatId: number, mode: ChatMuteMode): Promise<ApiChatRow> {
  const res = await http.post<{ data: ApiChatRow }>(`/chats/${chatId}/mute`, { mode })
  return res.data.data
}
