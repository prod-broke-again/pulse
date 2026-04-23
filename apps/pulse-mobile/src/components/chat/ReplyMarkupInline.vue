<script setup lang="ts">
import type { ReplyMarkupButton } from '../../types/chat'

defineProps<{
  buttons: ReplyMarkupButton[]
}>()
</script>

<template>
  <div class="mt-1 flex w-full min-w-0 flex-col gap-1.5 self-stretch">
    <a
      v-for="(btn, idx) in buttons"
      :key="idx"
      :href="btn.url"
      target="_blank"
      rel="noopener noreferrer"
      class="block w-full min-w-0 rounded-xl border border-[var(--zinc-700)]/40 bg-zinc-100/90 px-3 py-2.5 text-center text-sm font-medium leading-snug break-words text-[var(--color-brand-500)] shadow-sm transition-[opacity,background-color] hover:opacity-95 [overflow-wrap:anywhere] dark:border-[var(--zinc-700)]/50 dark:bg-zinc-900/20 dark:text-[var(--color-brand-400)]"
    >
      {{ btn.text }}
    </a>
  </div>
</template>
