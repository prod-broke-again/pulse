<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { Search } from 'lucide-vue-next'
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import BottomNav from '../components/layout/BottomNav.vue'
import EmptyState from '../components/common/EmptyState.vue'
import ErrorState from '../components/common/ErrorState.vue'
import SkeletonList from '../components/common/SkeletonList.vue'
import ChannelGlyph from '../components/common/ChannelGlyph.vue'
import ChatCard from '../components/inbox/ChatCard.vue'
import InboxHeader from '../components/inbox/InboxHeader.vue'
import InboxTabs from '../components/inbox/InboxTabs.vue'
import type { FilterId } from '../types/chat'
import { useInboxStore } from '../stores/inboxStore'
import { useUiStore } from '../stores/uiStore'

const router = useRouter()
const inbox = useInboxStore()
const ui = useUiStore()

const {
  activeTab,
  activeFilters,
  searchQuery,
  isLoadingList,
  loadError,
  tabBadges,
  inboxBadge,
  filteredChats,
  showEmptyState,
  showChatList,
} = storeToRefs(inbox)

const ptrRefreshing = computed(() => ui.ptrRefreshing)

const filterDefs: { id: FilterId; label: string; dot?: 'green' | 'grey' | 'none'; channel?: FilterId }[] = [
  { id: 'open', label: 'Открытые', dot: 'green' },
  { id: 'closed', label: 'Закрытые', dot: 'grey' },
  { id: 'tg', label: 'Telegram', channel: 'tg' },
  { id: 'vk', label: 'VK', channel: 'vk' },
  { id: 'web', label: 'Web', channel: 'web' },
]

function chipActive(id: FilterId) {
  return activeFilters.value.has(id)
}

function toggleChip(id: FilterId) {
  inbox.toggleFilter(id)
}

/** Один набор классов без конфликта bg-white / bg-brand в каскаде Tailwind. */
function filterChipClass(id: FilterId) {
  const on = chipActive(id)
  const base =
    'flex shrink-0 cursor-pointer items-center gap-1 whitespace-nowrap rounded-full border-[1.5px] px-3.5 py-1.5 text-xs font-medium transition-all'
  if (on) {
    return [
      base,
      'border-[var(--color-brand)] bg-[var(--color-brand)] text-white dark:border-[var(--color-brand-500)] dark:bg-[var(--color-brand-500)] dark:text-white',
    ]
  }
  return [
    base,
    'border-[var(--color-gray-line)] bg-white text-[var(--zinc-600)] dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-300)]',
  ]
}

function openChat(id: string) {
  void router.push({ name: 'chat', params: { id } })
}

onMounted(() => {
  inbox.setBottomNav('inbox')
  void inbox.loadInbox()
  if (import.meta.env.DEV && typeof localStorage !== 'undefined') {
    if (localStorage.getItem('pulse:inboxForceError') === '1') {
      inbox.setLoadError(true)
    }
  }
})

</script>

<template>
  <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
    <InboxHeader />
    <InboxTabs :active="activeTab" :badges="tabBadges" @select="(t) => inbox.setActiveTab(t)" />

    <div
      class="flex shrink-0 flex-col gap-2 bg-white px-4 py-3 dark:bg-[var(--zinc-850)]"
    >
      <div
        class="flex h-[42px] items-center gap-2 rounded-xl border-[1.5px] border-transparent bg-[var(--zinc-100)] px-3 transition-[border-color] focus-within:border-[var(--color-brand-200)] dark:bg-[var(--zinc-800)]"
      >
        <Search class="size-3.5 shrink-0 text-[var(--zinc-400)]" aria-hidden="true" />
        <input
          :value="searchQuery"
          type="search"
          class="min-w-0 flex-1 border-none bg-transparent font-[family-name:var(--font-sans)] text-sm text-[var(--color-dark)] outline-none placeholder:text-[var(--zinc-400)] dark:text-[var(--zinc-100)]"
          placeholder="Поиск по имени или сообщению..."
          autocomplete="off"
          @input="inbox.setSearchQuery(($event.target as HTMLInputElement).value)"
        />
      </div>
      <div class="-mx-1 flex gap-1.5 overflow-x-auto px-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
        <button
          v-for="f in filterDefs"
          :key="f.id"
          type="button"
          :class="filterChipClass(f.id)"
          @click="toggleChip(f.id)"
        >
          <span
            v-if="f.dot === 'green'"
            class="inline-block size-1.5 shrink-0 rounded-full bg-[#22c55e]"
            aria-hidden="true"
          />
          <span
            v-else-if="f.dot === 'grey'"
            class="inline-block size-1.5 shrink-0 rounded-full bg-[#a1a1aa]"
            aria-hidden="true"
          />
          <ChannelGlyph v-else-if="f.channel === 'tg'" channel="tg" :size="12" />
          <ChannelGlyph v-else-if="f.channel === 'vk'" channel="vk" :size="12" />
          <ChannelGlyph v-else-if="f.channel === 'web'" channel="web" :size="12" />
          {{ f.label }}
        </button>
      </div>
    </div>

    <SkeletonList v-if="isLoadingList" />

    <div
      v-else-if="showChatList"
      class="chat-list min-h-0 flex-1 overflow-y-auto overscroll-y-contain [-webkit-overflow-scrolling:touch]"
    >
      <div v-show="ptrRefreshing" class="block px-3 py-3 text-center text-xs text-[var(--zinc-400)]">
        <span
          class="inline-block size-[18px] rounded-full border-2 border-[var(--zinc-200)] border-t-[var(--color-brand)] motion-safe:animate-spin dark:border-[var(--zinc-600)] dark:border-t-[var(--color-brand-200)]"
          aria-hidden="true"
        />
      </div>
      <ChatCard v-for="c in filteredChats" :key="c.id" :chat="c" @open="openChat" />
    </div>

    <EmptyState v-else-if="showEmptyState" />

    <ErrorState v-else-if="loadError" :on-retry="() => inbox.retryLoad()" />

    <BottomNav :inbox-badge="inboxBadge" />
  </div>
</template>
