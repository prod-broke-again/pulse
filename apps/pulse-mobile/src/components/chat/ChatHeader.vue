<script setup lang="ts">
import { ChevronLeft, UserPlus, X } from 'lucide-vue-next'
import { useRouter } from 'vue-router'
import type { ChatThreadMeta, ChannelSource } from '../../types/chat'
import ChannelGlyph from '../common/ChannelGlyph.vue'
import { useUiStore } from '../../stores/uiStore'

defineProps<{
  meta: ChatThreadMeta
}>()

const router = useRouter()
const ui = useUiStore()

function channelColor(ch: ChannelSource) {
  if (ch === 'tg') return '#2AABEE'
  if (ch === 'vk') return '#0077FF'
  return '#8b6b9a'
}

function back() {
  void router.back()
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
        type="button"
        class="flex cursor-pointer items-center gap-1 rounded-[10px] border-[1.5px] border-[var(--color-gray-line)] bg-white px-3 py-1.5 text-xs font-medium text-[var(--zinc-600)] transition-all active:scale-[0.97] dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-300)]"
        @click="ui.pushToast('Чат назначен на вас', 'success')"
      >
        <UserPlus class="size-3" aria-hidden="true" />
      </button>
      <button
        type="button"
        class="flex cursor-pointer items-center gap-1 rounded-[10px] border-[1.5px] border-[#fecaca] bg-white px-3 py-1.5 text-xs font-medium text-[#ef4444] transition-all active:scale-[0.97] dark:border-[rgba(248,113,113,0.2)] dark:bg-[var(--zinc-800)] dark:text-[#f87171]"
        @click="ui.pushToast('Чат закрыт', 'info')"
      >
        <X class="size-3" aria-hidden="true" />
      </button>
    </div>
  </div>
</template>
