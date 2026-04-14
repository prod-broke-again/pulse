import { http } from '../lib/http'
import type { ApiChatRow, ApiPaginatedLinks, ApiPaginatedMeta, ApiTabCounts } from './types'

export interface ListChatsParams {
  tab: string
  status?: 'open' | 'closed' | 'all'
  search?: string
  channels?: string[]
  page?: number
  per_page?: number
}

export async function fetchChat(chatId: number): Promise<ApiChatRow> {
  const res = await http.get<{ data: ApiChatRow }>(`/chats/${chatId}`)
  return res.data.data
}

export async function fetchChats(params: ListChatsParams): Promise<{
  data: ApiChatRow[]
  meta: ApiPaginatedMeta
  links: ApiPaginatedLinks
}> {
  const res = await http.get<{ data: ApiChatRow[]; meta: ApiPaginatedMeta; links: ApiPaginatedLinks }>(
    '/chats',
    { params },
  )
  return res.data
}

export async function fetchTabCounts(
  params: Omit<ListChatsParams, 'tab' | 'page' | 'per_page'>,
): Promise<ApiTabCounts> {
  const res = await http.get<{ data: ApiTabCounts }>('/chats/tab-counts', { params })
  return res.data.data
}

export async function markChatRead(chatId: number, lastMessageId: number): Promise<void> {
  await http.post(`/chats/${chatId}/read`, { last_message_id: lastMessageId })
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
