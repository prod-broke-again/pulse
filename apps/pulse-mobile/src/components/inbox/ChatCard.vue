<script setup lang="ts">
import { Sparkles } from 'lucide-vue-next'
import type { ChatPreviewItem } from '../../types/chat'
import ChannelGlyph from '../common/ChannelGlyph.vue'

defineProps<{
  chat: ChatPreviewItem
}>()

const emit = defineEmits<{
  open: [id: string]
}>()
</script>

<template>
  <div
    class="relative flex cursor-pointer gap-3 border-b border-[var(--color-gray-line)] px-4 py-3.5 transition-colors active:bg-[var(--color-brand-50)] dark:border-[var(--zinc-800)] dark:active:bg-[var(--zinc-800)]"
    :class="{
      'bg-[rgba(85,23,94,0.03)] dark:bg-[rgba(154,95,168,0.06)]': chat.unread,
    }"
    role="button"
    tabindex="0"
    @click="emit('open', chat.id)"
    @keydown.enter.prevent="emit('open', chat.id)"
  >
    <div
      class="relative flex size-12 shrink-0 items-center justify-center rounded-[14px] text-lg font-semibold text-white"
      :class="{
        'bg-[#2AABEE]': chat.channel === 'tg',
        'bg-[#0077FF]': chat.channel === 'vk',
        'bg-[var(--color-brand-300)]': chat.channel === 'web',
      }"
    >
      {{ chat.initials }}
      <span
        class="absolute -bottom-0.5 -right-0.5 flex size-[18px] items-center justify-center rounded-md bg-white shadow-[0_1px_3px_rgba(0,0,0,0.15)] dark:bg-[var(--zinc-800)]"
        :class="{
          'text-[#2AABEE]': chat.channel === 'tg',
          'text-[#0077FF]': chat.channel === 'vk',
          'text-[var(--color-brand)]': chat.channel === 'web',
        }"
      >
        <ChannelGlyph :channel="chat.channel" :size="14" />
      </span>
    </div>
    <div class="flex min-w-0 flex-1 flex-col gap-0.5">
      <div class="flex items-center justify-between gap-2">
        <span
          class="max-w-[180px] truncate text-sm font-semibold text-[var(--color-dark)] dark:text-[var(--zinc-100)]"
        >
          {{ chat.name }}
        </span>
        <span class="shrink-0 text-[11px] text-[var(--zinc-400)]">{{ chat.timeLabel }}</span>
      </div>
      <div
        class="truncate text-[13px] leading-snug text-[var(--zinc-500)] dark:text-[var(--zinc-400)]"
        :class="{
          'font-medium text-[var(--color-dark)] dark:text-[var(--zinc-200)]': chat.unread,
        }"
      >
        {{ chat.preview }}
      </div>
      <div class="mt-0.5 flex flex-wrap items-center gap-1.5">
        <span
          class="rounded-md px-[7px] py-0.5 text-[10px] font-semibold"
          :class="
            chat.status === 'open'
              ? 'bg-[rgba(34,197,94,0.12)] text-[#16a34a] dark:bg-[rgba(34,197,94,0.15)] dark:text-[#4ade80]'
              : 'bg-[var(--zinc-200)] text-[var(--zinc-600)] dark:bg-[var(--zinc-700)] dark:text-[var(--zinc-300)]'
          "
        >
          {{ chat.status === 'open' ? 'Открыт' : 'Закрыт' }}
        </span>
        <span
          v-if="chat.department"
          class="max-w-[min(140px,45vw)] truncate rounded-md bg-[var(--color-brand-50)] px-[7px] py-0.5 text-[10px] font-semibold text-[var(--color-brand-500)] dark:bg-[var(--zinc-800)] dark:text-[var(--color-brand-200)]"
          :title="chat.department"
        >
          {{ chat.department }}
        </span>
        <span
          v-if="chat.hasAiTag"
          class="inline-flex items-center gap-0.5 rounded-md bg-[#fef3c7] px-[7px] py-0.5 text-[10px] font-semibold text-[#92400e] dark:bg-[rgba(251,191,36,0.15)] dark:text-[#fbbf24]"
        >
          <Sparkles class="size-2" aria-hidden="true" />
          AI
        </span>
      </div>
    </div>
    <span
      v-if="chat.unread && chat.unreadCount != null"
      class="flex size-5 shrink-0 items-center justify-center rounded-full bg-[var(--color-brand)] text-[10px] font-bold text-white"
    >
      {{ chat.unreadCount }}
    </span>
  </div>
</template>
