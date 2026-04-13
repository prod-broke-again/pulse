<script setup lang="ts">
import { computed } from 'vue'
import type { InboxTab } from '../../types/chat'

const props = defineProps<{
  active: InboxTab
  badges: { my: number; unassigned: number; all: number }
}>()

const emit = defineEmits<{
  select: [tab: InboxTab]
}>()

const tabs = computed(() =>
  (
    [
      { id: 'my' as const, label: 'Мои' },
      { id: 'unassigned' as const, label: 'Свободные' },
      { id: 'all' as const, label: 'Все' },
    ] as const
  ).map((t) => ({
    ...t,
    count: props.badges[t.id],
  })),
)

function tabButtonClass(id: InboxTab) {
  const on = props.active === id
  return [
    'relative flex-1 cursor-pointer border-b-2 bg-transparent py-2.5 text-center text-[13px] transition-all',
    on
      ? 'border-[var(--color-brand)] font-semibold text-[var(--color-brand)] dark:border-[var(--color-brand-200)] dark:text-[var(--color-brand-200)]'
      : 'border-transparent font-medium text-[var(--zinc-500)] dark:text-[var(--zinc-400)]',
  ]
}

function tabBadgeClass(id: InboxTab) {
  const on = props.active === id
  return [
    'ml-1 inline-flex h-[18px] min-w-[18px] items-center justify-center rounded-[9px] px-1.5 text-[10px] font-bold',
    on
      ? 'bg-[var(--color-brand)] text-white'
      : 'bg-[var(--zinc-300)] text-[var(--zinc-600)] dark:bg-[var(--zinc-700)] dark:text-[var(--zinc-400)]',
  ]
}
</script>

<template>
  <div
    class="flex shrink-0 gap-1 border-b border-[var(--color-gray-line)] bg-white px-4 dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-850)]"
  >
    <button
      v-for="t in tabs"
      :key="t.id"
      type="button"
      :class="tabButtonClass(t.id)"
      @click="emit('select', t.id)"
    >
      {{ t.label }}
      <span :class="tabBadgeClass(t.id)">{{ t.count }}</span>
    </button>
  </div>
</template>
