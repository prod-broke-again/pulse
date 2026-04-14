import type { MessageItem } from '../types/chat'

function pad2(n: number): string {
  return n.toString().padStart(2, '0')
}

/** `[18.03.2026 22:32]` */
export function formatRuDateTimeBracket(iso: string | null): string {
  if (!iso) {
    const d = new Date()
    return `[${pad2(d.getDate())}.${pad2(d.getMonth() + 1)}.${d.getFullYear()} ${pad2(d.getHours())}:${pad2(d.getMinutes())}]`
  }
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) {
    return '[]'
  }
  return `[${pad2(d.getDate())}.${pad2(d.getMonth() + 1)}.${d.getFullYear()} ${pad2(d.getHours())}:${pad2(d.getMinutes())}]`
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
    const who = m.from === 'client' ? peerName : moderatorName
    const prefix = formatRuDateTimeBracket(m.createdAtIso)
    const body = messageBodyLine(m)
    lines.push(`${prefix} ${who}: ${body}`)
  }
  return lines.join('\n')
}
