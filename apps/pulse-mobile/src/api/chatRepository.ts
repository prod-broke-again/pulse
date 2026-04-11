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
