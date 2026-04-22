import type { ApiChat, ApiMessage } from '../types/dto/chat.types'
import type { Conversation, MessageItem } from '../types/chat'
import type { NewChatMessagePayload } from '../lib/realtime'

function mapWsAttachmentsToMessageItemAttachments(
  raw: Array<Record<string, unknown>> | undefined,
): MessageItem['attachments'] {
  if (!raw?.length) {
    return undefined
  }
  const out: NonNullable<MessageItem['attachments']> = []
  for (let i = 0; i < raw.length; i++) {
    const row = raw[i]
    const url = typeof row.url === 'string' ? row.url.trim() : ''
    if (!url) {
      continue
    }
    out.push({
      id: typeof row.id === 'number' ? row.id : -(i + 1),
      name: typeof row.name === 'string' && row.name !== '' ? row.name : 'file',
      mime_type:
        typeof row.mime_type === 'string' && row.mime_type !== ''
          ? row.mime_type
          : 'application/octet-stream',
      size: typeof row.size === 'number' ? row.size : 0,
      url,
    })
  }
  return out.length > 0 ? out : undefined
}

export function mapChatToConversation(chat: ApiChat, activeId: number | null): Conversation {
  const metadata = chat.user_metadata ?? {}
  const name =
    (typeof metadata.name === 'string' && metadata.name.trim() !== '' ? metadata.name : '') ||
    (metadata.first_name as string) ||
    (metadata.username as string) ||
    chat.external_user_id
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

  const unreadCount = typeof chat.unread_count === 'number' ? Math.max(0, chat.unread_count) : 0

  const topicRaw = chat.topic
  const topic =
    typeof topicRaw === 'string' && topicRaw.trim() !== '' ? topicRaw.trim() : null

  const sourceNameRaw = chat.source?.name
  const sourceName =
    typeof sourceNameRaw === 'string' && sourceNameRaw.trim() !== ''
      ? sourceNameRaw.trim()
      : undefined

  return {
    id: chat.id,
    initials,
    name,
    channel,
    department: chat.department?.name ?? '',
    departmentIcon: chat.department?.icon ?? null,
    topic,
    message: chat.latest_message?.text ?? '',
    time: latestTime,
    active: chat.id === activeId,
    unreadCount,
    unread: unreadCount > 0,
    sourceId: chat.source_id,
    ...(sourceName ? { sourceName } : {}),
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

  const pending = p.pending_attachments
  const pendingNorm =
    Array.isArray(pending) && pending.length > 0
      ? pending.filter(
          (x): x is { type: string; source_url?: string; kind?: string } =>
            x !== null && typeof x === 'object' && typeof (x as { type?: unknown }).type === 'string',
        )
      : undefined

  const deliveryChannel =
    typeof p.delivery_channel === 'string' && p.delivery_channel !== ''
      ? p.delivery_channel
      : undefined

  return {
    id: p.messageId,
    from,
    text: p.text,
    time,
    createdAtIso: new Date().toISOString(),
    attachments: mapWsAttachmentsToMessageItemAttachments(p.attachments),
    ...(pendingNorm ? { pending_attachments: pendingNorm } : {}),
    ...(deliveryChannel ? { delivery_channel: deliveryChannel } : {}),
  }
}

export function mapApiMessage(msg: ApiMessage): MessageItem {
  const senderType = msg.sender_type as 'client' | 'moderator' | 'system'
  const time = msg.created_at
    ? new Date(msg.created_at).toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' })
    : ''

  const rawPending = msg.payload?.pending_attachments
  const pendingNorm =
    Array.isArray(rawPending) && rawPending.length > 0
      ? rawPending.filter(
          (x): x is { type: string; source_url?: string; kind?: string } =>
            x !== null && typeof x === 'object' && typeof (x as { type?: unknown }).type === 'string',
        )
      : undefined

  const dchRaw = msg.payload?.delivery_channel
  const deliveryChannel =
    typeof dchRaw === 'string' && dchRaw !== '' ? dchRaw : undefined

  return {
    id: msg.id,
    from: senderType,
    text: msg.text,
    time,
    createdAtIso: msg.created_at ?? null,
    attachments: msg.attachments ?? [],
    ...(pendingNorm ? { pending_attachments: pendingNorm } : {}),
    reply_markup: msg.reply_markup ?? undefined,
    reply_to: msg.reply_to ?? undefined,
    is_read: msg.is_read,
    pending: msg.id < 0,
    ...(deliveryChannel ? { delivery_channel: deliveryChannel } : {}),
  }
}
