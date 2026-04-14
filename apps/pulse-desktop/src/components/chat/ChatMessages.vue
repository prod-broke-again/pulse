<script setup lang="ts">
import {
  Check,
  CheckCheck,
  Loader2,
  ChevronUp,
  ChevronDown,
  Reply,
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
import { formatMessagesTelegramStyle } from '../../utils/telegramCopyFormat'

const NEAR_BOTTOM_PX = 140

const props = defineProps<{
  timeline: MessageItem[]
  isLoading?: boolean
  canLoadMore?: boolean
  peerName: string
  /** Имя модератора для копирования в стиле Telegram */
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
const pendingBelowCount = ref(0)
const isNearBottom = ref(true)
/** Distance from bottom before each DOM update — used to preserve reading position when height grows. */
const savedDistanceFromBottom = ref(0)
const lastTimelineFirstId = ref<number | null>(null)
const lastTimelineLen = ref(0)
const highlightMessageId = ref<number | null>(null)
let highlightTimer: ReturnType<typeof setTimeout> | null = null

const selectionMode = ref(false)
const selectedIds = ref<number[]>([])
const anchorId = ref<number | null>(null)

function moderatorLabel(): string {
  return props.moderatorName?.trim() || 'Модератор'
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

function onContextMessage(item: MessageItem): void {
  if (item.from === 'system') {
    return
  }
  enterSelectionMode()
  selectedIds.value = [item.id]
  anchorId.value = item.id
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
  const text = formatMessagesTelegramStyle(
    props.timeline,
    new Set(selectedIds.value),
    props.peerName,
    moderatorLabel(),
  )
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
  const text = formatMessagesTelegramStyle(
    props.timeline,
    new Set(selectedIds.value),
    props.peerName,
    moderatorLabel(),
  )
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

onBeforeUnmount(() => {
  document.removeEventListener('copy', onGlobalCopy)
})

function scrollToBottom(behavior: ScrollBehavior = 'auto'): void {
  const el = scrollRoot.value
  if (!el) {
    return
  }
  el.scrollTo({ top: el.scrollHeight - el.clientHeight, behavior })
}

function updateNearBottomFromScroll(): void {
  const el = scrollRoot.value
  if (!el) {
    return
  }
  const d = el.scrollHeight - el.scrollTop - el.clientHeight
  isNearBottom.value = d < NEAR_BOTTOM_PX
}

function onScroll(e: Event): void {
  const el = e.target as HTMLElement
  updateNearBottomFromScroll()
  if (props.chatId == null) {
    return
  }
  if (isNearBottom.value) {
    pendingBelowCount.value = 0
    emit('near-bottom')
  }
}

function onClickScrollDown(): void {
  scrollToBottom('smooth')
  pendingBelowCount.value = 0
  isNearBottom.value = true
  if (props.chatId != null) {
    emit('near-bottom')
  }
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
  const dist = savedDistanceFromBottom.value
  const firstId = props.timeline[0]?.id ?? null
  const len = props.timeline.length

  if (dist < NEAR_BOTTOM_PX) {
    el.scrollTop = el.scrollHeight - el.clientHeight
    pendingBelowCount.value = 0
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
    exitSelectionMode()
    pendingBelowCount.value = 0
    isNearBottom.value = true
    lastTimelineFirstId.value = null
    lastTimelineLen.value = 0
  },
)

watch(
  () => props.timeline.length,
  async () => {
    if (props.timeline.length === 0) {
      return
    }
    await nextTick()
    if (isNearBottom.value) {
      scrollToBottom('auto')
    }
  },
)

function scrollToMessageById(id: number): void {
  highlightMessageId.value = id
  if (highlightTimer != null) {
    clearTimeout(highlightTimer)
  }
  highlightTimer = setTimeout(() => {
    highlightMessageId.value = null
    highlightTimer = null
  }, 1500)
  void nextTick(() => {
    const root = scrollRoot.value
    const el = root?.querySelector(`[data-message-id="${id}"]`)
    el?.scrollIntoView({ block: 'center', behavior: 'smooth' })
  })
}

async function jumpToReply(replyToId: number | null | undefined): Promise<void> {
  if (replyToId == null || replyToId <= 0) {
    return
  }
  if (props.timeline.some((m) => m.id === replyToId)) {
    await nextTick()
    scrollToMessageById(replyToId)
    return
  }
  try {
    const rows = await fetchMessageContext(replyToId)
    emit('merge-context', rows)
    await nextTick()
    await nextTick()
    scrollToMessageById(replyToId)
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
      class="thread-messages custom-scroll"
      @scroll.passive="onScroll"
    >
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
          class="msg-group user"
          :data-message-id="item.id"
          :class="{
            'msg-jump-highlight': highlightMessageId === item.id,
            'msg--selected': selectionMode && isMsgSelected(item.id),
          }"
          @contextmenu.prevent="onContextMessage(item)"
        >
          <div class="msg-sender">
            {{ peerName }}
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
            <button
              type="button"
              class="rounded p-0.5 opacity-50 hover:opacity-100"
              title="Ответить"
              @click.stop="emit('reply', item.id)"
            >
              <Reply class="h-3 w-3" />
            </button>
            {{ item.time }}
          </div>
        </div>

        <div
          v-else-if="item.from === 'moderator'"
          class="msg-group mod"
          :data-message-id="item.id"
          :class="{
            'msg-jump-highlight': highlightMessageId === item.id,
            'msg--selected': selectionMode && isMsgSelected(item.id),
          }"
          @contextmenu.prevent="onContextMessage(item)"
        >
          <div class="msg-sender">
            Модератор
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
            <button
              type="button"
              class="rounded p-0.5 opacity-50 hover:opacity-100"
              title="Ответить"
              @click.stop="emit('reply', item.id)"
            >
              <Reply class="h-3 w-3 text-white/80" />
            </button>
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
      class="absolute right-4 z-20 flex h-11 w-11 items-center justify-center rounded-full shadow-md transition hover:scale-105"
      :class="selectionMode ? 'bottom-28' : 'bottom-24'"
      style="background: var(--color-brand-200); color: white; box-shadow: var(--shadow-md)"
      title="К последним сообщениям"
      aria-label="К последним сообщениям"
      @click="onClickScrollDown"
    >
      <ChevronDown class="h-5 w-5" />
      <span
        v-if="pendingBelowCount > 0"
        class="absolute -right-1 -top-1 flex min-w-[1.25rem] items-center justify-center rounded-full px-1 text-[10px] font-bold leading-none"
        style="background: #ef4444; color: #fff"
      >
        {{ pendingBelowCount > 99 ? '99+' : pendingBelowCount }}
      </span>
    </button>
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
</style>
