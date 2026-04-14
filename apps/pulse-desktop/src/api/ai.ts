import { api } from './client'

export async function fetchAiSummary(
  chatId: number,
): Promise<{ summary: string; intent_tag: string | null }> {
  const response = await api.get<{ data: { summary: string; intent_tag: string | null } }>(
    `/chats/${chatId}/ai/summary`,
  )
  return response.data
}

export async function fetchAiSuggestions(
  chatId: number,
): Promise<{ replies: Array<{ id: string; text: string }> }> {
  const response = await api.get<{ data: { replies: Array<{ id: string; text: string }> } }>(
    `/chats/${chatId}/ai/suggestions`,
  )
  return response.data
}
