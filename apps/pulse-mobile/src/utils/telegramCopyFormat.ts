import type { ChatMessage, MessageMediaItem } from '../types/chat'

function pad2(n: number): string {
  return n.toString().padStart(2, '0')
}

export function formatRuDateTimeBracket(iso: string | undefined): string {
  const src = iso ?? new Date().toISOString()
  const d = new Date(src)
  if (Number.isNaN(d.getTime())) {
    const x = new Date()
    return `[${pad2(x.getDate())}.${pad2(x.getMonth() + 1)}.${x.getFullYear()} ${pad2(x.getHours())}:${pad2(x.getMinutes())}]`
  }
  return `[${pad2(d.getDate())}.${pad2(d.getMonth() + 1)}.${d.getFullYear()} ${pad2(d.getHours())}:${pad2(d.getMinutes())}]`
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
