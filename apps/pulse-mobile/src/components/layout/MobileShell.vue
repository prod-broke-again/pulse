<!-- Иконки: Lucide + simple-icons вместо Font Awesome из app (9).html — допустимое отличие по плану. -->
<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { AlertCircle, CheckCircle2, Info, WifiOff } from 'lucide-vue-next'
import { useUiStore } from '../../stores/uiStore'

const ui = useUiStore()
const { isOffline, toasts } = storeToRefs(ui)

function toastClass(type: string) {
  if (type === 'success') {
    return 'bg-[#dcfce7] text-[#166534] dark:bg-[rgba(22,163,106,0.2)] dark:text-[#4ade80]'
  }
  if (type === 'error') {
    return 'bg-[#fee2e2] text-[#991b1b] dark:bg-[rgba(220,38,38,0.2)] dark:text-[#f87171]'
  }
  return 'bg-[var(--color-brand-50)] text-[var(--color-brand)] dark:bg-[rgba(85,23,94,0.3)] dark:text-[var(--color-brand-200)]'
}
</script>

<template>
  <div
    class="relative mx-auto flex h-dvh max-w-[430px] flex-col overflow-hidden bg-[var(--color-light)] text-[var(--color-dark)] dark:bg-[var(--zinc-900)] dark:text-[var(--zinc-100)]"
  >
    <div
      class="pointer-events-none absolute left-4 right-4 z-[100] flex flex-col gap-2"
      :style="{ top: 'calc(16px + var(--safe-top))' }"
    >
      <div
        v-for="t in toasts"
        :key="t.id"
        class="toast-animate-in pointer-events-auto flex items-center gap-2 rounded-[14px] px-4 py-3 text-[13px] font-medium shadow-[0_4px_20px_rgba(0,0,0,0.12)]"
        :class="toastClass(t.type)"
      >
        <CheckCircle2 v-if="t.type === 'success'" class="size-[15px] shrink-0" aria-hidden="true" />
        <AlertCircle v-else-if="t.type === 'error'" class="size-[15px] shrink-0" aria-hidden="true" />
        <Info v-else class="size-[15px] shrink-0" aria-hidden="true" />
        <span>{{ t.message }}</span>
      </div>
    </div>

    <div
      class="hidden shrink-0 items-center justify-center gap-1.5 bg-[#fef3c7] px-4 py-2 text-center text-[12px] font-medium text-[#92400e] dark:bg-[rgba(217,119,6,0.15)] dark:text-[#fbbf24]"
      :class="{ '!flex': isOffline }"
    >
      <WifiOff class="size-3 shrink-0" aria-hidden="true" />
      Нет подключения к интернету
    </div>

    <router-view class="flex min-h-0 flex-1 flex-col overflow-hidden" />
  </div>
</template>
