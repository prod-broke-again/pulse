import { http } from '../lib/http'

export async function fetchAiSummary(chatId: number): Promise<{ summary: string; intent_tag: string | null }> {
  const res = await http.get<{ data: { summary: string; intent_tag: string | null } }>(
    `/chats/${chatId}/ai/summary`,
  )
  return res.data.data
}

export async function fetchAiSuggestions(
  chatId: number,
): Promise<{ replies: Array<{ id: string; text: string }> }> {
  const res = await http.get<{ data: { replies: Array<{ id: string; text: string }> } }>(
    `/chats/${chatId}/ai/suggestions`,
  )
  return res.data.data
}
