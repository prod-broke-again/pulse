import type { MessageItem } from '../types/chat'

function part(parts: Intl.DateTimeFormatPart[], type: Intl.DateTimeFormatPartTypes): string {
  return parts.find((p) => p.type === type)?.value ?? ''
}

/**
 * `[18.03.2026 22:32]` in the moderator machine local timezone (explicit IANA from the browser).
 * ISO strings from the API are instants (UTC); this formats them for copy/paste like Telegram desktop.
 */
export function formatRuDateTimeBracket(iso: string | null): string {
  const d = iso != null && iso !== '' ? new Date(iso) : new Date()
  if (Number.isNaN(d.getTime())) {
    return '[]'
  }
  const timeZone = Intl.DateTimeFormat().resolvedOptions().timeZone
  const parts = new Intl.DateTimeFormat('en-GB', {
    timeZone,
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
  }).formatToParts(d)
  const day = part(parts, 'day')
  const month = part(parts, 'month')
  const year = part(parts, 'year')
  const hour = part(parts, 'hour')
  const minute = part(parts, 'minute')
  return `[${day}.${month}.${year} ${hour}:${minute}]`
}

function formatAttachmentSummary(m: MessageItem): string {
  const atts = m.attachments ?? []
  if (atts.length === 0) {
    return ''
  }
  const allImg = atts.every((a) => a.mime_type.startsWith('image/'))
  if (allImg) {
    return atts.length > 1 ? `[Фото ×${atts.length}]` : '[Фото]'
  }
  const allAudio = atts.every((a) => a.mime_type.startsWith('audio/'))
  if (allAudio) {
    return atts.length > 1 ? `[Аудио ×${atts.length}]` : '[Аудио]'
  }
  if (atts.length > 1) {
    return `[Вложений: ${atts.length}]`
  }
  const one = atts[0]!
  if (one.mime_type.startsWith('audio/')) {
    return '[Аудио]'
  }
  return `[${one.name}]`
}

function messageBodyLine(m: MessageItem): string {
  const t = (m.text ?? '').trim()
  if (t !== '') {
    return t
  }
  return formatAttachmentSummary(m)
}

/**
 * Plain text only: body line per message (or attachment summary), in timeline order.
 * No names, dates, or bracket prefixes.
 */
export function formatMessagesPlainText(
  timeline: MessageItem[],
  selectedIds: ReadonlySet<number>,
): string {
  const lines: string[] = []
  for (const m of timeline) {
    if (m.from === 'system') {
      continue
    }
    if (!selectedIds.has(m.id)) {
      continue
    }
    const body = messageBodyLine(m)
    if (body !== '') {
      lines.push(body)
    }
  }
  return lines.join('\n\n')
}

/**
 * Multi-line string like Telegram desktop paste: one block per message.
 */
export function formatMessagesTelegramStyle(
  timeline: MessageItem[],
  selectedIds: ReadonlySet<number>,
  peerName: string,
  moderatorName: string,
): string {
  const lines: string[] = []
  for (const m of timeline) {
    if (m.from === 'system') {
      continue
    }
    if (!selectedIds.has(m.id)) {
      continue
    }
    const who =
      m.from === 'client'
        ? peerName
        : m.delivery_channel === 'telegram_app'
          ? 'Telegram'
          : moderatorName
    const prefix = formatRuDateTimeBracket(m.createdAtIso)
    const body = messageBodyLine(m)
    lines.push(`${prefix} ${who}: ${body}`)
  }
  return lines.join('\n')
}
