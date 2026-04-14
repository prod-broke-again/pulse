import type { NewChatMessagePayload } from '../lib/realtime'
import type { ApiMessageRow } from '../api/types'
import type { ChatMessage, MessageAttachment, MessageKind, ReplyMarkupButton } from '../types/chat'

function formatBytes(n: number): string {
  if (n < 1024) return `${n} Б`
  if (n < 1024 * 1024) return `${(n / 1024).toFixed(1)} КБ`
  return `${(n / (1024 * 1024)).toFixed(1)} МБ`
}

function normalizeReplyMarkup(raw: unknown): ReplyMarkupButton[] | undefined {
  if (!Array.isArray(raw) || raw.length === 0) return undefined
  const out: ReplyMarkupButton[] = []
  for (const row of raw) {
    if (
      row !== null &&
      typeof row === 'object' &&
      'text' in row &&
      'url' in row &&
      typeof (row as { text: unknown }).text === 'string' &&
      typeof (row as { url: unknown }).url === 'string'
    ) {
      out.push({ text: (row as { text: string }).text, url: (row as { url: string }).url })
    }
  }
  return out.length > 0 ? out : undefined
}

function formatTime(iso: string | undefined): string | undefined {
  if (!iso) return undefined
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return undefined
  return `${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}`
}

export function mapRealtimePayloadToChatMessage(p: NewChatMessagePayload): ChatMessage {
  let kind: MessageKind = 'incoming'
  if (p.sender_type === 'system') kind = 'system'
  else if (p.sender_type === 'moderator') kind = 'outgoing'

  const d = new Date()
  const time = `${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}`

  return {
    id: String(p.messageId),
    kind,
    text: p.text,
    time,
    ...(p.reply_to ? { reply_to: p.reply_to } : {}),
    ...(kind === 'incoming' ? { isRead: false } : {}),
  }
}

export function mapApiMessageToChatMessage(row: ApiMessageRow): ChatMessage {
  let kind: MessageKind = 'incoming'
  if (row.sender_type === 'system') kind = 'system'
  else if (row.sender_type === 'moderator') kind = 'outgoing'

  const time = formatTime(row.created_at)
  const atts = row.attachments ?? []
  let attachment: MessageAttachment | undefined
  if (atts.length > 0) {
    const a = atts[0]!
    attachment = {
      fileName: a.name ?? 'файл',
      sizeLabel: typeof a.size === 'number' ? formatBytes(a.size) : '',
    }
  }

  const text = row.text ?? ''
  const showAttachmentOnly = atts.length > 0 && !text.trim()

  const replyMarkup = normalizeReplyMarkup(row.reply_markup)

  return {
    id: String(row.id),
    kind,
    text: showAttachmentOnly ? '' : text,
    time,
    attachment,
    ...(row.reply_to ? { reply_to: row.reply_to } : {}),
    ...(replyMarkup ? { reply_markup: replyMarkup } : {}),
    ...(kind === 'incoming' && row.is_read === true ? { isRead: true } : {}),
  }
}
