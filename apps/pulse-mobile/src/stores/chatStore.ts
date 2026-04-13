import { defineStore } from 'pinia'
import { computed, ref, watch } from 'vue'
import * as aiApi from '../api/aiRepository'
import * as cannedApi from '../api/cannedResponseRepository'
import * as chatApi from '../api/chatRepository'
import * as quickLinkApi from '../api/quickLinkRepository'
import * as msgApi from '../api/messageRepository'
import * as uploadApi from '../api/uploadRepository'
import { playIncomingTone, vibrateIncoming } from '../lib/notificationFeedback'
import { subscribeChatChannel } from '../lib/realtime'
import { mapApiChatToPreview } from '../mappers/chatMapper'
import { mapApiMessageToChatMessage, mapRealtimePayloadToChatMessage } from '../mappers/messageMapper'
import { useAuthStore } from './authStore'
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

export const useChatStore = defineStore('chat', () => {
  const activeChatId = ref<string | null>(null)
  const messages = ref<ChatMessage[]>([])
  const composerText = ref('')
  const isTyping = ref(false)
  const aiProcessing = ref(false)
  const aiContent = ref<AiPanelContent | null>(null)
  const overlayVisible = ref(false)
  const panelOpen = ref(false)
  const loadingOlder = ref(false)
  const hasMoreOlder = ref(true)
  const oldestMessageId = ref<number | null>(null)
  const threadMeta = ref<ChatThreadMeta | null>(null)
  const pendingReplyMarkup = ref<ReplyMarkupButton[]>([])
  /** Canned responses for quick-reply chips (from API; ComposerBar falls back if empty). */
  const cannedQuickReplies = ref<Array<{ label: string; text: string }>>([])
  /** Quick link buttons for Zap menu (from API). */
  const quickLinkPresets = ref<ReplyMarkupButton[]>([])

  let aiTimers: number[] = []
  let unsubscribeRealtime: (() => void) | null = null
  let typingClearTimer: number | null = null
  let readNearBottomTimer: number | null = null
  let readRequestInFlight = false
  let lastSentReadWatermarkKey: string | null = null

  watch(activeChatId, () => {
    readRequestInFlight = false
    lastSentReadWatermarkKey = null
  })

  const channelSource = computed((): ChannelSource | null => threadMeta.value?.channel ?? null)

  function clearAiTimers() {
    aiTimers.forEach((t) => window.clearTimeout(t))
    aiTimers = []
  }

  function maxMessageIdFromList(): number {
    let max = 0
    for (const m of messages.value) {
      const n = Number(m.id)
      if (Number.isFinite(n) && n > max) max = n
    }
    return max
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
      departmentLabel: preview.department,
      sourceId: chat.source_id ?? null,
      departmentId: chat.department_id ?? chat.department?.id ?? null,
      aiSummaryBar: prev?.aiSummaryBar ?? 'Нет данных AI для этого чата.',
      assignedToUserId: assigneeUserId(chat),
    }
  }

  async function assignToMe(): Promise<void> {
    const chatId = activeChatId.value
    if (!chatId) return
    const id = Number(chatId)
    if (!Number.isFinite(id)) return
    const ui = useUiStore()
    try {
      const chat = await chatApi.assignMe(id)
      applyThreadMetaFromApiChat(chat, chatId)
      await useInboxStore().loadInbox()
      ui.pushToast('Чат назначен на вас', 'success')
    } catch {
      ui.pushToast('Не удалось назначить чат', 'error')
    }
  }

  async function closeThread(): Promise<void> {
    const chatId = activeChatId.value
    if (!chatId) return
    const id = Number(chatId)
    if (!Number.isFinite(id)) return
    const ui = useUiStore()
    try {
      const chat = await chatApi.closeChat(id)
      applyThreadMetaFromApiChat(chat, chatId)
      await useInboxStore().loadInbox()
      ui.pushToast('Чат закрыт', 'success')
    } catch {
      ui.pushToast('Не удалось закрыть чат', 'error')
    }
  }

  async function changeDepartment(departmentId: number): Promise<void> {
    const chatId = activeChatId.value
    if (!chatId) return
    const id = Number(chatId)
    if (!Number.isFinite(id)) return
    const ui = useUiStore()
    try {
      const chat = await chatApi.changeChatDepartment(id, departmentId)
      applyThreadMetaFromApiChat(chat, chatId)
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

  async function fetchThread(chatId: string): Promise<void> {
    const id = Number(chatId)
    if (!Number.isFinite(id)) return

    activeChatId.value = chatId
    loadingOlder.value = true
    hasMoreOlder.value = true
    pendingReplyMarkup.value = []
    cannedQuickReplies.value = []
    quickLinkPresets.value = []

    try {
      const chat = await chatApi.fetchChat(id)
      const [rows, aiSummary, cannedRows, linkRows] = await Promise.all([
        msgApi.fetchMessages(id, { limit: 50 }),
        aiApi.fetchAiSummary(id).catch(() => null),
        cannedApi
          .fetchCannedResponses(
            chat.source_id != null ? { source_id: chat.source_id } : undefined,
          )
          .catch(() => []),
        quickLinkApi
          .fetchQuickLinks(
            chat.source_id != null ? { source_id: chat.source_id } : undefined,
          )
          .catch(() => []),
      ])

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
        departmentLabel: preview.department,
        sourceId: chat.source_id ?? null,
        departmentId: chat.department_id ?? chat.department?.id ?? null,
        aiSummaryBar:
          aiSummary?.summary?.trim() ||
          aiSummary?.intent_tag?.trim() ||
          'Нет данных AI для этого чата.',
        assignedToUserId: assigneeUserId(chat),
      }

      messages.value = rows.map(mapApiMessageToChatMessage)
      oldestMessageId.value = rows.length > 0 ? rows[0]!.id : null
      if (rows.length < 50) hasMoreOlder.value = false

      aiContent.value = {
        summary: aiSummary?.summary ?? threadMeta.value.aiSummaryBar,
        intentTag: aiSummary?.intent_tag ?? 'Общее обращение',
        replies: [],
        actionTitle: 'Продолжить диалог',
        actionDesc: '',
        actionButtonLabel: 'Ок',
      }

      try {
        const sug = await aiApi.fetchAiSuggestions(id)
        if (aiContent.value && sug.replies?.length) {
          aiContent.value = {
            ...aiContent.value,
            replies: sug.replies.map((r) => ({ id: r.id, text: r.text })),
          }
        }
      } catch {
        /* optional */
      }

      await markAsRead(id)
    } finally {
      loadingOlder.value = false
    }
  }

  async function loadOlderMessages(): Promise<void> {
    const chatId = activeChatId.value
    const before = oldestMessageId.value
    if (!chatId || before == null || loadingOlder.value || !hasMoreOlder.value) return
    const id = Number(chatId)
    if (!Number.isFinite(id)) return

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
  }

  function setComposerText(value: string) {
    composerText.value = value
  }

  const canSend = computed(
    () =>
      composerText.value.trim().length > 0 || pendingReplyMarkup.value.length > 0,
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
    const text = composerText.value.trim()
    const chatId = activeChatId.value
    const markupSnapshot = [...pendingReplyMarkup.value]
    if ((!text && markupSnapshot.length === 0) || !chatId) return
    const id = Number(chatId)
    if (!Number.isFinite(id)) return

    const clientMessageId = crypto.randomUUID?.() ?? `cm-${Date.now()}`
    const now = new Date()
    const time = `${pad2(now.getHours())}:${pad2(now.getMinutes())}`
    const tempId = `temp-${clientMessageId}`
    messages.value = [
      ...messages.value,
      {
        id: tempId,
        kind: 'outgoing',
        text: text || '\u00a0',
        time,
        clientMessageId,
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
      const data = await msgApi.sendMessage(id, payload)
      const mapped = mapApiMessageToChatMessage(data)
      messages.value = messages.value.map((m) =>
        m.clientMessageId === clientMessageId ? mapped : m,
      )
      pendingReplyMarkup.value = []
      useUiStore().pushToast('Сообщение отправлено', 'success')
      await markAsRead(id)
    } catch {
      messages.value = messages.value.filter((m) => m.id !== tempId && m.clientMessageId !== clientMessageId)
      useUiStore().pushToast('Не удалось отправить', 'error')
    }
  }

  async function sendWithAttachments(files: File[]) {
    const chatId = activeChatId.value
    if (!chatId || files.length === 0) return
    const id = Number(chatId)
    if (!Number.isFinite(id)) return

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
      await fetchThread(chatId)
      useUiStore().pushToast('Файл отправлен', 'success')
    } catch {
      useUiStore().pushToast('Не удалось отправить файл', 'error')
    }
  }

  function insertQuickReply(text: string) {
    composerText.value = text
  }

  function openAiPanel() {
    clearAiTimers()
    overlayVisible.value = true
    aiProcessing.value = true
    const t1 = window.setTimeout(() => {
      panelOpen.value = true
    }, 10)
    const t2 = window.setTimeout(() => {
      aiProcessing.value = false
    }, 1200)
    aiTimers.push(t1, t2)
  }

  function closeAiPanel() {
    panelOpen.value = false
    const t = window.setTimeout(() => {
      overlayVisible.value = false
      aiProcessing.value = false
    }, 350)
    aiTimers.push(t)
  }

  function useAiReply(text: string) {
    composerText.value = text
    closeAiPanel()
  }

  function setTyping(value: boolean) {
    isTyping.value = value
  }

  function subscribeThread(chatId: string): void {
    unsubscribeRealtime?.()
    const id = Number(chatId)
    if (!Number.isFinite(id)) return

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
              const byId = new Map(messages.value.map((m) => [m.id, m]))
              for (const row of newer) {
                byId.set(String(row.id), mapApiMessageToChatMessage(row))
              }
              messages.value = Array.from(byId.values()).sort((a, b) => {
                const na = Number(a.id)
                const nb = Number(b.id)
                if (Number.isFinite(na) && Number.isFinite(nb)) {
                  return na - nb
                }
                return String(a.id).localeCompare(String(b.id))
              })
            } else {
              const mapped = mapRealtimePayloadToChatMessage(payload)
              if (messages.value.some((m) => m.id === mapped.id)) return
              messages.value = [...messages.value, mapped]
            }
          } catch {
            const mapped = mapRealtimePayloadToChatMessage(payload)
            if (messages.value.some((m) => m.id === mapped.id)) return
            messages.value = [...messages.value, mapped]
          }
          if (payload.sender_type === 'client') {
            playIncomingTone(settings.sound)
            vibrateIncoming(settings.vibration)
          }
          useInboxStore().scheduleInboxRefreshFromRealtime()
        })()
      },
      onMessageRead: (payload) => {
        if (payload.chatId !== id || payload.messageIds.length === 0) return
        const readIds = new Set(payload.messageIds.map((n) => String(n)))
        messages.value = messages.value.map((m) =>
          readIds.has(m.id) && m.kind === 'incoming' ? { ...m, isRead: true } : m,
        )
        useInboxStore().scheduleInboxRefreshFromRealtime()
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
    if (!chatId) return
    const id = Number(chatId)
    if (!Number.isFinite(id)) return
    if (readNearBottomTimer != null) window.clearTimeout(readNearBottomTimer)
    readNearBottomTimer = window.setTimeout(() => {
      readNearBottomTimer = null
      void markAsRead(id)
    }, 400)
  }

  function clearThread() {
    leaveThread()
    readRequestInFlight = false
    lastSentReadWatermarkKey = null
    messages.value = []
    threadMeta.value = null
    oldestMessageId.value = null
    hasMoreOlder.value = true
    pendingReplyMarkup.value = []
    cannedQuickReplies.value = []
    quickLinkPresets.value = []
  }

  return {
    activeChatId,
    messages,
    composerText,
    isTyping,
    aiProcessing,
    aiContent,
    overlayVisible,
    panelOpen,
    loadingOlder,
    hasMoreOlder,
    threadMeta,
    pendingReplyMarkup,
    cannedQuickReplies,
    quickLinkPresets,
    channelSource,
    fetchThread,
    loadOlderMessages,
    setComposerText,
    canSend,
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
    changeDepartment,
  }
})
