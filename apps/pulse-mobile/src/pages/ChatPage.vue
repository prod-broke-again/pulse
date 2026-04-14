<script setup lang="ts">
import { ChevronRight, Sparkles } from 'lucide-vue-next'
import { storeToRefs } from 'pinia'
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import AiAssistPanel from '../components/chat/AiAssistPanel.vue'
import ChatHeader from '../components/chat/ChatHeader.vue'
import ComposerBar from '../components/chat/ComposerBar.vue'
import MessageList from '../components/chat/MessageList.vue'
import { useChatStore } from '../stores/chatStore'

const route = useRoute()
const chat = useChatStore()

const { threadMeta, messages } = storeToRefs(chat)

const listRef = ref<InstanceType<typeof MessageList> | null>(null)

function scrollEnd() {
  listRef.value?.scrollToEnd()
}

function initThread() {
  chat.setTyping(false)
  const id = route.params.id as string
  void chat.fetchThread(id)
  chat.subscribeThread(id)
  if (import.meta.env.DEV && typeof localStorage !== 'undefined') {
    if (localStorage.getItem('pulse:chatTyping') === '1') {
      chat.setTyping(true)
    }
  }
}

initThread()

onMounted(() => {
  void nextTick(() => {
    window.setTimeout(scrollEnd, 50)
  })
})

onUnmounted(() => {
  chat.clearAiTimers()
  chat.setTyping(false)
  chat.leaveThread()
})

watch(
  () => route.params.id,
  (id) => {
    if (typeof id === 'string') {
      initThread()
      void nextTick(() => window.setTimeout(scrollEnd, 50))
    }
  },
)

</script>

<template>
  <div v-if="threadMeta" class="relative flex min-h-0 flex-1 flex-col overflow-hidden">
    <ChatHeader :meta="threadMeta" @select-for-copy="() => listRef?.enterSelectionMode?.()" />

    <button
      type="button"
      class="flex shrink-0 cursor-pointer items-start gap-2 border-b border-[var(--color-gray-line)] bg-gradient-to-br from-[rgba(85,23,94,0.06)] to-[rgba(154,95,168,0.06)] px-4 py-2.5 text-left dark:border-[var(--zinc-700)] dark:from-[rgba(85,23,94,0.15)] dark:to-[rgba(154,95,168,0.08)]"
      @click="chat.openAiPanel()"
    >
      <div
        class="flex size-7 shrink-0 items-center justify-center rounded-lg bg-[var(--color-brand)] text-xs text-white"
      >
        <Sparkles class="size-3" aria-hidden="true" />
      </div>
      <div class="min-w-0 flex-1">
        <div
          class="mb-0.5 text-[10px] font-bold uppercase tracking-wide text-[var(--color-brand)] dark:text-[var(--color-brand-200)]"
        >
          AI-резюме
        </div>
        <div class="text-xs leading-normal text-[var(--zinc-600)] dark:text-[var(--zinc-300)]">
          {{ threadMeta.aiSummaryBar }}
        </div>
      </div>
      <ChevronRight class="mt-0.5 size-3 shrink-0 text-[var(--zinc-400)]" aria-hidden="true" />
    </button>

    <MessageList ref="listRef" :messages="messages" />

    <ComposerBar />

    <AiAssistPanel />
  </div>
</template>
