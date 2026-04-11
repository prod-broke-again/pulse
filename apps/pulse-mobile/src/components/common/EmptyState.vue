<script setup lang="ts">
import type { Component } from 'vue'
import { Inbox } from 'lucide-vue-next'

withDefaults(
  defineProps<{
    title?: string
    description?: string
    /** Lucide icon component */
    icon?: Component
  }>(),
  {
    title: 'Нет обращений',
    description:
      'Все чаты обработаны. Новые обращения появятся здесь автоматически.',
    icon: undefined,
  },
)

const DefaultIcon = Inbox
</script>

<template>
  <div
    class="flex flex-1 flex-col items-center justify-center gap-3 px-8 py-10 text-center"
  >
    <div
      class="mb-1 flex size-16 items-center justify-center rounded-[20px] bg-[var(--color-brand-50)] text-[var(--color-brand)] dark:bg-[rgba(85,23,94,0.2)] dark:text-[var(--color-brand-200)]"
    >
      <component :is="icon ?? DefaultIcon" class="size-6" aria-hidden="true" />
    </div>
    <div class="text-base font-semibold text-[var(--color-dark)] dark:text-[var(--zinc-100)]">
      {{ title }}
    </div>
    <div class="text-[13px] leading-normal text-[var(--zinc-500)] dark:text-[var(--zinc-500)]">
      {{ description }}
    </div>
  </div>
</template>
