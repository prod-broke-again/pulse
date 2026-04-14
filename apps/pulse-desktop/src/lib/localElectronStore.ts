import type { ApiMessage } from '../types/dto/chat.types'
import type { OutboxSendPayload } from '../types/outbox'

function hasElectronStore(): boolean {
  return typeof window !== 'undefined' && window.pulseLocalCache !== undefined && window.pulseOutbox !== undefined
}

export async function getCachedMessages(chatId: number): Promise<ApiMessage[] | null> {
  if (!hasElectronStore() || !window.pulseLocalCache) {
    return null
  }
  try {
    const raw = await window.pulseLocalCache.get(chatId)
    if (!raw) {
      return null
    }
    const parsed = JSON.parse(raw) as ApiMessage[]
    return Array.isArray(parsed) ? parsed : null
  } catch {
    return null
  }
}

export async function setCachedMessages(chatId: number, messages: ApiMessage[]): Promise<void> {
  if (!hasElectronStore() || !window.pulseLocalCache) {
    return
  }
  try {
    await window.pulseLocalCache.set(chatId, JSON.stringify(messages))
  } catch (e) {
    console.warn('[pulseLocalCache] save failed', e)
  }
}

export async function outboxListRows(): Promise<
  Array<{ id: string; chat_id: number; payload: string; created_at: number; attempts: number }>
> {
  if (!window.pulseOutbox) {
    return []
  }
  return window.pulseOutbox.list()
}

export async function outboxEnqueue(rowId: string, chatId: number, payload: OutboxSendPayload): Promise<void> {
  if (!window.pulseOutbox) {
    return
  }
  await window.pulseOutbox.add(rowId, chatId, JSON.stringify(payload))
}

export async function outboxDelete(rowId: string): Promise<void> {
  if (!window.pulseOutbox) {
    return
  }
  await window.pulseOutbox.remove(rowId)
}

export async function outboxBumpAttempts(rowId: string): Promise<void> {
  if (!window.pulseOutbox) {
    return
  }
  await window.pulseOutbox.incrementAttempts(rowId)
}

export function isElectronLocalStoreAvailable(): boolean {
  return hasElectronStore()
}
