import type { ChatMessage, MessageMediaItem } from '../types/chat'

function part(parts: Intl.DateTimeFormatPart[], type: Intl.DateTimeFormatPartTypes): string {
  return parts.find((p) => p.type === type)?.value ?? ''
}

/** `[dd.mm.yyyy HH:mm]` in device local timezone (Capacitor / WebView). */
export function formatRuDateTimeBracket(iso: string | undefined): string {
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

function mediaSummary(items: MessageMediaItem[] | undefined): string {
  if (!items?.length) return ''
  const allImg = items.every((a) => a.mime_type.startsWith('image/'))
  if (allImg) return items.length > 1 ? `[Фото ×${items.length}]` : '[Фото]'
  const allAudio = items.every((a) => a.mime_type.startsWith('audio/'))
  if (allAudio) return items.length > 1 ? `[Аудио ×${items.length}]` : '[Аудио]'
  if (items.length > 1) return `[Вложений: ${items.length}]`
  return `[${items[0]!.name}]`
}

function bodyLine(m: ChatMessage): string {
  const t = (m.text ?? '').trim()
  if (t !== '') return t
  return mediaSummary(m.mediaAttachments)
}

export function formatChatMessagesTelegramStyle(
  timeline: ChatMessage[],
  selectedIds: ReadonlySet<string>,
  peerName: string,
  moderatorName: string,
): string {
  const lines: string[] = []
  for (const m of timeline) {
    if (m.kind === 'system') continue
    if (!selectedIds.has(String(m.id))) continue
    const who = m.kind === 'outgoing' ? moderatorName : peerName
    lines.push(`${formatRuDateTimeBracket(m.createdAtIso)} ${who}: ${bodyLine(m)}`)
  }
  return lines.join('\n')
}
