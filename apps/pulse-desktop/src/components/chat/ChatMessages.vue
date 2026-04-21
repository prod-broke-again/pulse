<script setup lang="ts">
import {
  Check,
  CheckCheck,
  Loader2,
  ChevronUp,
  ChevronDown,
} from 'lucide-vue-next'
import {
  nextTick,
  onBeforeUnmount,
  onBeforeUpdate,
  onUpdated,
  ref,
  watch,
} from 'vue'
import type { MessageItem } from '../../types/chat'
import type { ApiMessage } from '../../types/dto/chat.types'
import { fetchMessageContext } from '../../api/messages'
import MessageAttachmentsGallery from './MessageAttachmentsGallery.vue'
import { formatMessagesPlainText } from '../../utils/telegramCopyFormat'

/** FAB / «рядом с низом» — широкий порог. */
const NEAR_BOTTOM_PX = 140
/** Следовать за новыми сообщениями и snap в onUpdated — только если реально у нижней границы. */
const STRICT_BOTTOM_PX = 24

const props = defineProps<{
  timeline: MessageItem[]
  isLoading?: boolean
  canLoadMore?: boolean
  peerName: string
  moderatorName?: string
  chatId?: number | null
  clientTyping?: boolean
}>()

const emit = defineEmits<{
  (e: 'load-more'): void
  (e: 'near-bottom'): void
  (e: 'reply', messageId: number): void
  (e: 'merge-context', rows: ApiMessage[]): void
  (e: 'toast', message: string): void
}>()

const scrollRoot = ref<HTMLElement | null>(null)
/** Inner column that grows with messages/media — observed for height changes. */
const threadContentRef = ref<HTMLElement | null>(null)
/** User wants to stay pinned to bottom; ResizeObserver only auto-scrolls when this is true. */
const stickToBottom = ref(true)
const pendingBelowCount = ref(0)
const isNearBottom = ref(true)
/** Distance from bottom before each DOM update — used to preserve reading position when height grows. */
const savedDistanceFromBottom = ref(0)
const lastTimelineFirstId = ref<number | null>(null)
const lastTimelineLen = ref(0)
const highlightMessageId = ref<number | null>(null)
let highlightTimer: ReturnType<typeof setTimeout> | null = null
/** Пока не завершён программный jump к сообщению — не трогаем scroll в onUpdated / watchers. */
const jumpTargetId = ref<number | null>(null)
/** После клика «к последним» — несколько циклов onUpdated прижимают низ, не восстанавливая якорь (иначе ломается smooth/auto скролл). */
const pendingJumpToBottomUntil = ref(0)

const selectionMode = ref(false)
const selectedIds = ref<number[]>([])
const anchorId = ref<number | null>(null)

const contextMenu = ref<{ x: number; y: number; messageId: number } | null>(null)
const contextMenuRef = ref<HTMLElement | null>(null)
const EST_CTX_MENU_W = 200
const EST_CTX_MENU_H = 100

let contextMenuDocCleanup: (() => void) | null = null

function closeContextMenu(): void {
  contextMenu.value = null
  contextMenuDocCleanup?.()
  contextMenuDocCleanup = null
}

function openContextMenu(e: MouseEvent, item: MessageItem): void {
  if (item.from === 'system') {
    return
  }
  e.preventDefault()
  closeContextMenu()
  const pad = 4
  let x = e.clientX
  let y = e.clientY
  const maxX = window.innerWidth - EST_CTX_MENU_W - pad
  const maxY = window.innerHeight - EST_CTX_MENU_H - pad
  x = Math.max(pad, Math.min(x, maxX))
  y = Math.max(pad, Math.min(y, maxY))
  contextMenu.value = { x, y, messageId: item.id }

  const onKey = (ke: KeyboardEvent): void => {
    if (ke.key === 'Escape') {
      closeContextMenu()
    }
  }
  const onDown = (me: MouseEvent): void => {
    const t = me.target as Node
    if (contextMenuRef.value?.contains(t)) {
      return
    }
    closeContextMenu()
  }
  const root = scrollRoot.value
  const onScroll = (): void => {
    closeContextMenu()
  }
  document.addEventListener('keydown', onKey)
  document.addEventListener('mousedown', onDown)
  if (root) {
    root.addEventListener('scroll', onScroll, { passive: true })
  }
  contextMenuDocCleanup = (): void => {
    document.removeEventListener('keydown', onKey)
    document.removeEventListener('mousedown', onDown)
    if (root) {
      root.removeEventListener('scroll', onScroll)
    }
  }
}

function contextMenuReply(): void {
  const id = contextMenu.value?.messageId
  closeContextMenu()
  if (id != null) {
    emit('reply', id)
  }
}

async function contextMenuCopyPlain(): Promise<void> {
  const id = contextMenu.value?.messageId
  if (id == null) {
    return
  }
  const text = formatMessagesPlainText(props.timeline, new Set([id]))
  closeContextMenu()
  if (text.trim() === '') {
    emit('toast', 'Нечего копировать')
    return
  }
  try {
    await navigator.clipboard.writeText(text)
    emit('toast', 'Скопировано')
  } catch {
    emit('toast', 'Не удалось скопировать')
  }
}

function moderatorLabel(): string {
  return props.moderatorName?.trim() || 'Модератор'
}

function moderatorBubbleLabel(item: MessageItem): string {
  if (item.from === 'moderator' && item.delivery_channel === 'telegram_app') {
    return 'Telegram'
  }
  return moderatorLabel()
}

function isMsgSelected(id: number): boolean {
  return selectedIds.value.includes(id)
}

function enterSelectionMode(): void {
  selectionMode.value = true
  selectedIds.value = []
  anchorId.value = null
}

function exitSelectionMode(): void {
  selectionMode.value = false
  selectedIds.value = []
  anchorId.value = null
}

function toggleSelectMessage(id: number, shiftKey: boolean): void {
  const row = props.timeline.find((m) => m.id === id)
  if (!row || row.from === 'system') {
    return
  }
  if (shiftKey && anchorId.value !== null) {
    const ids = props.timeline.map((m) => m.id)
    const ia = ids.indexOf(anchorId.value)
    const ib = ids.indexOf(id)
    if (ia < 0 || ib < 0) {
      return
    }
    const lo = Math.min(ia, ib)
    const hi = Math.max(ia, ib)
    const range: number[] = []
    for (let i = lo; i <= hi; i++) {
      const item = props.timeline[i]
      if (item && item.from !== 'system') {
        range.push(item.id)
      }
    }
    selectedIds.value = [...new Set([...selectedIds.value, ...range])]
    return
  }
  anchorId.value = id
  const s = new Set(selectedIds.value)
  if (s.has(id)) {
    s.delete(id)
  } else {
    s.add(id)
  }
  selectedIds.value = [...s]
}

function onContextMessage(e: MouseEvent, item: MessageItem): void {
  if (item.from === 'system') {
    return
  }
  openContextMenu(e, item)
}

function onBubbleSelectClick(item: MessageItem, e: MouseEvent): void {
  if (!selectionMode.value || item.from === 'system') {
    return
  }
  e.stopPropagation()
  toggleSelectMessage(item.id, e.shiftKey)
}

async function copySelectedToClipboard(): Promise<void> {
  if (selectedIds.value.length === 0) {
    return
  }
  const text = formatMessagesPlainText(props.timeline, new Set(selectedIds.value))
  if (text.trim() === '') {
    emit('toast', 'Нечего копировать')
    return
  }
  try {
    await navigator.clipboard.writeText(text)
    emit('toast', 'Скопировано')
    exitSelectionMode()
  } catch {
    emit('toast', 'Не удалось скопировать')
  }
}

function onGlobalCopy(e: ClipboardEvent): void {
  if (!selectionMode.value || selectedIds.value.length === 0) {
    return
  }
  e.preventDefault()
  const text = formatMessagesPlainText(props.timeline, new Set(selectedIds.value))
  if (text.trim() === '') {
    emit('toast', 'Нечего копировать')
    exitSelectionMode()
    return
  }
  e.clipboardData?.setData('text/plain', text)
  emit('toast', 'Скопировано')
  exitSelectionMode()
}

watch(selectionMode, (on) => {
  if (on) {
    document.addEventListener('copy', onGlobalCopy)
  } else {
    document.removeEventListener('copy', onGlobalCopy)
  }
})

let threadResizeObserver: ResizeObserver | null = null

function detachThreadResizeObserver(): void {
  if (threadResizeObserver) {
    threadResizeObserver.disconnect()
    threadResizeObserver = null
  }
}

function attachThreadResizeObserver(): void {
  detachThreadResizeObserver()
  if (typeof ResizeObserver === 'undefined') {
    return
  }
  const content = threadContentRef.value
  if (!content) {
    return
  }
  threadResizeObserver = new ResizeObserver(() => {
    if (jumpTargetId.value != null) {
      return
    }
    if (!stickToBottom.value) {
      return
    }
    scrollToBottom('auto')
    updateNearBottomFromScroll()
    if (props.chatId != null && isNearBottom.value) {
      pendingBelowCount.value = 0
      emit('near-bottom')
    }
  })
  threadResizeObserver.observe(content)
}

onBeforeUnmount(() => {
  document.removeEventListener('copy', onGlobalCopy)
  closeContextMenu()
  detachThreadResizeObserver()
})

function scrollToBottom(behavior: ScrollBehavior = 'auto'): void {
  const el = scrollRoot.value
  if (!el) {
    return
  }
  const maxTop = Math.max(0, el.scrollHeight - el.clientHeight)
  if (behavior === 'auto') {
    el.scrollTop = maxTop
  } else {
    el.scrollTo({ top: el.scrollHeight, behavior })
  }
}

/** После смены чата / первой отрисовки — один кадр + ResizeObserver добивает поздний layout. */
function scrollToBottomSettled(): void {
  scrollToBottom('auto')
  requestAnimationFrame(() => {
    scrollToBottom('auto')
  })
}

function updateNearBottomFromScroll(): void {
  const el = scrollRoot.value
  if (!el) {
    return
  }
  const d = el.scrollHeight - el.scrollTop - el.clientHeight
  isNearBottom.value = d < NEAR_BOTTOM_PX
  stickToBottom.value = d < STRICT_BOTTOM_PX
}

function onScroll(_e: Event): void {
  updateNearBottomFromScroll()
  if (props.chatId == null) {
    return
  }
  if (isNearBottom.value) {
    pendingBelowCount.value = 0
    emit('near-bottom')
  }
}

function onClickScrollDown(e: MouseEvent): void {
  e.preventDefault()
  e.stopPropagation()
  pendingJumpToBottomUntil.value = Date.now() + 550
  stickToBottom.value = true
  scrollToBottom('auto')
  pendingBelowCount.value = 0
  void nextTick(() => {
    scrollToBottom('auto')
    requestAnimationFrame(() => {
      scrollToBottom('auto')
      updateNearBottomFromScroll()
      if (props.chatId != null && isNearBottom.value) {
        emit('near-bottom')
      }
    })
  })
}

onBeforeUpdate(() => {
  const el = scrollRoot.value
  if (el) {
    savedDistanceFromBottom.value = el.scrollHeight - el.scrollTop - el.clientHeight
  }
})

onUpdated(() => {
  const el = scrollRoot.value
  if (!el || props.timeline.length === 0) {
    return
  }
  if (jumpTargetId.value != null) {
    const firstId = props.timeline[0]?.id ?? null
    const len = props.timeline.length
    lastTimelineFirstId.value = firstId
    lastTimelineLen.value = len
    updateNearBottomFromScroll()
    return
  }
  if (Date.now() < pendingJumpToBottomUntil.value) {
    const firstId = props.timeline[0]?.id ?? null
    const len = props.timeline.length
    el.scrollTop = Math.max(0, el.scrollHeight - el.clientHeight)
    pendingBelowCount.value = 0
    stickToBottom.value = true
    lastTimelineFirstId.value = firstId
    lastTimelineLen.value = len
    updateNearBottomFromScroll()
    return
  }
  const dist = savedDistanceFromBottom.value
  const firstId = props.timeline[0]?.id ?? null
  const len = props.timeline.length

  if (dist < STRICT_BOTTOM_PX) {
    el.scrollTop = el.scrollHeight - el.clientHeight
    pendingBelowCount.value = 0
    stickToBottom.value = true
  } else {
    el.scrollTop = el.scrollHeight - el.clientHeight - dist
    if (
      lastTimelineLen.value > 0
      && firstId === lastTimelineFirstId.value
      && len > lastTimelineLen.value
    ) {
      const added = props.timeline.slice(lastTimelineLen.value)
      for (const m of added) {
        if (m.from === 'client') {
          pendingBelowCount.value += 1
        }
      }
    }
  }

  lastTimelineFirstId.value = firstId
  lastTimelineLen.value = len
  updateNearBottomFromScroll()
})

watch(
  () => props.chatId,
  () => {
    closeContextMenu()
    exitSelectionMode()
    pendingBelowCount.value = 0
    isNearBottom.value = true
    stickToBottom.value = true
    pendingJumpToBottomUntil.value = 0
    lastTimelineFirstId.value = null
    lastTimelineLen.value = 0
  },
)

type ThreadSnapshot = {
  chatId: number | null | undefined
  len: number
  lastId: number | null
}

watch(
  () => {
    const len = props.timeline.length
    const last = len > 0 ? props.timeline[len - 1] : undefined
    const snap: ThreadSnapshot = {
      chatId: props.chatId,
      len,
      lastId: last ? last.id : null,
    }
    return snap
  },
  async (cur, prev) => {
    if (cur.len === 0) {
      return
    }
    await nextTick()
    const chatChanged = !prev || cur.chatId !== prev.chatId
    const becameVisible = (prev?.len ?? 0) === 0 && cur.len > 0

    if (chatChanged || becameVisible) {
      isNearBottom.value = true
      stickToBottom.value = true
      scrollToBottomSettled()
      void nextTick(() => {
        attachThreadResizeObserver()
      })
      return
    }

    if (jumpTargetId.value != null) {
      return
    }
    if (stickToBottom.value && cur.len > (prev?.len ?? 0)) {
      scrollToBottom('auto')
      requestAnimationFrame(() => scrollToBottom('auto'))
    }
  },
  { flush: 'post' },
)

watch(
  threadContentRef,
  () => {
    void nextTick(() => {
      attachThreadResizeObserver()
    })
  },
  { flush: 'post', immediate: true },
)

function tryScrollMessageIntoViewCentered(id: number): boolean {
  const root = scrollRoot.value
  if (!root) {
    return false
  }
  const el = root.querySelector(`[data-message-id="${id}"]`) as HTMLElement | null
  if (!el) {
    return false
  }
  const rootRect = root.getBoundingClientRect()
  const elRect = el.getBoundingClientRect()
  const delta
    = (elRect.top - rootRect.top) - (root.clientHeight / 2) + (elRect.height / 2)
  root.scrollTop += delta
  return true
}

async function scrollToMessageById(id: number): Promise<void> {
  jumpTargetId.value = id
  highlightMessageId.value = id
  if (highlightTimer != null) {
    clearTimeout(highlightTimer)
  }
  highlightTimer = setTimeout(() => {
    highlightMessageId.value = null
    highlightTimer = null
  }, 1500)

  await nextTick()
  await new Promise<void>((resolve) => {
    requestAnimationFrame(() => {
      requestAnimationFrame(() => resolve())
    })
  })

  let ok = tryScrollMessageIntoViewCentered(id)
  if (!ok) {
    await new Promise<void>((resolve) => {
      requestAnimationFrame(() => resolve())
    })
    ok = tryScrollMessageIntoViewCentered(id)
  }
  if (!ok) {
    await nextTick()
    tryScrollMessageIntoViewCentered(id)
  }

  jumpTargetId.value = null
  updateNearBottomFromScroll()
}

async function jumpToReply(replyToId: number | null | undefined): Promise<void> {
  if (replyToId == null || replyToId <= 0) {
    return
  }
  if (props.timeline.some((m) => m.id === replyToId)) {
    await scrollToMessageById(replyToId)
    return
  }
  try {
    const rows = await fetchMessageContext(replyToId)
    jumpTargetId.value = replyToId
    emit('merge-context', rows)
    await nextTick()
    await scrollToMessageById(replyToId)
  } catch {
    emit('toast', 'Сообщение слишком далеко в истории или недоступно.')
  }
}

defineExpose({
  scrollToMessageById,
  jumpToReply,
  enterSelectionMode,
  exitSelectionMode,
})
</script>

<template>
  <div class="relative flex min-h-0 flex-1 flex-col overflow-hidden">
    <div v-if="isLoading && timeline.length === 0" class="thread-messages">
      <div class="flex flex-1 flex-col gap-4 py-4">
        <div class="skeleton-chat-mock h-[50px] rounded-[var(--radius-md)]" />
        <div class="flex gap-3">
          <div class="skeleton-chat-mock h-10 w-10 rounded-full" />
          <div class="min-w-0 flex-1 space-y-2">
            <div class="skeleton-chat-mock h-3 w-4/5 rounded" />
            <div class="skeleton-chat-mock h-3 w-3/5 rounded" />
          </div>
        </div>
        <div class="flex justify-end gap-3">
          <div class="w-[60%] space-y-2">
            <div class="skeleton-chat-mock h-3 w-full rounded" />
            <div class="skeleton-chat-mock h-3 w-full rounded" />
          </div>
        </div>
      </div>
    </div>

    <div v-else-if="timeline.length === 0" class="thread-messages flex flex-col items-center justify-center">
      <p class="text-[13px]" style="color: var(--text-muted)">
        Пока нет сообщений в этом обращении
      </p>
    </div>

    <div
      v-else
      ref="scrollRoot"
      class="thread-messages custom-scroll h-full min-h-0 flex-1 overflow-y-auto"
      :class="{ 'thread-messages--fab-pad': timeline.length > 0 && !isNearBottom }"
      @scroll.passive="onScroll"
    >
      <div ref="threadContentRef" class="thread-messages-inner min-w-0">
      <div v-if="canLoadMore" class="thread-load-more">
        <button
          type="button"
          :disabled="isLoading"
          @click="emit('load-more')"
        >
          <Loader2 v-if="isLoading" class="h-3 w-3 animate-spin" />
          <ChevronUp v-else class="h-3 w-3" />
          Загрузить предыдущие сообщения
        </button>
      </div>

      <div class="msg-date-divider">
        Сегодня
      </div>

      <template v-for="item in timeline" :key="item.id">
        <div
          v-if="item.from === 'system'"
          class="msg-group system"
          :data-message-id="item.id"
        >
          <div class="msg-bubble">
            {{ item.text }}
          </div>
        </div>

        <div
          v-else-if="item.from === 'client'"
          class="msg-group user group"
          :data-message-id="item.id"
          :class="{
            'msg-jump-highlight': highlightMessageId === item.id,
            'msg--selected': selectionMode && isMsgSelected(item.id),
          }"
          @contextmenu.prevent="onContextMessage($event, item)"
        >
          <div class="flex w-full max-w-[520px] items-center justify-between self-start px-1">
            <div class="msg-sender m-0 p-0">
              {{ peerName }}
            </div>
            <button
              type="button"
              class="invisible border-none bg-transparent p-0 text-[11px] font-semibold text-[var(--text-primary)] opacity-0 underline-offset-2 transition-opacity duration-150 hover:text-[var(--text-secondary)] hover:underline group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100"
              @click.stop="emit('reply', item.id)"
            >
              Ответить
            </button>
          </div>
          <div class="msg-bubble" @click="onBubbleSelectClick(item, $event)">
            <div
              v-if="item.reply_to && (item.reply_to.id == null || item.reply_to.id <= 0)"
              class="mb-2 border-l-2 border-[var(--color-brand)] pl-2 text-[11px] leading-snug opacity-90"
            >
              {{ item.reply_to.text }}
            </div>
            <button
              v-else-if="item.reply_to"
              type="button"
              class="mb-2 w-full border-l-2 border-[var(--color-brand)] pl-2 text-left text-[11px] leading-snug opacity-90 transition hover:opacity-100"
              @click="jumpToReply(item.reply_to!.id)"
            >
              {{ item.reply_to.text }}
            </button>
            <template v-if="item.text">{{ item.text }}</template>
            <MessageAttachmentsGallery
              v-if="
                (item.attachments && item.attachments.length > 0)
                || (item.pending_attachments && item.pending_attachments.length > 0)
              "
              :files="item.attachments ?? []"
              :pending-slots="item.pending_attachments ?? []"
            />
            <div v-if="item.reply_markup && item.reply_markup.length" class="mt-2 flex flex-wrap gap-1.5">
              <a
                v-for="(btn, idx) in item.reply_markup"
                :key="`rm-c-${item.id}-${idx}`"
                :href="btn.url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex max-w-full rounded-lg border px-2 py-1 text-[11px] font-semibold underline-offset-2 hover:underline"
                style="border-color: var(--border-light); color: var(--color-brand-200)"
                @click.stop
              >{{ btn.text }}</a>
            </div>
            </div>
          <div class="msg-time inline-flex items-center gap-1">
            {{ item.time }}
          </div>
        </div>

        <div
          v-else-if="item.from === 'moderator'"
          class="msg-group mod group"
          :data-message-id="item.id"
          :class="{
            'msg-jump-highlight': highlightMessageId === item.id,
            'msg--selected': selectionMode && isMsgSelected(item.id),
          }"
          @contextmenu.prevent="onContextMessage($event, item)"
        >
          <div class="flex w-full max-w-[520px] items-center justify-between self-end px-1">
            <button
              type="button"
              class="invisible border-none bg-transparent p-0 text-[11px] font-semibold text-[var(--text-primary)] opacity-0 underline-offset-2 transition-opacity duration-150 hover:text-[var(--text-secondary)] hover:underline group-hover:visible group-hover:opacity-100 group-focus-within:visible group-focus-within:opacity-100"
              @click.stop="emit('reply', item.id)"
            >
              Ответить
            </button>
            <div class="msg-sender m-0 p-0 text-right">
              {{ moderatorBubbleLabel(item) }}
            </div>
          </div>
          <div
            class="msg-bubble"
            :class="item.from === 'moderator' && item.pending ? 'opacity-90' : ''"
            @click="onBubbleSelectClick(item, $event)"
          >
            <div
              v-if="item.reply_to && (item.reply_to.id == null || item.reply_to.id <= 0)"
              class="mb-2 border-l-2 border-white/40 pl-2 text-[11px] leading-snug text-white/90"
            >
              {{ item.reply_to.text }}
            </div>
            <button
              v-else-if="item.reply_to"
              type="button"
              class="mb-2 w-full border-l-2 border-white/40 pl-2 text-left text-[11px] leading-snug text-white/90 transition hover:opacity-100"
              @click="jumpToReply(item.reply_to!.id)"
            >
              {{ item.reply_to.text }}
            </button>
            <template v-if="item.text">{{ item.text }}</template>
            <MessageAttachmentsGallery
              v-if="
                (item.attachments && item.attachments.length > 0)
                || (item.pending_attachments && item.pending_attachments.length > 0)
              "
              :files="item.attachments ?? []"
              :pending-slots="item.pending_attachments ?? []"
            />
            <div v-if="item.reply_markup && item.reply_markup.length" class="mt-2 flex flex-wrap gap-1.5">
              <a
                v-for="(btn, idx) in item.reply_markup"
                :key="`rm-m-${item.id}-${idx}`"
                :href="btn.url"
                target="_blank"
                rel="noopener noreferrer"
                class="inline-flex max-w-full rounded-lg border border-white/25 px-2 py-1 text-[11px] font-semibold text-white underline-offset-2 hover:underline"
                @click.stop
              >{{ btn.text }}</a>
            </div>
            </div>
          <div class="msg-time inline-flex items-center gap-1">
            <Loader2
              v-if="item.from === 'moderator' && item.pending"
              class="h-3 w-3 shrink-0 animate-spin"
              style="color: var(--color-brand-200)"
            />
            {{ item.time }}
            <CheckCheck
              v-if="item.from === 'moderator' && !item.pending && item.is_read === true"
              class="h-3 w-3 shrink-0"
              style="color: var(--color-brand-200)"
            />
            <Check
              v-else-if="item.from === 'moderator' && !item.pending"
              class="h-3 w-3 shrink-0 opacity-70"
              style="color: var(--color-brand-200)"
            />
          </div>
        </div>
      </template>
      <p
        v-if="clientTyping"
        class="px-1 pt-2 text-[11px] font-medium"
        style="color: var(--color-brand-200)"
      >
        Клиент печатает…
      </p>
      </div>
    </div>

    <div
      v-if="selectionMode"
      class="absolute bottom-0 left-0 right-0 z-30 flex items-center justify-center gap-2 border-t px-3 py-2.5 shadow-[0_-4px_12px_rgba(0,0,0,0.08)]"
      style="background: var(--bg-inbox); border-color: var(--border-light)"
    >
      <span class="mr-auto text-xs" style="color: var(--text-muted)">Выбор сообщений · Shift — диапазон</span>
      <button
        type="button"
        class="btn btn-secondary text-sm"
        @click="exitSelectionMode"
      >
        Отмена
      </button>
      <button
        type="button"
        class="btn btn-primary text-sm"
        :disabled="selectedIds.length === 0"
        @click="copySelectedToClipboard"
      >
        Копировать ({{ selectedIds.length }})
      </button>
    </div>

    <button
      v-if="timeline.length > 0 && !isNearBottom"
      type="button"
      class="chat-fab-scroll no-drag-region pointer-events-auto absolute right-4 z-40 flex h-11 w-11 items-center justify-center rounded-full shadow-md transition hover:scale-105"
      :class="selectionMode ? 'bottom-14' : 'bottom-5'"
      style="background: var(--color-brand-200); color: white; box-shadow: var(--shadow-md)"
      title="К последним сообщениям"
      aria-label="К последним сообщениям"
      @click="onClickScrollDown"
    >
      <ChevronDown class="h-5 w-5" />
      <span
        v-if="pendingBelowCount > 0"
        class="absolute -right-1 -top-1 flex h-5 min-h-5 min-w-[1.25rem] shrink-0 items-center justify-center rounded-full px-1.5 text-[10px] font-bold tabular-nums leading-none"
        style="background: #ef4444; color: #fff; line-height: 1"
      >
        {{ pendingBelowCount > 99 ? '99+' : pendingBelowCount }}
      </span>
    </button>

    <Teleport to="body">
      <div
        v-if="contextMenu"
        ref="contextMenuRef"
        class="pointer-events-auto fixed z-[100] min-w-[180px] overflow-hidden rounded-lg border py-1 text-[13px] shadow-lg"
        style="background: var(--bg-inbox); border-color: var(--border-light); color: var(--text-primary)"
        :style="{ left: `${contextMenu.x}px`, top: `${contextMenu.y}px` }"
        role="menu"
        @click.stop
      >
        <button
          type="button"
          class="block w-full cursor-pointer px-3 py-2.5 text-left text-[13px] hover:bg-[var(--bg-card-hover)]"
          role="menuitem"
          @click="contextMenuReply"
        >
          Ответить
        </button>
        <button
          type="button"
          class="block w-full cursor-pointer px-3 py-2.5 text-left text-[13px] hover:bg-[var(--bg-card-hover)]"
          role="menuitem"
          @click="contextMenuCopyPlain"
        >
          Копировать
        </button>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.skeleton-chat-mock {
  background: linear-gradient(
    90deg,
    var(--border-light) 25%,
    var(--bg-card-hover) 50%,
    var(--border-light) 75%
  );
  background-size: 200% 100%;
  animation: shimmer-chat 1.5s infinite;
}
@keyframes shimmer-chat {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}

.msg-jump-highlight {
  animation: reply-jump-fade 1.5s ease-out forwards;
}

@keyframes reply-jump-fade {
  0% {
    box-shadow: 0 0 0 3px var(--reply-jump-highlight);
    background-color: var(--reply-jump-highlight);
  }
  100% {
    box-shadow: 0 0 0 0 transparent;
    background-color: transparent;
  }
}

.msg--selected .msg-bubble {
  outline: 2px solid var(--color-brand-200);
  outline-offset: 2px;
}

/* extra bottom space so FAB does not cover bubbles when scrolled up */
.thread-messages.thread-messages--fab-pad {
  padding-bottom: 76px;
}

/* FAB must not participate in overflow anchoring */
.chat-fab-scroll {
  overflow-anchor: none;
}
</style>
