<script setup lang="ts">
import { Clock3, Moon, Search, Sun } from 'lucide-vue-next'
import { storeToRefs } from 'pinia'
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import BottomNav from '../components/layout/BottomNav.vue'
import EmptyState from '../components/common/EmptyState.vue'
import SkeletonList from '../components/common/SkeletonList.vue'
import ChatCard from '../components/inbox/ChatCard.vue'
import { useInboxStore } from '../stores/inboxStore'
import { useUiStore } from '../stores/uiStore'

const router = useRouter()
const inbox = useInboxStore()
const ui = useUiStore()

const { historySearchQuery, filteredHistoryChats, inboxBadge, isLoadingHistory } = storeToRefs(inbox)
const { isDark } = storeToRefs(ui)

onMounted(() => {
  inbox.setBottomNav('history')
  void inbox.loadHistory()
})

function openChat(id: string) {
  void router.push({ name: 'chat', params: { id } })
}
</script>

<template>
  <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
    <div
      class="flex min-h-14 shrink-0 items-center justify-between border-b border-[var(--color-gray-line)] bg-white px-4 pb-3 pt-[calc(12px+var(--safe-top))] dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-850)]"
    >
      <div class="flex min-w-0 flex-col gap-0.5">
        <div class="text-lg font-bold text-[var(--color-dark)] dark:text-[var(--zinc-50)]">
          История
        </div>
        <div class="text-[11px] font-medium text-[var(--zinc-400)]">
          Закрытые и архивные обращения
        </div>
      </div>
      <div class="flex shrink-0 gap-2">
        <button
          type="button"
          class="flex size-10 cursor-pointer items-center justify-center rounded-xl border-none bg-[var(--color-brand-50)] text-[var(--color-brand)] transition-all active:scale-95 dark:bg-[var(--zinc-800)] dark:text-[var(--color-brand-200)]"
          aria-label="Переключить тему"
          @click="ui.toggleTheme()"
        >
          <Moon v-if="!isDark" class="size-4" aria-hidden="true" />
          <Sun v-else class="size-4" aria-hidden="true" />
        </button>
      </div>
    </div>

    <div
      class="flex shrink-0 flex-col gap-2 bg-white px-4 py-3 dark:bg-[var(--zinc-850)]"
    >
      <div
        class="flex h-[42px] items-center gap-2 rounded-xl border-[1.5px] border-transparent bg-[var(--zinc-100)] px-3 transition-[border-color] focus-within:border-[var(--color-brand-200)] dark:bg-[var(--zinc-800)]"
      >
        <Search class="size-3.5 shrink-0 text-[var(--zinc-400)]" aria-hidden="true" />
        <input
          :value="historySearchQuery"
          type="search"
          class="min-w-0 flex-1 border-none bg-transparent text-sm text-[var(--color-dark)] outline-none placeholder:text-[var(--zinc-400)] dark:text-[var(--zinc-100)]"
          placeholder="Поиск по истории..."
          autocomplete="off"
          @input="inbox.setHistorySearchQuery(($event.target as HTMLInputElement).value)"
        />
      </div>
    </div>

    <SkeletonList v-if="isLoadingHistory" />

    <div
      v-else-if="filteredHistoryChats.length > 0"
      class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain [-webkit-overflow-scrolling:touch]"
    >
      <ChatCard v-for="c in filteredHistoryChats" :key="c.id" :chat="c" @open="openChat" />
    </div>

    <EmptyState
      v-else
      title="Нет записей"
      description="Здесь появятся завершённые обращения. Пока список пуст или ничего не найдено по запросу."
      :icon="Clock3"
    />

    <BottomNav :inbox-badge="inboxBadge" />
  </div>
</template>
