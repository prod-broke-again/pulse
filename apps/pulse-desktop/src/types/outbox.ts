/** Тело для POST /chats/{id}/send — хранится в SQLite outbox (Electron). */
export type OutboxSendPayload = {
  chatId: number
  text: string
  attachments: string[]
  clientMessageId: string
  replyMarkup?: { text: string; url: string }[]
  /** Same as POST /chats/{id}/send reply_to_message_id */
  replyToMessageId?: number
}
