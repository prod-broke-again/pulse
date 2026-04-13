import type { ChatMessage } from '../../types/chat'
import type { ApiMessageRow } from '../../api/types'
import { mapApiMessageToChatMessage, mapRealtimePayloadToChatMessage } from '../../mappers/messageMapper'
import { compareMessageIds } from '../../lib/chatIds'
import type { NewChatMessagePayload } from '../../lib/realtime'

/**
 * Merge API rows into the message list by id (dedupe), then sort.
 */
export function mergeFetchedNewerRows(
  current: ChatMessage[],
  rows: ApiMessageRow[],
): ChatMessage[] {
  const byId = new Map(current.map((m) => [m.id, m]))
  for (const row of rows) {
    byId.set(String(row.id), mapApiMessageToChatMessage(row))
  }
  return Array.from(byId.values()).sort((a, b) => compareMessageIds(a.id, b.id))
}

/** Append realtime-mapped message if not already present (shared try/catch path). */
export function appendRealtimeMessageIfNew(
  current: ChatMessage[],
  payload: NewChatMessagePayload,
): ChatMessage[] {
  const mapped = mapRealtimePayloadToChatMessage(payload)
  if (current.some((m) => m.id === mapped.id)) {
    return current
  }
  return [...current, mapped]
}
