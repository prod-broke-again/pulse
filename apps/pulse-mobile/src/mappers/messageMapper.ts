import type { ChatMessageUpdatedPayload, NewChatMessagePayload, PendingAttachmentMeta } from '../lib/realtime'
import type { ApiMessageRow } from '../api/types'
import type { ChatMessage, MessageKind, MessageMediaItem, ReplyMarkupButton } from '../types/chat'

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

function mapApiAttachmentsToMediaItems(
  atts: ApiMessageRow['attachments'] | undefined,
): MessageMediaItem[] | undefined {
  if (!atts?.length) return undefined
  const out: MessageMediaItem[] = []
  for (const a of atts) {
    const url = typeof a.url === 'string' ? a.url.trim() : ''
    if (!url) continue
    const size = typeof a.size === 'number' ? a.size : undefined
    out.push({
      id: typeof a.id === 'number' ? a.id : undefined,
      url,
      name: typeof a.name === 'string' && a.name !== '' ? a.name : 'файл',
      mime_type:
        typeof a.mime_type === 'string' && a.mime_type !== '' ? a.mime_type : 'application/octet-stream',
      size,
      sizeLabel: size != null ? formatBytes(size) : '',
    })
  }
  return out.length > 0 ? out : undefined
}

function normalizePendingSlots(
  raw: PendingAttachmentMeta[] | undefined,
): ChatMessage['pendingMediaSlots'] {
  if (!raw?.length) {
    return undefined
  }
  const out: NonNullable<ChatMessage['pendingMediaSlots']> = []
  for (const x of raw) {
    if (x !== null && typeof x === 'object' && typeof x.type === 'string') {
      out.push({
        type: x.type,
        ...(typeof x.source_url === 'string' ? { source_url: x.source_url } : {}),
        ...(typeof x.kind === 'string' ? { kind: x.kind } : {}),
      })
    }
  }
  return out.length > 0 ? out : undefined
}

function pendingSlotsFromPayload(raw: unknown): ChatMessage['pendingMediaSlots'] {
  if (!Array.isArray(raw) || raw.length === 0) {
    return undefined
  }
  const out: NonNullable<ChatMessage['pendingMediaSlots']> = []
  for (const x of raw) {
    if (x !== null && typeof x === 'object' && typeof (x as { type?: unknown }).type === 'string') {
      const o = x as { type: string; source_url?: string; kind?: string }
      out.push({
        type: o.type,
        ...(typeof o.source_url === 'string' ? { source_url: o.source_url } : {}),
        ...(typeof o.kind === 'string' ? { kind: o.kind } : {}),
      })
    }
  }
  return out.length > 0 ? out : undefined
}

function mapRealtimeAttachmentsToMediaItems(
  raw: Array<Record<string, unknown>> | undefined,
): MessageMediaItem[] | undefined {
  if (!raw?.length) return undefined
  const out: MessageMediaItem[] = []
  for (const row of raw) {
    const url = typeof row.url === 'string' ? row.url.trim() : ''
    if (!url) continue
    const size = typeof row.size === 'number' ? row.size : undefined
    out.push({
      id: typeof row.id === 'number' ? row.id : undefined,
      url,
      name: typeof row.name === 'string' && row.name !== '' ? row.name : 'файл',
      mime_type:
        typeof row.mime_type === 'string' && row.mime_type !== ''
          ? row.mime_type
          : 'application/octet-stream',
      size,
      sizeLabel: size != null ? formatBytes(size) : '',
    })
  }
  return out.length > 0 ? out : undefined
}

export function mapRealtimePayloadToChatMessage(p: NewChatMessagePayload): ChatMessage {
  let kind: MessageKind = 'incoming'
  if (p.sender_type === 'system') kind = 'system'
  else if (p.sender_type === 'moderator') kind = 'outgoing'

  const d = new Date()
  const time = `${d.getHours().toString().padStart(2, '0')}:${d.getMinutes().toString().padStart(2, '0')}`

  const mediaAttachments = mapRealtimeAttachmentsToMediaItems(p.attachments)
  const pendingSlots = normalizePendingSlots(p.pending_attachments)

  return {
    id: String(p.messageId),
    kind,
    text: p.text,
    time,
    createdAtIso: new Date().toISOString(),
    ...(mediaAttachments ? { mediaAttachments } : {}),
    ...(pendingSlots ? { pendingMediaSlots: pendingSlots } : {}),
    ...(p.reply_to ? { reply_to: p.reply_to } : {}),
    ...(kind === 'incoming' ? { isRead: false } : {}),
  }
}

export function mapApiMessageToChatMessage(row: ApiMessageRow): ChatMessage {
  let kind: MessageKind = 'incoming'
  if (row.sender_type === 'system') kind = 'system'
  else if (row.sender_type === 'moderator') kind = 'outgoing'

  const time = formatTime(row.created_at)
  const mediaAttachments = mapApiAttachmentsToMediaItems(row.attachments)
  const pendingSlots = pendingSlotsFromPayload(row.payload?.pending_attachments)
  const text = row.text ?? ''

  const replyMarkup = normalizeReplyMarkup(row.reply_markup)

  return {
    id: String(row.id),
    kind,
    text,
    time,
    createdAtIso: row.created_at,
    ...(mediaAttachments ? { mediaAttachments } : {}),
    ...(pendingSlots ? { pendingMediaSlots: pendingSlots } : {}),
    ...(row.reply_to ? { reply_to: row.reply_to } : {}),
    ...(replyMarkup ? { reply_markup: replyMarkup } : {}),
    ...(kind === 'incoming' && row.is_read === true ? { isRead: true } : {}),
  }
}

/** Patch attachments + pending slots after DownloadInboundAttachmentJob (WebSocket). */
export function applyChatMessageUpdatedPayload(
  current: ChatMessage[],
  payload: ChatMessageUpdatedPayload,
): ChatMessage[] {
  const mid = String(payload.messageId)
  const media = mapRealtimeAttachmentsToMediaItems(payload.attachments)
  const pendingSlots = normalizePendingSlots(payload.pending_attachments)

  return current.map((m) => {
    if (m.id !== mid) {
      return m
    }
    return {
      ...m,
      ...(media?.length ? { mediaAttachments: media } : { mediaAttachments: undefined }),
      pendingMediaSlots: pendingSlots,
    }
  })
}
