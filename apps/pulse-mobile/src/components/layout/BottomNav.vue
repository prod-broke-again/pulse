<script setup lang="ts">
import { Clock3, Inbox, Settings } from 'lucide-vue-next'
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import type { BottomNavId } from '../../types/chat'
import { useInboxStore } from '../../stores/inboxStore'

defineProps<{
  inboxBadge?: number
}>()

const router = useRouter()
const route = useRoute()
const inbox = useInboxStore()

/** Активный пункт строго по маршруту — без рассинхрона с store и без конфликта классов в Tailwind. */
const activeNav = computed((): BottomNavId => {
  const n = route.name
  if (n === 'settings') return 'settings'
  if (n === 'history') return 'history'
  return 'inbox'
})

function navButtonClass(nav: BottomNavId) {
  const on = activeNav.value === nav
  const base =
    'relative flex flex-1 flex-col items-center gap-0.5 border-none bg-transparent py-2.5 pb-2 pt-2.5 text-[10px] font-medium transition-colors'
  if (on) {
    return [
      base,
      'font-semibold text-[var(--color-brand)] dark:text-[var(--color-brand-200)]',
    ]
  }
  return [base, 'text-[var(--zinc-400)] dark:text-[var(--zinc-400)]']
}

function iconClass(nav: BottomNavId) {
  const on = activeNav.value === nav
  return [
    'size-[18px] shrink-0',
    on
      ? 'text-[var(--color-brand)] dark:text-[var(--color-brand-200)]'
      : 'text-[var(--zinc-400)] dark:text-[var(--zinc-400)]',
  ]
}

function goNav(nav: BottomNavId) {
  inbox.setBottomNav(nav)
  if (nav === 'settings') {
    void router.push({ name: 'settings' })
  } else if (nav === 'history') {
    void router.push({ name: 'history' })
  } else {
    void router.push({ name: 'inbox' })
  }
}
</script>

<template>
  <div
    class="flex shrink-0 border-t border-[var(--color-gray-line)] bg-white pb-[var(--safe-bottom)] dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-850)]"
  >
    <button type="button" :class="navButtonClass('inbox')" @click="goNav('inbox')">
      <Inbox :class="iconClass('inbox')" aria-hidden="true" />
      <span>Входящие</span>
      <span
        v-if="inboxBadge != null && inboxBadge > 0"
        class="absolute right-[calc(50%-18px)] top-1 flex h-4 min-w-4 items-center justify-center rounded-lg bg-[#ef4444] px-1 text-[9px] font-bold text-white"
      >
        {{ inboxBadge > 99 ? '99+' : inboxBadge }}
      </span>
    </button>
    <button type="button" :class="navButtonClass('history')" @click="goNav('history')">
      <Clock3 :class="iconClass('history')" aria-hidden="true" />
      <span>История</span>
    </button>
    <button type="button" :class="navButtonClass('settings')" @click="goNav('settings')">
      <Settings :class="iconClass('settings')" aria-hidden="true" />
      <span>Настройки</span>
    </button>
  </div>
</template>
