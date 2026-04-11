import { http } from '../lib/http'
import type { SendMessagePayload } from '../types/chat'
import type { ApiMessageRow } from './types'

export async function fetchMessages(
  chatId: number,
  opts: { beforeId?: number; limit?: number } = {},
): Promise<ApiMessageRow[]> {
  const params: Record<string, number> = { limit: opts.limit ?? 50 }
  if (opts.beforeId != null) params.before_id = opts.beforeId
  const res = await http.get<{ data: ApiMessageRow[] }>(`/chats/${chatId}/messages`, { params })
  return res.data.data
}

export async function sendMessage(
  chatId: number,
  body: SendMessagePayload,
): Promise<ApiMessageRow> {
  const res = await http.post<{ data: ApiMessageRow & { client_message_id?: string } }>(
    `/chats/${chatId}/send`,
    body,
  )
  return res.data.data
}
