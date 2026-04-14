import type { ApiChat, ApiMessage } from '../types/dto/chat.types'
import type { Conversation, MessageItem } from '../types/chat'
import type { NewChatMessagePayload } from '../lib/realtime'

export function mapChatToConversation(chat: ApiChat, activeId: number | null): Conversation {
  const metadata = chat.user_metadata ?? {}
  const name = (metadata.first_name as string) || (metadata.username as string) || chat.external_user_id
  const initials = name
    .split(' ')
    .map((w: string) => w.charAt(0).toUpperCase())
    .slice(0, 2)
    .join('')

  const sourceType = chat.source?.type ?? 'web'
  let channel: Conversation['channel'] = 'web'
  if (sourceType === 'tg') channel = 'telegram'
  else if (sourceType === 'vk') channel = 'vk'
  else if (sourceType === 'max') channel = 'web'

  const latestTime = chat.latest_message?.created_at
    ? new Date(chat.latest_message.created_at).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
    : ''

  const clientAvatarUrl =
    typeof metadata.avatar_url === 'string' && metadata.avatar_url.trim() !== ''
      ? metadata.avatar_url.trim()
      : null

  return {
    id: chat.id,
    initials,
    name,
    channel,
    department: chat.department?.name ?? '',
    message: chat.latest_message?.text ?? '',
    time: latestTime,
    active: chat.id === activeId,
    unread: (typeof chat.unread_count === 'number' ? chat.unread_count : 0) > 0,
    sourceId: chat.source_id,
    assignedTo: chat.assigned_to,
    assignee: chat.assignee ?? null,
    clientAvatarUrl,
    status: chat.status === 'closed' ? 'closed' : 'open',
    isUrgent: chat.is_urgent,
    muted_until: chat.muted_until ?? null,
  }
}

/** Минимальная карта WS-события (полная карточка подтянется при следующей перезагрузке треда). */
export function mapRealtimePayloadToMessageItem(p: NewChatMessagePayload): MessageItem {
  const st = p.sender_type
  let from: MessageItem['from'] = 'client'
  if (st === 'system') {
    from = 'system'
  } else if (st === 'moderator') {
    from = 'moderator'
  }

  const d = new Date()
  const time = d.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })

  return {
    id: p.messageId,
    from,
    text: p.text,
    time,
    attachments: [],
  }
}

export function mapApiMessage(msg: ApiMessage): MessageItem {
  const senderType = msg.sender_type as 'client' | 'moderator' | 'system'
  const time = msg.created_at
    ? new Date(msg.created_at).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
    : ''

  return {
    id: msg.id,
    from: senderType,
    text: msg.text,
    time,
    attachments: msg.attachments ?? [],
    reply_markup: msg.reply_markup ?? undefined,
    reply_to: msg.reply_to ?? undefined,
    is_read: msg.is_read,
    pending: msg.id < 0,
  }
}
