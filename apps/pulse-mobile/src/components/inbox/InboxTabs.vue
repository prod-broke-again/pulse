<script setup lang="ts">
import { computed } from 'vue'
import type { InboxSummaryData } from '../../api/types'

const props = defineProps<{
  active: 'all' | number
  departments: Array<{ id: number; name: string }>
  summary: InboxSummaryData | null
}>()

const emit = defineEmits<{
  select: [tab: 'all' | number]
}>()

const tabs = computed(() => {
  const list = [
    { id: 'all' as const, label: 'Все', count: props.summary?.all?.open ?? 0 }
  ]
  for (const dept of props.departments) {
    const sDept = props.summary?.departments?.find(d => d.id === dept.id)
    list.push({
      id: dept.id,
      label: dept.name,
      count: sDept ? sDept.open : 0
    })
  }
  return list
})

function tabButtonClass(on: boolean) {
  return [
    'relative shrink-0 cursor-pointer border-b-2 bg-transparent px-3 py-2.5 text-center text-[13px] transition-all whitespace-nowrap',
    on
      ? 'border-[var(--color-brand)] font-semibold text-[var(--color-brand)] dark:border-[var(--color-brand-200)] dark:text-[var(--color-brand-200)]'
      : 'border-transparent font-medium text-[var(--zinc-500)] dark:text-[var(--zinc-400)]',
  ]
}

function tabBadgeClass(on: boolean) {
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
    class="flex shrink-0 gap-2 border-b border-[var(--color-gray-line)] bg-white px-4 overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-850)]"
  >
    <button
      v-for="t in tabs"
      :key="t.id"
      type="button"
      :class="tabButtonClass(props.active === t.id)"
      @click="emit('select', t.id)"
    >
      {{ t.label }}
      <span v-if="t.count > 0" :class="tabBadgeClass(props.active === t.id)">{{ t.count }}</span>
    </button>
  </div>
</template>
