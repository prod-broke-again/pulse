import type { ApiChatRow } from '../api/types'
import type { ChannelSource, ChatPreviewItem } from '../types/chat'

function channelFromApi(v: string | null | undefined): ChannelSource {
  if (v === 'tg' || v === 'vk' || v === 'web') return v
  return 'web'
}

function initialsFromName(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  if (parts.length >= 2) return (parts[0]![0]! + parts[1]![0]!).toUpperCase()
  if (parts.length === 1 && parts[0]!.length >= 2) return parts[0]!.slice(0, 2).toUpperCase()
  const n = name.trim()
  return n.length >= 2 ? n.slice(0, 2).toUpperCase() : (n[0]?.toUpperCase() ?? '?')
}

function formatTimeLabel(iso: string | undefined): string {
  if (!iso) return ''
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return ''
  const now = new Date()
  const startToday = new Date(now.getFullYear(), now.getMonth(), now.getDate())
  const startMsg = new Date(d.getFullYear(), d.getMonth(), d.getDate())
  const diffDays = Math.floor((startToday.getTime() - startMsg.getTime()) / 86400000)

  if (diffDays === 0) {
    const diffM = Math.floor((now.getTime() - d.getTime()) / 60000)
    if (diffM < 1) return 'сейчас'
    if (diffM < 60) return `${diffM} мин`
    const h = d.getHours()
    const m = d.getMinutes()
    return `${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`
  }
  if (diffDays === 1) return 'Вчера'
  if (diffDays < 7) return `${diffDays} дн`
  return new Intl.DateTimeFormat('ru-RU', { day: 'numeric', month: 'short' }).format(d)
}

function resolveGuestName(row: ApiChatRow): string {
  const raw = row.user_metadata?.name?.trim() ?? ''
  const normalized = raw.toLowerCase()
  if (raw && !['гость', 'guest', 'клиент', 'client'].includes(normalized)) {
    return raw
  }

  return row.external_user_id?.trim() || 'Клиент'
}

export function mapApiChatToPreview(row: ApiChatRow): ChatPreviewItem {
  const name = resolveGuestName(row)
  const channel = channelFromApi(row.channel ?? row.source?.type)
  const department = row.category_label ?? row.department?.name ?? 'Поддержка'
  const status = row.status === 'closed' ? 'closed' : 'open'
  const unreadCount = row.unread_count ?? 0
  return {
    id: String(row.id),
    initials: initialsFromName(name),
    name,
    timeLabel: formatTimeLabel(row.last_message_at ?? undefined),
    preview: row.last_message_preview ?? '',
    department,
    channel,
    unread: unreadCount > 0,
    unreadCount: unreadCount > 0 ? unreadCount : undefined,
    hasAiTag: Boolean(row.ai_badge ?? row.ai_enabled),
    status,
  }
}
