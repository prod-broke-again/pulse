import { sendMessage as apiSendMessage } from '../api/messages'
import type { ApiMessage } from '../types/dto/chat.types'
import type { OutboxSendPayload } from '../types/outbox'
import { outboxBumpAttempts, outboxDelete, outboxListRows } from './localElectronStore'

/**
 * Отправка накопленных сообщений при появлении сети.
 * Возвращает число успешно отправленных.
 */
export async function processOutboxQueue(
  options: {
    /** Заменить оптимистичное сообщение в сторе по clientMessageId */
    onSuccess: (chatId: number, clientMessageId: string, message: ApiMessage) => void
  },
): Promise<number> {
  const rows = await outboxListRows()
  if (rows.length === 0) {
    return 0
  }

  let ok = 0
  for (const row of rows) {
    let payload: OutboxSendPayload
    try {
      payload = JSON.parse(row.payload) as OutboxSendPayload
    } catch {
      await outboxDelete(row.id)
      continue
    }

    if (!navigator.onLine) {
      break
    }

    try {
      const message = await apiSendMessage(
        payload.chatId,
        payload.text,
        payload.attachments,
        payload.clientMessageId,
        payload.replyMarkup,
        payload.replyToMessageId,
      )
      await outboxDelete(row.id)
      options.onSuccess(payload.chatId, payload.clientMessageId, message)
      ok++
    } catch (e) {
      console.warn('[outbox] send failed', row.id, e)
      await outboxBumpAttempts(row.id)
    }
  }

  return ok
}
