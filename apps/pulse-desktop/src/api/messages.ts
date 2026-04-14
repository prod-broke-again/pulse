import { api } from './client'
import type {
  ApiMessage,
  MessageListParams,
  MessagesListResponse,
  MessageResponse
} from '../types/dto/chat.types'

export async function fetchMessages(chatId: number, params: MessageListParams = {}): Promise<ApiMessage[]> {
  const response = await api.get<MessagesListResponse>(
    `/chats/${chatId}/messages`,
    params as Record<string, string | number | undefined>,
  )
  return response.data
}

export async function sendMessage(
  chatId: number,
  text: string,
  attachments: string[] = [],
  clientMessageId?: string,
  replyMarkup?: { text: string; url: string }[],
  replyToMessageId?: number,
): Promise<ApiMessage> {
  const body: Record<string, unknown> = { text }
  if (attachments.length > 0) {
    body.attachments = attachments
  }
  if (clientMessageId) {
    body.client_message_id = clientMessageId
  }
  if (replyMarkup && replyMarkup.length > 0) {
    body.reply_markup = replyMarkup
  }
  if (replyToMessageId != null && replyToMessageId > 0) {
    body.reply_to_message_id = replyToMessageId
  }
  const response = await api.post<MessageResponse>(`/chats/${chatId}/send`, body)
  return response.data
}

/** Fragment of history around a message (jump-to-reply). */
export async function fetchMessageContext(messageId: number): Promise<ApiMessage[]> {
  const response = await api.get<MessagesListResponse>(`/messages/${messageId}/context`)
  return response.data
}
