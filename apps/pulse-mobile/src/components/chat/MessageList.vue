<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { nextTick, onBeforeUpdate, onUpdated, ref, watch } from 'vue'
import type { ChatMessage } from '../../types/chat'
import { useChatStore } from '../../stores/chatStore'
import MessageBubble from './MessageBubble.vue'

const NEAR_BOTTOM_PX = 140

const props = defineProps<{
  messages: ChatMessage[]
}>()

const chat = useChatStore()
const { isTyping } = storeToRefs(chat)

function onReplyToMessage(messageId: string) {
  const n = Number(messageId)
  if (Number.isFinite(n) && n > 0) {
    chat.setReplyTarget(n)
  }
}

async function onJumpReply(messageId: number) {
  const ok = await chat.ensureReplyMessageLoaded(messageId)
  if (ok) {
    await nextTick()
    scrollToMessageById(messageId)
  }
}

const root = ref<HTMLElement | null>(null)
const pendingBelowCount = ref(0)
const isNearBottom = ref(true)
const savedDistanceFromBottom = ref(0)
const lastTimelineFirstId = ref<string | null>(null)
const lastTimelineLen = ref(0)
const highlightMessageId = ref<string | null>(null)
let highlightTimer: number | null = null

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
  scrollToEnd('smooth')
  pendingBelowCount.value = 0
  isNearBottom.value = true
  chat.onThreadScrolledNearBottom()
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
  const dist = savedDistanceFromBottom.value
  const firstId = props.messages[0]?.id ?? null
  const len = props.messages.length

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
    pendingBelowCount.value = 0
    isNearBottom.value = true
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
    if (isNearBottom.value) {
      scrollToEnd('auto')
    }
  },
)

watch(isTyping, async () => {
  await nextTick()
  if (isNearBottom.value) {
    scrollToEnd('auto')
  }
})

function scrollToMessageById(messageId: number) {
  const idStr = String(messageId)
  highlightMessageId.value = idStr
  if (highlightTimer != null) {
    window.clearTimeout(highlightTimer)
  }
  highlightTimer = window.setTimeout(() => {
    highlightMessageId.value = null
    highlightTimer = null
  }, 1500)
  void nextTick(() => {
    const r = root.value
    const el = r?.querySelector(`[data-message-id="${idStr}"]`)
    el?.scrollIntoView({ block: 'center', behavior: 'smooth' })
  })
}

defineExpose({ scrollToEnd, scrollToMessageById })
</script>

<template>
  <div class="relative min-h-0 flex-1">
    <div
      ref="root"
      class="flex min-h-0 flex-1 flex-col gap-1.5 overflow-y-auto bg-[var(--zinc-50)] px-4 py-4 [-webkit-overflow-scrolling:touch] dark:bg-[var(--zinc-900)]"
      @scroll.passive="onScroll"
    >
      <div class="py-2 text-center text-[11px] font-medium text-[var(--zinc-400)]">
        Сегодня
      </div>
      <MessageBubble
        v-for="m in messages"
        :key="m.id"
        :message="m"
        :highlighted="highlightMessageId === String(m.id)"
        @reply="onReplyToMessage"
        @jump-reply="onJumpReply"
      />
      <div v-if="isTyping" class="flex gap-1 self-start px-4 py-2.5" aria-live="polite">
        <span class="typing-dot size-1.5 rounded-full bg-[var(--zinc-400)]" />
        <span class="typing-dot size-1.5 rounded-full bg-[var(--zinc-400)]" />
        <span class="typing-dot size-1.5 rounded-full bg-[var(--zinc-400)]" />
      </div>
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
        class="absolute -right-1 -top-1 flex min-w-[1.25rem] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold leading-none text-white"
      >
        {{ pendingBelowCount > 99 ? '99+' : pendingBelowCount }}
      </span>
    </button>
  </div>
</template>
