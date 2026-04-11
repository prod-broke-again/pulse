<script setup lang="ts">
import { Bell, Moon, Sun } from 'lucide-vue-next'
import { storeToRefs } from 'pinia'
import { useUiStore } from '../../stores/uiStore'

const ui = useUiStore()
const { isDark } = storeToRefs(ui)
</script>

<template>
  <div
    class="flex min-h-14 shrink-0 items-center justify-between border-b border-[var(--color-gray-line)] bg-white px-4 pb-3 pt-[calc(12px+var(--safe-top))] dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-850)]"
  >
    <div class="flex items-center gap-2 text-lg font-bold text-[var(--color-dark)] dark:text-[var(--zinc-50)]">
      <div
        class="size-2 shrink-0 rounded-full bg-[var(--color-brand)] pulse-dot-animate"
        aria-hidden="true"
      />
      Pulse
    </div>
    <div class="flex gap-2">
      <button
        type="button"
        class="relative flex size-10 cursor-pointer items-center justify-center rounded-xl border-none bg-[var(--color-brand-50)] text-base text-[var(--color-brand)] transition-all active:scale-95 dark:bg-[var(--zinc-800)] dark:text-[var(--color-brand-200)]"
        aria-label="Переключить тему"
        @click="ui.toggleTheme()"
      >
        <Moon v-if="!isDark" class="size-4" aria-hidden="true" />
        <Sun v-else class="size-4" aria-hidden="true" />
      </button>
      <button
        type="button"
        class="relative flex size-10 cursor-pointer items-center justify-center rounded-xl border-none bg-[var(--color-brand-50)] text-base text-[var(--color-brand)] transition-all active:scale-95 dark:bg-[var(--zinc-800)] dark:text-[var(--color-brand-200)]"
        aria-label="Уведомления"
      >
        <Bell class="size-4" aria-hidden="true" />
        <span
          class="absolute right-2 top-2 size-[7px] rounded-full border-2 border-white bg-[#ef4444] dark:border-[var(--zinc-850)]"
          aria-hidden="true"
        />
      </button>
    </div>
  </div>
</template>
