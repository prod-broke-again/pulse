<script setup lang="ts">
import { ChevronLeft, UserPlus, X } from 'lucide-vue-next'
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import type { ChatThreadMeta, ChannelSource } from '../../types/chat'
import ChannelGlyph from '../common/ChannelGlyph.vue'
import { useAuthStore } from '../../stores/authStore'
import { useChatStore } from '../../stores/chatStore'

const props = defineProps<{
  meta: ChatThreadMeta
}>()

const router = useRouter()
const auth = useAuthStore()
const chat = useChatStore()

/** Скрываем «назначить на себя», если чат уже на текущем модераторе или закрыт. */
const showAssignButton = computed(() => {
  if (props.meta.status === 'closed') return false
  const uid = auth.user?.id
  if (uid == null) return true
  return props.meta.assignedToUserId !== uid
})

const showCloseButton = computed(() => props.meta.status === 'open')
const headerAction = ref<'assign' | 'close' | null>(null)

function channelColor(ch: ChannelSource) {
  if (ch === 'tg') return '#2AABEE'
  if (ch === 'vk') return '#0077FF'
  return '#8b6b9a'
}

function back() {
  void router.back()
}

async function onAssignMe() {
  if (headerAction.value) return
  headerAction.value = 'assign'
  try {
    await chat.assignToMe()
  } finally {
    headerAction.value = null
  }
}

async function onCloseChat() {
  if (headerAction.value) return
  headerAction.value = 'close'
  try {
    await chat.closeThread()
  } finally {
    headerAction.value = null
  }
}
</script>

<template>
  <div
    class="flex shrink-0 items-center gap-2.5 border-b border-[var(--color-gray-line)] bg-white px-3 pb-2.5 pt-[calc(10px+var(--safe-top))] dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-850)]"
  >
    <button
      type="button"
      class="flex size-9 cursor-pointer items-center justify-center rounded-[10px] border-none bg-transparent text-base text-[var(--color-brand)] dark:text-[var(--color-brand-200)]"
      aria-label="Назад"
      @click="back()"
    >
      <ChevronLeft class="size-4" />
    </button>
    <div class="min-w-0 flex-1">
      <div
        class="flex items-center gap-1.5 text-[15px] font-semibold text-[var(--color-dark)] dark:text-[var(--zinc-100)]"
      >
        {{ meta.userName }}
        <span
          class="inline-block size-1.5 rounded-full"
          :class="meta.status === 'open' ? 'bg-[#22c55e]' : 'bg-[var(--zinc-400)]'"
          aria-hidden="true"
        />
      </div>
      <div class="flex items-center gap-1 text-[11px] text-[var(--zinc-400)]">
        <span class="inline-flex shrink-0" :style="{ color: channelColor(meta.channel) }">
          <ChannelGlyph :channel="meta.channel" :size="10" />
        </span>
        <span :style="{ color: channelColor(meta.channel) }">{{ meta.channelLabel }}</span>
        <span>· {{ meta.departmentLabel }}</span>
      </div>
    </div>
    <div class="flex gap-1">
      <button
        v-if="showAssignButton"
        type="button"
        class="flex cursor-pointer items-center gap-1 rounded-[10px] border-[1.5px] border-[var(--color-gray-line)] bg-white px-3 py-1.5 text-xs font-medium text-[var(--zinc-600)] transition-all active:scale-[0.97] enabled:hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-50 dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-300)]"
        :disabled="headerAction !== null"
        aria-label="Назначить чат на себя"
        @click="onAssignMe()"
      >
        <UserPlus
          class="size-3 motion-safe:transition-transform"
          :class="{ 'motion-safe:animate-pulse': headerAction === 'assign' }"
          aria-hidden="true"
        />
      </button>
      <button
        v-if="showCloseButton"
        type="button"
        class="flex cursor-pointer items-center gap-1 rounded-[10px] border-[1.5px] border-[#fecaca] bg-white px-3 py-1.5 text-xs font-medium text-[#ef4444] transition-all active:scale-[0.97] enabled:hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-50 dark:border-[rgba(248,113,113,0.2)] dark:bg-[var(--zinc-800)] dark:text-[#f87171]"
        :disabled="headerAction !== null"
        aria-label="Закрыть чат"
        @click="onCloseChat()"
      >
        <X
          class="size-3 motion-safe:transition-transform"
          :class="{ 'motion-safe:animate-pulse': headerAction === 'close' }"
          aria-hidden="true"
        />
      </button>
    </div>
  </div>
</template>
