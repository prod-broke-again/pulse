import { mapApiChatToPreview } from '../mappers/chatMapper'
import type { ApiChatRow } from '../api/types'
import type { AiPanelContent, ChatMessage, ChatThreadMeta, ReplyMarkupButton } from '../types/chat'

const MAX_CACHED = 50

export interface ThreadCacheEntry {
  threadMeta: ChatThreadMeta
  messages: ChatMessage[]
  oldestMessageId: number | null
  hasMoreOlder: boolean
  aiContent: AiPanelContent | null
  cannedQuickReplies: Array<{ label: string; text: string }>
  quickLinkPresets: ReplyMarkupButton[]
  /** Last raw chat from API (optional) for meta bumps */
  _rawChatId?: number
}

const byChatId = new Map<string, ThreadCacheEntry>()

function evictIfNeeded(): void {
  if (byChatId.size < MAX_CACHED) {
    return
  }
  const first = byChatId.keys().next().value as string | undefined
  if (first !== undefined) {
    byChatId.delete(first)
  }
}

export function getThreadCache(chatId: string): ThreadCacheEntry | undefined {
  return byChatId.get(chatId)
}

export function setThreadCache(chatId: string, entry: ThreadCacheEntry): void {
  evictIfNeeded()
  if (byChatId.has(chatId)) {
    byChatId.delete(chatId)
  }
  byChatId.set(chatId, entry)
}

function cloneMessage(m: ChatMessage): ChatMessage {
  return { ...m, reply_to: m.reply_to ? { ...m.reply_to } : undefined, reply_markup: m.reply_markup ? [...m.reply_markup] : undefined }
}

export function snapshotToEntry(params: {
  threadMeta: ChatThreadMeta
  messages: ChatMessage[]
  oldestMessageId: number | null
  hasMoreOlder: boolean
  aiContent: AiPanelContent | null
  cannedQuickReplies: Array<{ label: string; text: string }>
  quickLinkPresets: ReplyMarkupButton[]
}): ThreadCacheEntry {
  return {
    threadMeta: { ...params.threadMeta },
    messages: params.messages.map(cloneMessage),
    oldestMessageId: params.oldestMessageId,
    hasMoreOlder: params.hasMoreOlder,
    aiContent: params.aiContent
      ? { ...params.aiContent, replies: params.aiContent.replies.map((r) => ({ ...r })) }
      : null,
    cannedQuickReplies: params.cannedQuickReplies.map((x) => ({ ...x })),
    quickLinkPresets: params.quickLinkPresets.map((x) => ({ ...x })),
  }
}

export function clearThreadCache(): void {
  byChatId.clear()
}

/** Merge new API chat row into cached thread meta (assignee, status, topic, …) without refetching messages. */
export function patchCacheMetaFromApiChatIfPresent(chatId: string, row: ApiChatRow): void {
  const e = byChatId.get(chatId)
  if (!e) {
    return
  }
  const preview = mapApiChatToPreview(row)
  e.threadMeta = {
    ...e.threadMeta,
    userName: preview.name,
    status: preview.status,
    channel: preview.channel,
    channelLabel: row.channel_label ?? e.threadMeta.channelLabel,
    externalUserId: row.external_user_id != null && String(row.external_user_id).trim() !== '' ? String(row.external_user_id) : e.threadMeta.externalUserId,
    departmentLabel: preview.department,
    departmentIcon: preview.departmentIcon ?? e.threadMeta.departmentIcon,
    sourceId: row.source_id ?? e.threadMeta.sourceId,
    departmentId: row.department_id ?? row.department?.id ?? e.threadMeta.departmentId,
    assignedToUserId: row.assignee?.id ?? (row.assigned_to != null ? Number(row.assigned_to) : e.threadMeta.assignedToUserId),
    muted_until: row.muted_until ?? e.threadMeta.muted_until,
  }
}
