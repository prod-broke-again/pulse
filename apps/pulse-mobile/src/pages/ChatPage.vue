<script setup lang="ts">
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

const { threadMeta, messages, threadSyncing } = storeToRefs(chat)

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
    <div
      v-if="threadMeta.status === 'closed'"
      class="shrink-0 border-b border-amber-200/80 bg-amber-50 px-4 py-2 text-center text-xs font-medium text-amber-900 dark:border-amber-800/50 dark:bg-amber-950/50 dark:text-amber-100"
      role="status"
    >
      Чат закрыт — клиент не получит ответ, пока чат снова не в работе.
    </div>
    <div
      v-if="threadSyncing"
      class="h-0.5 shrink-0 overflow-hidden bg-[var(--zinc-200)] dark:bg-[var(--zinc-700)]"
      role="status"
      aria-label="Синхронизация с сервером"
    >
      <div
        class="h-full w-2/5 max-w-[120px] bg-[var(--color-brand)] motion-safe:animate-pulse dark:bg-[var(--color-brand-300)]"
      />
    </div>

    <MessageList ref="listRef" :messages="messages" />

    <ComposerBar />

    <AiAssistPanel />
  </div>
</template>
