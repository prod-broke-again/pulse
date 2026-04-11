<script setup lang="ts">
import { AlertTriangle } from 'lucide-vue-next'

defineProps<{
  onRetry: () => void
}>()
</script>

<template>
  <div
    class="flex flex-1 flex-col items-center justify-center gap-3 px-8 py-10 text-center"
  >
    <div
      class="mb-1 flex size-16 items-center justify-center rounded-[20px] bg-[#fee2e2] text-[#dc2626] dark:bg-[rgba(220,38,38,0.15)] dark:text-[#f87171]"
    >
      <AlertTriangle class="size-6" aria-hidden="true" />
    </div>
    <div class="text-base font-semibold text-[var(--color-dark)] dark:text-[var(--zinc-100)]">
      Ошибка загрузки
    </div>
    <div class="text-[13px] leading-normal text-[var(--zinc-500)] dark:text-[var(--zinc-500)]">
      Не удалось загрузить список обращений. Проверьте подключение и попробуйте снова.
    </div>
    <button
      type="button"
      class="mt-2 cursor-pointer rounded-xl border-none bg-[var(--color-brand)] px-6 py-2.5 text-[13px] font-semibold text-white"
      @click="onRetry"
    >
      Повторить
    </button>
  </div>
</template>
