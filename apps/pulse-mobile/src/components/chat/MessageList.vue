<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { nextTick, onBeforeUnmount, onBeforeUpdate, onUpdated, ref, watch } from 'vue'
import type { ChatMessage } from '../../types/chat'
import { useAuthStore } from '../../stores/authStore'
import { useChatStore } from '../../stores/chatStore'
import { useUiStore } from '../../stores/uiStore'
import { formatChatMessagesTelegramStyle } from '../../utils/telegramCopyFormat'
import MessageBubble from './MessageBubble.vue'

const NEAR_BOTTOM_PX = 140
const STRICT_BOTTOM_PX = 24

const props = defineProps<{
  messages: ChatMessage[]
}>()

const chat = useChatStore()
const auth = useAuthStore()
const { isTyping } = storeToRefs(chat)

const selectionMode = ref(false)
const selectedIds = ref<string[]>([])
const selectAnchorId = ref<string | null>(null)
let longPressTimer: ReturnType<typeof setTimeout> | null = null

function exitSelectionMode(): void {
  selectionMode.value = false
  selectedIds.value = []
  selectAnchorId.value = null
}

function enterSelectionMode(): void {
  selectionMode.value = true
  selectedIds.value = []
  selectAnchorId.value = null
}

function isSelected(m: ChatMessage): boolean {
  return selectedIds.value.includes(String(m.id))
}

function toggleSelect(m: ChatMessage, shiftKey: boolean): void {
  if (m.kind === 'system') return
  const id = String(m.id)
  if (shiftKey && selectAnchorId.value !== null) {
    const ids = props.messages.map((x) => String(x.id))
    const ia = ids.indexOf(selectAnchorId.value)
    const ib = ids.indexOf(id)
    if (ia < 0 || ib < 0) return
    const lo = Math.min(ia, ib)
    const hi = Math.max(ia, ib)
    const range: string[] = []
    for (let i = lo; i <= hi; i++) {
      const row = props.messages[i]
      if (row && row.kind !== 'system') {
        range.push(String(row.id))
      }
    }
    selectedIds.value = [...new Set([...selectedIds.value, ...range])]
    return
  }
  selectAnchorId.value = id
  const s = new Set(selectedIds.value)
  if (s.has(id)) s.delete(id)
  else s.add(id)
  selectedIds.value = [...s]
}

function onRowClick(m: ChatMessage, e: MouseEvent): void {
  if (!selectionMode.value) return
  const t = e.target as HTMLElement
  if (t.closest('a') || t.closest('button')) return
  if (m.kind === 'system') return
  toggleSelect(m, e.shiftKey)
}

function onPointerDownRow(m: ChatMessage, _e: PointerEvent): void {
  if (m.kind === 'system' || selectionMode.value) return
  if (longPressTimer != null) {
    window.clearTimeout(longPressTimer)
    longPressTimer = null
  }
  const id = String(m.id)
  longPressTimer = window.setTimeout(() => {
    longPressTimer = null
    enterSelectionMode()
    selectedIds.value = [id]
    selectAnchorId.value = id
  }, 650)
}

function onPointerUpRow(): void {
  if (longPressTimer != null) {
    window.clearTimeout(longPressTimer)
    longPressTimer = null
  }
}

async function copySelectedMobile(): Promise<void> {
  if (selectedIds.value.length === 0) return
  const meta = chat.threadMeta
  const peer = meta?.userName?.trim() || 'Клиент'
  const mod = auth.user?.name?.trim() || 'Модератор'
  const text = formatChatMessagesTelegramStyle(props.messages, new Set(selectedIds.value), peer, mod)
  try {
    await navigator.clipboard.writeText(text)
    useUiStore().pushToast('Скопировано', 'success')
    exitSelectionMode()
  } catch {
    useUiStore().pushToast('Не удалось скопировать', 'error')
  }
}

function onGlobalCopy(e: ClipboardEvent): void {
  if (!selectionMode.value || selectedIds.value.length === 0) return
  e.preventDefault()
  const meta = chat.threadMeta
  const peer = meta?.userName?.trim() || 'Клиент'
  const mod = auth.user?.name?.trim() || 'Модератор'
  const text = formatChatMessagesTelegramStyle(props.messages, new Set(selectedIds.value), peer, mod)
  e.clipboardData?.setData('text/plain', text)
  useUiStore().pushToast('Скопировано', 'success')
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
  if (longPressTimer != null) {
    window.clearTimeout(longPressTimer)
    longPressTimer = null
  }
})

function onReplyToMessage(messageId: string) {
  const n = Number(messageId)
  if (Number.isFinite(n) && n > 0) {
    chat.setReplyTarget(n)
  }
}

async function onJumpReply(messageId: number) {
  jumpTargetId.value = messageId
  const ok = await chat.ensureReplyMessageLoaded(messageId)
  if (ok) {
    await nextTick()
    await scrollToMessageById(messageId)
  } else {
    jumpTargetId.value = null
  }
}

const root = ref<HTMLElement | null>(null)
const pendingBelowCount = ref(0)
const isNearBottom = ref(true)
/** Следовать за хвостом при новых сообщениях / typing — только у самого низа. */
const followTail = ref(true)
const savedDistanceFromBottom = ref(0)
const lastTimelineFirstId = ref<string | null>(null)
const lastTimelineLen = ref(0)
const highlightMessageId = ref<string | null>(null)
let highlightTimer: number | null = null
const jumpTargetId = ref<number | null>(null)
const pendingJumpToBottomUntil = ref(0)

function scrollToEnd(behavior: ScrollBehavior = 'auto') {
  const el = root.value
  if (!el) {
    return
  }
  el.scrollTo({ top: el.scrollHeight - el.clientHeight, behavior })
}

function updateNearBottomFromScroll(): void {
  const el = root.value
  if (!el) {
    return
  }
  const d = el.scrollHeight - el.scrollTop - el.clientHeight
  isNearBottom.value = d < NEAR_BOTTOM_PX
  followTail.value = d < STRICT_BOTTOM_PX
}

function onScroll(e: Event) {
  const el = e.target as HTMLElement
  if (el.scrollTop < 120) {
    void chat.loadOlderMessages()
  }
  updateNearBottomFromScroll()
  if (isNearBottom.value) {
    pendingBelowCount.value = 0
    chat.onThreadScrolledNearBottom()
  }
}

function onClickScrollDown() {
  pendingJumpToBottomUntil.value = Date.now() + 550
  followTail.value = true
  scrollToEnd('auto')
  pendingBelowCount.value = 0
  void nextTick(() => {
    scrollToEnd('auto')
    requestAnimationFrame(() => {
      scrollToEnd('auto')
      updateNearBottomFromScroll()
      if (isNearBottom.value) {
        chat.onThreadScrolledNearBottom()
      }
    })
  })
}

onBeforeUpdate(() => {
  const el = root.value
  if (el) {
    savedDistanceFromBottom.value = el.scrollHeight - el.scrollTop - el.clientHeight
  }
})

onUpdated(() => {
  const el = root.value
  if (!el || props.messages.length === 0) {
    return
  }
  if (jumpTargetId.value != null) {
    const firstId = props.messages[0]?.id ?? null
    const len = props.messages.length
    lastTimelineFirstId.value = firstId
    lastTimelineLen.value = len
    updateNearBottomFromScroll()
    return
  }
  if (Date.now() < pendingJumpToBottomUntil.value) {
    const firstId = props.messages[0]?.id ?? null
    const len = props.messages.length
    el.scrollTop = Math.max(0, el.scrollHeight - el.clientHeight)
    pendingBelowCount.value = 0
    lastTimelineFirstId.value = firstId
    lastTimelineLen.value = len
    updateNearBottomFromScroll()
    return
  }
  const dist = savedDistanceFromBottom.value
  const firstId = props.messages[0]?.id ?? null
  const len = props.messages.length

  if (dist < STRICT_BOTTOM_PX) {
    el.scrollTop = el.scrollHeight - el.clientHeight
    pendingBelowCount.value = 0
  } else {
    el.scrollTop = el.scrollHeight - el.clientHeight - dist
    if (
      lastTimelineLen.value > 0
      && firstId === lastTimelineFirstId.value
      && len > lastTimelineLen.value
    ) {
      const added = props.messages.slice(lastTimelineLen.value)
      for (const m of added) {
        if (m.kind === 'incoming') {
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
  () => chat.activeChatId,
  () => {
    exitSelectionMode()
    pendingBelowCount.value = 0
    isNearBottom.value = true
    followTail.value = true
    pendingJumpToBottomUntil.value = 0
    lastTimelineFirstId.value = null
    lastTimelineLen.value = 0
  },
)

watch(
  () => props.messages.length,
  async () => {
    if (props.messages.length === 0) {
      return
    }
    await nextTick()
    if (jumpTargetId.value != null) {
      return
    }
    if (followTail.value) {
      scrollToEnd('auto')
    }
  },
)

watch(isTyping, async () => {
  await nextTick()
  if (jumpTargetId.value != null) {
    return
  }
  if (followTail.value) {
    scrollToEnd('auto')
  }
})

function tryScrollMessageIntoViewCentered(messageId: number): boolean {
  const r = root.value
  if (!r) {
    return false
  }
  const idStr = String(messageId)
  const el = r.querySelector(`[data-message-id="${idStr}"]`) as HTMLElement | null
  if (!el) {
    return false
  }
  const rootRect = r.getBoundingClientRect()
  const elRect = el.getBoundingClientRect()
  const delta
    = (elRect.top - rootRect.top) - (r.clientHeight / 2) + (elRect.height / 2)
  r.scrollTop += delta
  return true
}

async function scrollToMessageById(messageId: number): Promise<void> {
  const idStr = String(messageId)
  jumpTargetId.value = messageId
  highlightMessageId.value = idStr
  if (highlightTimer != null) {
    window.clearTimeout(highlightTimer)
  }
  highlightTimer = window.setTimeout(() => {
    highlightMessageId.value = null
    highlightTimer = null
  }, 1500)

  await nextTick()
  await new Promise<void>((resolve) => {
    requestAnimationFrame(() => {
      requestAnimationFrame(() => resolve())
    })
  })

  let ok = tryScrollMessageIntoViewCentered(messageId)
  if (!ok) {
    await new Promise<void>((resolve) => {
      requestAnimationFrame(() => resolve())
    })
    ok = tryScrollMessageIntoViewCentered(messageId)
  }
  if (!ok) {
    await nextTick()
    tryScrollMessageIntoViewCentered(messageId)
  }

  jumpTargetId.value = null
  updateNearBottomFromScroll()
}

defineExpose({ scrollToEnd, scrollToMessageById, enterSelectionMode, exitSelectionMode })
</script>

<template>
  <div class="relative flex min-h-0 flex-1 flex-col">
    <div
      ref="root"
      class="flex min-h-0 min-w-0 flex-1 flex-col gap-1.5 overflow-y-auto bg-[var(--zinc-50)] px-4 py-4 [-webkit-overflow-scrolling:touch] dark:bg-[var(--zinc-900)]"
      @scroll.passive="onScroll"
    >
      <div class="py-2 text-center text-[11px] font-medium text-[var(--zinc-400)]">
        Сегодня
      </div>
      <div
        v-for="m in messages"
        :key="m.id"
        :class="[
          'flex w-full',
          m.kind === 'outgoing' ? 'justify-end' : m.kind === 'system' ? 'justify-center' : 'justify-start',
          selectionMode && isSelected(m) ? 'rounded-2xl ring-2 ring-[var(--color-brand)] ring-offset-2 ring-offset-[var(--zinc-50)] dark:ring-offset-[var(--zinc-900)]' : '',
        ]"
        @pointerdown="onPointerDownRow(m, $event)"
        @pointerup="onPointerUpRow"
        @pointercancel="onPointerUpRow"
        @pointerleave="onPointerUpRow"
        @click="onRowClick(m, $event)"
      >
        <MessageBubble
          :message="m"
          :highlighted="highlightMessageId === String(m.id)"
          @reply="onReplyToMessage"
          @jump-reply="onJumpReply"
        />
      </div>
      <div v-if="isTyping" class="flex gap-1 self-start px-4 py-2.5" aria-live="polite">
        <span class="typing-dot size-1.5 rounded-full bg-[var(--zinc-400)]" />
        <span class="typing-dot size-1.5 rounded-full bg-[var(--zinc-400)]" />
        <span class="typing-dot size-1.5 rounded-full bg-[var(--zinc-400)]" />
      </div>
    </div>

    <div
      v-if="selectionMode"
      class="flex shrink-0 items-center justify-end gap-2 border-t border-[var(--color-gray-line)] bg-white px-3 py-2.5 dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-850)]"
    >
      <span class="mr-auto text-[10px] text-[var(--zinc-500)]">Выбор · долгое нажатие для старта</span>
      <button
        type="button"
        class="rounded-lg border border-[var(--color-gray-line)] px-3 py-1.5 text-xs font-medium text-[var(--zinc-600)] dark:border-[var(--zinc-600)] dark:text-[var(--zinc-300)]"
        @click="exitSelectionMode"
      >
        Отмена
      </button>
      <button
        type="button"
        class="rounded-lg bg-[var(--color-brand)] px-3 py-1.5 text-xs font-medium text-white disabled:opacity-50"
        :disabled="selectedIds.length === 0"
        @click="copySelectedMobile"
      >
        Копировать ({{ selectedIds.length }})
      </button>
    </div>

    <button
      v-if="messages.length > 0 && !isNearBottom"
      type="button"
      class="absolute bottom-4 right-4 z-10 flex h-12 w-12 items-center justify-center rounded-full bg-[var(--color-brand)] text-white shadow-lg transition active:scale-95"
      aria-label="К последним сообщениям"
      @click="onClickScrollDown"
    >
      <svg class="size-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
      </svg>
      <span
        v-if="pendingBelowCount > 0"
        class="absolute -right-1 -top-1 flex h-5 min-h-5 min-w-[1.25rem] shrink-0 items-center justify-center rounded-full bg-red-500 px-1.5 text-[10px] font-bold tabular-nums leading-none text-white"
        style="line-height: 1"
      >
        {{ pendingBelowCount > 99 ? '99+' : pendingBelowCount }}
      </span>
    </button>
  </div>
</template>
