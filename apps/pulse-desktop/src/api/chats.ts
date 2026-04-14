import { api } from './client'
import type {
  ApiChat,
  ChatListFilters,
  ChatsListResponse,
  ChatResponse,
  TabCountsData,
} from '../types/dto/chat.types'

export async function fetchChats(filters: ChatListFilters = {}): Promise<ChatsListResponse> {
  return api.get<ChatsListResponse>('/chats', filters as Record<string, string | number | undefined>)
}

export async function fetchTabCounts(
  params: Pick<ChatListFilters, 'search' | 'status'> = {},
): Promise<TabCountsData> {
  const response = await api.get<{ data: TabCountsData }>('/chats/tab-counts', {
    search: params.search,
    status: params.status,
  })
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
