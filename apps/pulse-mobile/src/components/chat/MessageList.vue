<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { nextTick, ref, watch } from 'vue'
import type { ChatMessage } from '../../types/chat'
import { useChatStore } from '../../stores/chatStore'
import MessageBubble from './MessageBubble.vue'

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

const root = ref<HTMLElement | null>(null)

function onScroll(e: Event) {
  const el = e.target as HTMLElement
  if (el.scrollTop < 120) {
    void chat.loadOlderMessages()
  }
  const threshold = 120
  const distFromBottom = el.scrollHeight - el.scrollTop - el.clientHeight
  if (distFromBottom < threshold) {
    chat.onThreadScrolledNearBottom()
  }
}

function scrollToEnd() {
  const el = root.value
  if (!el) return
  el.scrollTop = el.scrollHeight
}

watch(
  () => props.messages.length,
  async () => {
    await nextTick()
    scrollToEnd()
  },
)

watch(isTyping, async () => {
  await nextTick()
  scrollToEnd()
})

defineExpose({ scrollToEnd })
</script>

<template>
  <div
    ref="root"
    class="flex min-h-0 flex-1 flex-col gap-1.5 overflow-y-auto bg-[var(--zinc-50)] px-4 py-4 [-webkit-overflow-scrolling:touch] dark:bg-[var(--zinc-900)]"
    @scroll.passive="onScroll"
  >
    <div class="py-2 text-center text-[11px] font-medium text-[var(--zinc-400)]">Сегодня</div>
    <MessageBubble v-for="m in messages" :key="m.id" :message="m" @reply="onReplyToMessage" />
    <div v-if="isTyping" class="flex gap-1 self-start px-4 py-2.5" aria-live="polite">
      <span
        class="typing-dot size-1.5 rounded-full bg-[var(--zinc-400)]"
      />
      <span
        class="typing-dot size-1.5 rounded-full bg-[var(--zinc-400)]"
      />
      <span
        class="typing-dot size-1.5 rounded-full bg-[var(--zinc-400)]"
      />
    </div>
  </div>
</template>
