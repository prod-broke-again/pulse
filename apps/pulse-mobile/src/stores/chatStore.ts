import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'
import * as aiApi from '../api/aiRepository'
import * as cannedApi from '../api/cannedResponseRepository'
import * as chatApi from '../api/chatRepository'
import * as quickLinkApi from '../api/quickLinkRepository'
import * as msgApi from '../api/messageRepository'
import * as uploadApi from '../api/uploadRepository'
import { maxNumericMessageIdFromList, parseApiChatId } from '../lib/chatIds'
import { isMutedUntilActive } from '../lib/chatMute'
import { vibrateIncoming } from '../lib/notificationFeedback'
import { subscribeChatChannel } from '../lib/realtime'
import type { ChatMessageUpdatedPayload } from '../lib/realtime'
import { mapApiChatToPreview } from '../mappers/chatMapper'
import { applyChatMessageUpdatedPayload, mapApiMessageToChatMessage } from '../mappers/messageMapper'
import { appendRealtimeMessageIfNew, mergeFetchedNewerRows } from './chat/realtimeMerge'
import {
  getThreadCache,
  patchCacheMetaFromApiChatIfPresent,
  setThreadCache,
  snapshotToEntry,
} from '../lib/chatThreadCache'
import { useAuthStore } from './authStore'
import { useChatUiStore } from './chatUiStore'
import { useInboxStore } from './inboxStore'
import { useSettingsStore } from './settingsStore'
import { useUiStore } from './uiStore'
import type { ApiChatRow } from '../api/types'
import type {
  AiPanelContent,
  ChannelSource,
  ChatMessage,
  ChatThreadMeta,
  ReplyMarkupButton,
} from '../types/chat'

const AI_HINT = 'Нажмите «AI-ответ» ниже, чтобы открыть резюме и подсказки.'

function buildSkeletonThreadMeta(chatId: string): ChatThreadMeta {
  return {
    id: chatId,
    userName: 'Загрузка…',
    status: 'open',
    channel: 'web',
    channelLabel: '…',
    externalUserId: null,
    departmentLabel: '…',
    departmentIcon: null,
    sourceId: null,
    departmentId: null,
    aiSummaryBar: AI_HINT,
    assignedToUserId: null,
    muted_until: null,
  }
}

export const useChatStore = defineStore('chat', () => {
  const activeChatId = ref<string | null>(null)
  const messages = ref<ChatMessage[]>([])
  const composerText = ref('')
  /** Pulse message id to reply to (POST reply_to_message_id). */
  const replyToMessageId = ref<number | null>(null)
  const isTyping = ref(false)
  const aiContent = ref<AiPanelContent | null>(null)
  const loadingOlder = ref(false)
  const hasMoreOlder = ref(true)
  const oldestMessageId = ref<number | null>(null)
  const threadMeta = ref<ChatThreadMeta | null>(null)
  const pendingReplyMarkup = ref<ReplyMarkupButton[]>([])
  /** Canned responses for quick-reply chips (from API; ComposerBar falls back if empty). */
  const cannedQuickReplies = ref<Array<{ label: string; text: string }>>([])
  /** Quick link buttons for Zap menu (from API). */
  const quickLinkPresets = ref<ReplyMarkupButton[]>([])

  /** Сеть догружает тред (фон, после кеша). */
  const threadSyncing = ref(false)
  let saveCacheDebounce: ReturnType<typeof setTimeout> | null = null

  let unsubscribeRealtime: (() => void) | null = null
  let typingClearTimer: number | null = null
  let readNearBottomTimer: number | null = null
  let readRequestInFlight = false
  let lastSentReadWatermarkKey: string | null = null
  let lastTypingNotifyAt = 0
  let typingNotifyTimer: number | null = null

  watch(activeChatId, () => {
    readRequestInFlight = false
    lastSentReadWatermarkKey = null
  })

  const channelSource = computed((): ChannelSource | null => threadMeta.value?.channel ?? null)

  function maxMessageIdFromList(): number {
    return maxNumericMessageIdFromList(messages.value.map((m) => m.id))
  }

  function assigneeUserId(chat: ApiChatRow): number | null {
    const id = chat.assignee?.id ?? chat.assigned_to
    return id != null && Number.isFinite(Number(id)) ? Number(id) : null
  }

  function applyThreadMetaFromApiChat(chat: ApiChatRow, chatIdStr: string) {
    const preview = mapApiChatToPreview(chat)
    const prev = threadMeta.value
    threadMeta.value = {
      id: chatIdStr,
      userName: preview.name,
      status: preview.status,
      channel: preview.channel,
      channelLabel: chat.channel_label ?? 'Web',
      externalUserId: chat.external_user_id != null && String(chat.external_user_id).trim() !== '' ? String(chat.external_user_id) : null,
      departmentLabel: preview.department,
      departmentIcon: preview.departmentIcon ?? null,
      sourceId: chat.source_id ?? null,
      departmentId: chat.department_id ?? chat.department?.id ?? null,
      aiSummaryBar: prev?.aiSummaryBar ?? AI_HINT,
      assignedToUserId: assigneeUserId(chat),
      muted_until: chat.muted_until ?? null,
    }
  }

  function applyCacheToState(entry: import('../lib/chatThreadCache').ThreadCacheEntry): void {
    threadMeta.value = { ...entry.threadMeta }
    messages.value = entry.messages.map((m) => ({ ...m }))
    oldestMessageId.value = entry.oldestMessageId
    hasMoreOlder.value = entry.hasMoreOlder
    aiContent.value = entry.aiContent ? { ...entry.aiContent, replies: [...entry.aiContent.replies] } : null
    cannedQuickReplies.value = entry.cannedQuickReplies.map((x) => ({ ...x }))
    quickLinkPresets.value = entry.quickLinkPresets.map((x) => ({ ...x }))
  }

  function saveActiveThreadToCacheNow(): void {
    const id = activeChatId.value
    if (!id || !threadMeta.value) {
      return
    }
    setThreadCache(
      id,
      snapshotToEntry({
        threadMeta: threadMeta.value,
        messages: messages.value,
        oldestMessageId: oldestMessageId.value,
        hasMoreOlder: hasMoreOlder.value,
        aiContent: aiContent.value,
        cannedQuickReplies: cannedQuickReplies.value,
        quickLinkPresets: quickLinkPresets.value,
      }),
    )
  }

  function scheduleSaveThreadCache(): void {
    if (saveCacheDebounce != null) {
      clearTimeout(saveCacheDebounce)
    }
    saveCacheDebounce = window.setTimeout(() => {
      saveCacheDebounce = null
      saveActiveThreadToCacheNow()
    }, 350)
  }

  function resetForThreadSwitch(): void {
    pendingReplyMarkup.value = []
    replyToMessageId.value = null
    composerText.value = ''
  }

  async function assignToMe(): Promise<void> {
    const chatId = activeChatId.value
    const id = parseApiChatId(chatId)
    if (id == null) return
    const ui = useUiStore()
    try {
      const chat = await chatApi.assignMe(id)
      applyThreadMetaFromApiChat(chat, chatId!)
      scheduleSaveThreadCache()
      await useInboxStore().loadInbox()
      ui.pushToast('Чат назначен на вас', 'success')
    } catch {
      ui.pushToast('Не удалось назначить чат', 'error')
    }
  }

  async function closeThread(): Promise<void> {
    const chatId = activeChatId.value
    const id = parseApiChatId(chatId)
    if (id == null) return
    const ui = useUiStore()
    try {
      const chat = await chatApi.closeChat(id)
      applyThreadMetaFromApiChat(chat, chatId!)
      scheduleSaveThreadCache()
      await useInboxStore().loadInbox()
      ui.pushToast('Чат закрыт', 'success')
    } catch {
      ui.pushToast('Не удалось закрыть чат', 'error')
    }
  }

  /** Закрытый чат → в работе (назначение на себя на бэкенде переводит в active). */
  async function reopenToWork(): Promise<void> {
    const chatId = activeChatId.value
    const id = parseApiChatId(chatId)
    if (id == null) return
    const ui = useUiStore()
    try {
      const chat = await chatApi.assignMe(id)
      applyThreadMetaFromApiChat(chat, chatId!)
      scheduleSaveThreadCache()
      await useInboxStore().loadInbox()
      ui.pushToast('Чат снова в работе', 'success')
    } catch {
      ui.pushToast('Не удалось открыть чат', 'error')
    }
  }

  async function changeDepartment(departmentId: number): Promise<void> {
    const chatId = activeChatId.value
    const id = parseApiChatId(chatId)
    if (id == null) return
    const ui = useUiStore()
    try {
      const chat = await chatApi.changeChatDepartment(id, departmentId)
      applyThreadMetaFromApiChat(chat, chatId!)
      scheduleSaveThreadCache()
      await useInboxStore().loadInbox()
      ui.pushToast('Отдел обновлён', 'success')
    } catch {
      ui.pushToast('Не удалось сменить отдел', 'error')
    }
  }

  async function markAsRead(chatIdNum: number): Promise<void> {
    const lastId = maxMessageIdFromList()
    if (lastId <= 0) return
    const key = `${chatIdNum}:${lastId}`
    if (readRequestInFlight || lastSentReadWatermarkKey === key) return
    readRequestInFlight = true
    try {
      await chatApi.markChatRead(chatIdNum, lastId)
      lastSentReadWatermarkKey = key
    } catch {
      /* ignore */
    } finally {
      readRequestInFlight = false
    }
  }

  async function loadThreadFromServer(chatId: string): Promise<void> {
    const id = parseApiChatId(chatId)
    if (id == null) {
      return
    }

    const chat = await chatApi.fetchChat(id)
    if (activeChatId.value !== chatId) {
      return
    }

    const [rows, cannedRows, linkRows] = await Promise.all([
      msgApi.fetchMessages(id, { limit: 50 }),
      cannedApi
        .fetchCannedResponses(
          chat.source_id != null
            ? {
                source_id: chat.source_id,
                department_id: chat.department_id ?? chat.department?.id ?? undefined,
                chat_context: true,
              }
            : undefined,
        )
        .catch(() => []),
      quickLinkApi
        .fetchQuickLinks(
          chat.source_id != null
            ? {
                source_id: chat.source_id,
                department_id: chat.department_id ?? chat.department?.id ?? undefined,
                chat_context: true,
              }
            : undefined,
        )
        .catch(() => []),
    ])

    if (activeChatId.value !== chatId) {
      return
    }

    cannedQuickReplies.value = cannedRows.map((r) => ({
      label: (r.title?.trim() || r.code?.trim() || `Шаблон #${r.id}`).slice(0, 80),
      text: r.text,
    }))

    quickLinkPresets.value = linkRows.map((l) => ({
      text: l.title,
      url: l.url,
    }))

    const preview = mapApiChatToPreview(chat)
    threadMeta.value = {
      id: chatId,
      userName: preview.name,
      status: preview.status,
      channel: preview.channel,
      channelLabel: chat.channel_label ?? 'Web',
      externalUserId: chat.external_user_id != null && String(chat.external_user_id).trim() !== '' ? String(chat.external_user_id) : null,
      departmentLabel: preview.department,
      departmentIcon: preview.departmentIcon ?? null,
      sourceId: chat.source_id ?? null,
      departmentId: chat.department_id ?? chat.department?.id ?? null,
      aiSummaryBar: AI_HINT,
      assignedToUserId: assigneeUserId(chat),
      muted_until: chat.muted_until ?? null,
    }

    messages.value = rows.map(mapApiMessageToChatMessage)
    oldestMessageId.value = rows.length > 0 ? rows[0]!.id : null
    hasMoreOlder.value = rows.length >= 50

    if (aiContent.value == null) {
      aiContent.value = {
        summary: AI_HINT,
        intentTag: 'Общее обращение',
        replies: [],
        actionTitle: 'Продолжить диалог',
        actionDesc: '',
        actionButtonLabel: 'Ок',
      }
    }

    saveActiveThreadToCacheNow()
    try {
      await markAsRead(id)
    } catch {
      /* ignore */
    }
  }

  /**
   * @param force — принудительное обновление с сервера (при кеше тред показываем сразу, данные сети догоняют).
   */
  async function fetchThread(chatId: string, options?: { force?: boolean }): Promise<void> {
    const id = parseApiChatId(chatId)
    if (id == null) {
      return
    }

    const force = options?.force === true
    const prev = activeChatId.value
    const switching = prev != null && prev !== chatId

    if (switching) {
      saveActiveThreadToCacheNow()
    }
    const chatUi = useChatUiStore()
    chatUi.closeAiPanel()
    chatUi.clearAiTimers()
    activeChatId.value = chatId
    leaveThread()
    if (switching) {
      resetForThreadSwitch()
    }

    const sameChatRefresh = !switching && prev === chatId && force
    const cached = getThreadCache(chatId)

    if (sameChatRefresh) {
      /* оставляем экран, обновит loadThreadFromServer */
    } else if (cached) {
      applyCacheToState(cached)
      hasMoreOlder.value = cached.hasMoreOlder
      loadingOlder.value = false
    } else {
      hasMoreOlder.value = true
      aiContent.value = null
      threadMeta.value = buildSkeletonThreadMeta(chatId)
      messages.value = []
      loadingOlder.value = true
    }

    subscribeThread(chatId)
    threadSyncing.value = true
    try {
      await loadThreadFromServer(chatId)
    } catch {
      if (activeChatId.value === chatId && threadMeta.value && threadMeta.value.userName === 'Загрузка…') {
        threadMeta.value = {
          ...threadMeta.value!,
          userName: 'Не удалось загрузить',
        }
      }
    } finally {
      threadSyncing.value = false
      loadingOlder.value = false
    }
  }

  async function loadOlderMessages(): Promise<void> {
    const chatId = activeChatId.value
    const before = oldestMessageId.value
    if (!chatId || before == null || loadingOlder.value || !hasMoreOlder.value) return
    const id = parseApiChatId(chatId)
    if (id == null) return

    loadingOlder.value = true
    try {
      const older = await msgApi.fetchMessages(id, { beforeId: before, limit: 50 })
      if (older.length === 0) {
        hasMoreOlder.value = false
        return
      }
      const mapped = older.map(mapApiMessageToChatMessage)
      const existing = new Set(messages.value.map((m) => m.id))
      const merged = [...mapped.filter((m) => !existing.has(m.id)), ...messages.value]
      messages.value = merged
      oldestMessageId.value = older[0]!.id
      if (older.length < 50) hasMoreOlder.value = false
    } finally {
      loadingOlder.value = false
    }
    scheduleSaveThreadCache()
  }

  function setComposerText(value: string) {
    composerText.value = value
  }

  /** Throttled POST /typing while moderator types (как на desktop). */
  function scheduleTypingNotify(): void {
    const chatId = activeChatId.value
    const id = parseApiChatId(chatId)
    if (id == null || composerLocked.value) {
      return
    }
    const now = Date.now()
    if (now - lastTypingNotifyAt < 2500) {
      if (typingNotifyTimer == null) {
        typingNotifyTimer = window.setTimeout(() => {
          typingNotifyTimer = null
          scheduleTypingNotify()
        }, 2600)
      }
      return
    }
    lastTypingNotifyAt = now
    void chatApi.sendTypingIndicator(id).catch(() => {
      /* ignore */
    })
  }

  function setReplyTarget(messageId: number) {
    replyToMessageId.value = messageId > 0 ? messageId : null
  }

  function clearReplyTarget() {
    replyToMessageId.value = null
  }

  const composerLocked = computed(() => {
    const m = threadMeta.value
    if (!m) {
      return false
    }
    if (m.status === 'closed') {
      return true
    }
    const uid = useAuthStore().user?.id ?? null
    if (uid == null) {
      return false
    }
    const a = m.assignedToUserId
    if (a == null) {
      return false
    }
    return a !== uid
  })

  const canSend = computed(
    () =>
      !composerLocked.value &&
      (composerText.value.trim().length > 0 || pendingReplyMarkup.value.length > 0),
  )

  function pad2(n: number) {
    return n.toString().padStart(2, '0')
  }

  function addReplyMarkupPreset(btn: ReplyMarkupButton) {
    pendingReplyMarkup.value = [...pendingReplyMarkup.value, { ...btn }]
  }

  function removeReplyMarkupPreset(index: number) {
    pendingReplyMarkup.value = pendingReplyMarkup.value.filter((_, i) => i !== index)
  }

  async function sendMessage() {
    if (composerLocked.value) {
      return
    }
    const text = composerText.value.trim()
    const chatId = activeChatId.value
    const markupSnapshot = [...pendingReplyMarkup.value]
    const replyId = replyToMessageId.value
    if ((!text && markupSnapshot.length === 0) || !chatId) return
    const id = parseApiChatId(chatId)
    if (id == null) return

    const clientMessageId = crypto.randomUUID?.() ?? `cm-${Date.now()}`
    const now = new Date()
    const time = `${pad2(now.getHours())}:${pad2(now.getMinutes())}`
    const tempId = `temp-${clientMessageId}`
    const replyPreview =
      replyId != null && replyId > 0
        ? messages.value.find((m) => Number(m.id) === replyId)
        : undefined
    const optimisticReplyTo =
      replyId != null && replyId > 0
        ? replyPreview?.reply_to ??
          (replyPreview
            ? {
                id: replyId,
                text: (replyPreview.text ?? '').slice(0, 500),
                sender_type:
                  replyPreview.kind === 'outgoing'
                    ? 'moderator'
                    : replyPreview.kind === 'system'
                      ? 'system'
                      : 'client',
              }
            : { id: replyId, text: '', sender_type: 'client' })
        : undefined
    messages.value = [
      ...messages.value,
      {
        id: tempId,
        kind: 'outgoing',
        text: text || '\u00a0',
        time,
        clientMessageId,
        ...(optimisticReplyTo ? { reply_to: optimisticReplyTo } : {}),
        ...(markupSnapshot.length > 0 ? { reply_markup: markupSnapshot } : {}),
      },
    ]
    composerText.value = ''

    try {
      const payload: Parameters<typeof msgApi.sendMessage>[1] = {
        text: text || '',
        client_message_id: clientMessageId,
      }
      if (markupSnapshot.length > 0) {
        payload.reply_markup = markupSnapshot
      }
      if (replyId != null && replyId > 0) {
        payload.reply_to_message_id = replyId
      }
      const data = await msgApi.sendMessage(id, payload)
      const mapped = mapApiMessageToChatMessage(data)
      messages.value = messages.value.map((m) =>
        m.clientMessageId === clientMessageId ? mapped : m,
      )
      pendingReplyMarkup.value = []
      replyToMessageId.value = null
      useUiStore().pushToast('Сообщение отправлено', 'success')
      await markAsRead(id)
      scheduleSaveThreadCache()
    } catch {
      messages.value = messages.value.filter((m) => m.id !== tempId && m.clientMessageId !== clientMessageId)
      useUiStore().pushToast('Не удалось отправить', 'error')
    }
  }

  async function sendWithAttachments(files: File[]) {
    if (composerLocked.value) {
      return
    }
    const chatId = activeChatId.value
    if (!chatId || files.length === 0) return
    const id = parseApiChatId(chatId)
    if (id == null) return

    const paths: string[] = []
    for (const f of files) {
      const up = await uploadApi.uploadFile(f, f.name)
      paths.push(up.path)
    }
    const clientMessageId = crypto.randomUUID?.() ?? `cm-${Date.now()}`
    try {
      await msgApi.sendMessage(id, {
        text: ' ',
        attachments: paths,
        client_message_id: clientMessageId,
      })
      await fetchThread(chatId, { force: true })
      useUiStore().pushToast('Файл отправлен', 'success')
    } catch {
      useUiStore().pushToast('Не удалось отправить файл', 'error')
    }
  }

  function insertQuickReply(text: string) {
    composerText.value = text
  }

  function useAiReply(text: string) {
    composerText.value = text
    useChatUiStore().closeAiPanel()
  }

  function setTyping(value: boolean) {
    isTyping.value = value
  }

  function subscribeThread(chatId: string): void {
    unsubscribeRealtime?.()
    const id = parseApiChatId(chatId)
    if (id == null) return

    const auth = useAuthStore()
    unsubscribeRealtime = subscribeChatChannel(id, {
      onNewMessage: (payload) => {
        if (payload.chatId !== id) return
        if (
          payload.sender_type === 'moderator' &&
          payload.sender_id != null &&
          auth.user?.id === payload.sender_id
        ) {
          return
        }
        const settings = useSettingsStore()
        const prevMax = maxMessageIdFromList()
        void (async () => {
          try {
            const newer = await msgApi.fetchMessages(id, { afterId: prevMax, limit: 30 })
            if (newer.length > 0) {
              messages.value = mergeFetchedNewerRows(messages.value, newer)
            } else {
              messages.value = appendRealtimeMessageIfNew(messages.value, payload)
            }
          } catch {
            messages.value = appendRealtimeMessageIfNew(messages.value, payload)
          }
          /** В открытом треде звук не играем (сообщение видно сразу). */
          if (payload.sender_type === 'client' && !isMutedUntilActive(threadMeta.value?.muted_until)) {
            vibrateIncoming(settings.vibration)
          }
          useInboxStore().scheduleInboxRefreshFromRealtime()
          scheduleSaveThreadCache()
        })()
      },
      onChatMessageUpdated: (payload: ChatMessageUpdatedPayload) => {
        if (payload.chatId !== id) {
          return
        }
        messages.value = applyChatMessageUpdatedPayload(messages.value, payload)
        scheduleSaveThreadCache()
      },
      onMessageRead: (payload) => {
        if (payload.chatId !== id || payload.messageIds.length === 0) return
        const readIds = new Set(payload.messageIds.map((n) => String(n)))
        messages.value = messages.value.map((m) =>
          readIds.has(m.id) && m.kind === 'incoming' ? { ...m, isRead: true } : m,
        )
        useInboxStore().scheduleInboxRefreshFromRealtime()
        scheduleSaveThreadCache()
      },
      onTyping: (payload) => {
        if (payload.sender_type === 'moderator') {
          setTyping(false)
          return
        }
        setTyping(true)
        if (typingClearTimer != null) window.clearTimeout(typingClearTimer)
        typingClearTimer = window.setTimeout(() => {
          typingClearTimer = null
          setTyping(false)
        }, 4000)
      },
      onChatAssigned: (payload) => {
        if (payload.chatId !== id) {
          return
        }
        void (async () => {
          try {
            const chat = await chatApi.fetchChat(id)
            if (activeChatId.value === chatId) {
              applyThreadMetaFromApiChat(chat, chatId)
            }
            patchCacheMetaFromApiChatIfPresent(chatId, chat)
            scheduleSaveThreadCache()
            useInboxStore().scheduleInboxRefreshFromRealtime()
          } catch {
            /* ignore */
          }
        })()
      },
    })
  }

  function leaveThread(): void {
    unsubscribeRealtime?.()
    unsubscribeRealtime = null
    if (typingClearTimer != null) {
      window.clearTimeout(typingClearTimer)
      typingClearTimer = null
    }
    if (readNearBottomTimer != null) {
      window.clearTimeout(readNearBottomTimer)
      readNearBottomTimer = null
    }
  }

  function onThreadScrolledNearBottom(): void {
    const chatId = activeChatId.value
    const id = parseApiChatId(chatId)
    if (id == null) return
    if (readNearBottomTimer != null) window.clearTimeout(readNearBottomTimer)
    readNearBottomTimer = window.setTimeout(() => {
      readNearBottomTimer = null
      void markAsRead(id)
    }, 400)
  }

  function clearThread() {
    if (activeChatId.value) {
      saveActiveThreadToCacheNow()
    }
    if (saveCacheDebounce != null) {
      clearTimeout(saveCacheDebounce)
      saveCacheDebounce = null
    }
    activeChatId.value = null
    leaveThread()
    readRequestInFlight = false
    lastSentReadWatermarkKey = null
    lastTypingNotifyAt = 0
    if (typingNotifyTimer != null) {
      window.clearTimeout(typingNotifyTimer)
      typingNotifyTimer = null
    }
    messages.value = []
    threadMeta.value = null
    aiContent.value = null
    oldestMessageId.value = null
    hasMoreOlder.value = true
    pendingReplyMarkup.value = []
    replyToMessageId.value = null
    cannedQuickReplies.value = []
    quickLinkPresets.value = []
  }

  async function loadAiPanelData(): Promise<void> {
    const chatUi = useChatUiStore()
    const id = parseApiChatId(activeChatId.value)
    if (id == null) {
      chatUi.setAiPanelContentReady()
      return
    }
    try {
      const [summary, sug] = await Promise.all([
        aiApi.fetchAiSummary(id).catch(() => null),
        aiApi.fetchAiSuggestions(id).catch(() => null),
      ])
      if (parseApiChatId(activeChatId.value) !== id) {
        return
      }
      const s = summary?.summary?.trim() || summary?.intent_tag?.trim() || 'Нет данных AI для этого чата.'
      const intent = summary?.intent_tag?.trim() || 'Общее обращение'
      aiContent.value = {
        summary: s,
        intentTag: intent,
        replies: (sug?.replies ?? []).map((r) => ({ id: r.id, text: r.text })),
        actionTitle: 'Продолжить диалог',
        actionDesc: '',
        actionButtonLabel: 'Ок',
      }
      if (threadMeta.value) {
        threadMeta.value = {
          ...threadMeta.value,
          aiSummaryBar: s.length > 120 ? `${s.slice(0, 120)}…` : s,
        }
      }
      scheduleSaveThreadCache()
    } finally {
      chatUi.setAiPanelContentReady()
    }
  }

  function openAiPanel() {
    const chatUi = useChatUiStore()
    chatUi.openAiPanel()
    void loadAiPanelData()
  }

  function closeAiPanel() {
    useChatUiStore().closeAiPanel()
  }

  function clearAiTimers() {
    useChatUiStore().clearAiTimers()
  }

  function mergeContextMessages(rows: import('../api/types').ApiMessageRow[]): void {
    const mapped = rows.map(mapApiMessageToChatMessage)
    const byId = new Map(messages.value.map((m) => [m.id, m]))
    for (const m of mapped) {
      byId.set(m.id, m)
    }
    messages.value = Array.from(byId.values()).sort((a, b) => Number(a.id) - Number(b.id))
    scheduleSaveThreadCache()
  }

  async function ensureReplyMessageLoaded(replyToId: number): Promise<boolean> {
    if (messages.value.some((m) => Number(m.id) === replyToId)) {
      return true
    }
    try {
      const rows = await msgApi.fetchMessageContext(replyToId)
      mergeContextMessages(rows)
      return true
    } catch {
      useUiStore().pushToast('Сообщение слишком далеко в истории или недоступно.', 'error')
      return false
    }
  }

  return {
    activeChatId,
    messages,
    composerText,
    replyToMessageId,
    isTyping,
    aiContent,
    loadingOlder,
    hasMoreOlder,
    threadMeta,
    threadSyncing,
    pendingReplyMarkup,
    cannedQuickReplies,
    quickLinkPresets,
    channelSource,
    fetchThread,
    refreshThread: () => {
      const id = activeChatId.value
      if (id) {
        void fetchThread(id, { force: true })
      }
    },
    loadOlderMessages,
    setComposerText,
    scheduleTypingNotify,
    setReplyTarget,
    clearReplyTarget,
    canSend,
    composerLocked,
    sendMessage,
    addReplyMarkupPreset,
    removeReplyMarkupPreset,
    sendWithAttachments,
    insertQuickReply,
    openAiPanel,
    closeAiPanel,
    useAiReply,
    setTyping,
    subscribeThread,
    leaveThread,
    onThreadScrolledNearBottom,
    clearAiTimers,
    clearThread,
    markAsRead,
    assignToMe,
    closeThread,
    reopenToWork,
    changeDepartment,
    mergeContextMessages,
    ensureReplyMessageLoaded,
  }
})
