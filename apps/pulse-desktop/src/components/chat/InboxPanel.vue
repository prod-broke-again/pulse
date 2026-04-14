<script setup lang="ts">
import { ref, watch, computed, onMounted, onBeforeUnmount } from 'vue'
import { Search, Send, MessageCircleMore, Loader2, MoreVertical } from 'lucide-vue-next'
import { useChatStore } from '../../stores/chatStore'
import type { Conversation, ConversationChannel } from '../../types/chat'
import { isMutedUntilActive } from '../../lib/chatMute'

function sourceBadgeClass(channel: ConversationChannel): string {
  if (channel === 'telegram' || channel === 'tg') {
    return 'bg-[#2AABEE]'
  }
  if (channel === 'vk') {
    return 'bg-[#4C75A3]'
  }
  return 'bg-[var(--color-brand-200)]'
}

defineProps<{
  conversations: Conversation[]
  activeTab: 'my' | 'unassigned' | 'all'
  isLoading: boolean
  canLoadMore: boolean
  selectedChatId: number | null
  tabCounts?: { my: number; unassigned: number; all: number }
}>()

const emit = defineEmits<{
  (e: 'select-chat', chatId: number): void
  (e: 'change-tab', tab: 'my' | 'unassigned' | 'all'): void
  (e: 'load-more'): void
  (e: 'change-status', status: 'open' | 'closed'): void
  (e: 'assign-me', chatId: number): void
  (e: 'close-chat', chatId: number): void
  (e: 'mute-chat', chatId: number, mode: '1h' | '8h' | 'forever' | 'unmute'): void
}>()

const chatStore = useChatStore()
const searchQuery = ref(chatStore.filters.search)

const menuOpen = ref(false)
const menuChatId = ref<number | null>(null)
const menuX = ref(0)
const menuY = ref(0)

function closeMenu(): void {
  menuOpen.value = false
  menuChatId.value = null
}

function openMenuAt(chatId: number, e: MouseEvent): void {
  e.preventDefault()
  e.stopPropagation()
  menuChatId.value = chatId
  menuX.value = e.clientX
  menuY.value = e.clientY
  menuOpen.value = true
}

function onGlobalClick(): void {
  closeMenu()
}

onMounted(() => {
  window.addEventListener('click', onGlobalClick)
})

onBeforeUnmount(() => {
  window.removeEventListener('click', onGlobalClick)
})

let debounceTimeout: ReturnType<typeof setTimeout> | null = null

watch(searchQuery, (newQuery) => {
  if (debounceTimeout) {
    clearTimeout(debounceTimeout)
  }
  debounceTimeout = setTimeout(() => {
    chatStore.setFilters({ search: newQuery })
  }, 500)
})

const statusSelect = computed({
  get: () => chatStore.filters.status,
  set: (v: 'open' | 'closed') => {
    void chatStore.setFilters({ status: v })
  },
})

function isMenuChatMuted(): boolean {
  const id = menuChatId.value
  if (id == null) {
    return false
  }
  const row = chatStore.chats.find((c) => c.id === id)
  return isMutedUntilActive(row?.muted_until)
}

function pickMute(mode: '1h' | '8h' | 'forever' | 'unmute'): void {
  const id = menuChatId.value
  if (id == null) {
    return
  }
  emit('mute-chat', id, mode)
  closeMenu()
}

function pickAssign(): void {
  const id = menuChatId.value
  if (id == null) {
    return
  }
  emit('assign-me', id)
  closeMenu()
}

function pickClose(): void {
  const id = menuChatId.value
  if (id == null) {
    return
  }
  emit('close-chat', id)
  closeMenu()
}

/** Базовый паттерн счетчика: фикс. высота + min-width + padding, единая типографика для всех чисел. */
function unreadBadgeClass(count: number): string {
  if (count <= 0) {
    return ''
  }
  if (count <= 9) {
    return 'h-5 min-w-5 px-1'
  }
  if (count <= 99) {
    return 'h-5 min-w-5 px-1.5'
  }
  return 'h-5 min-w-[1.7rem] px-1.5'
}
</script>

<template>
  <section
    class="flex h-full w-[340px] shrink-0 flex-col border-r"
    style="background: var(--bg-inbox); border-color: var(--border-light)"
  >
    <div class="px-[18px] pt-[18px]">
      <div class="mb-3.5 flex items-center justify-between">
        <span class="text-lg font-bold" style="color: var(--text-primary)">Обращения</span>
        <span
          class="rounded-full px-2 py-0.5 text-xs font-semibold"
          style="color: var(--color-brand-200); background: var(--color-brand-50)"
        >
          {{ chatStore.pagination.total }}
        </span>
      </div>

      <div class="mb-3 flex gap-0.5 rounded-[var(--radius-md)] p-0.5" style="background: var(--bg-app)">
        <button
          type="button"
          class="flex flex-1 items-center justify-center gap-1 rounded-[7px] py-[7px] text-[12.5px] font-medium transition"
          :style="activeTab === 'my'
            ? { background: 'var(--bg-inbox)', color: 'var(--text-primary)', fontWeight: 600, boxShadow: 'var(--shadow-sm)' }
            : { color: 'var(--text-muted)' }"
          @click="emit('change-tab', 'my')"
        >
          <span>Мои</span>
          <span
            v-if="tabCounts && tabCounts.my > 0"
            class="min-w-[18px] rounded-full px-1 text-[10px] font-semibold leading-tight"
            style="background: var(--color-brand-50); color: var(--color-brand-200)"
          >{{ tabCounts.my > 99 ? '99+' : tabCounts.my }}</span>
        </button>
        <button
          type="button"
          class="flex flex-1 items-center justify-center gap-1 rounded-[7px] py-[7px] text-[12.5px] font-medium transition"
          :style="activeTab === 'unassigned'
            ? { background: 'var(--bg-inbox)', color: 'var(--text-primary)', fontWeight: 600, boxShadow: 'var(--shadow-sm)' }
            : { color: 'var(--text-muted)' }"
          title="Нераспределённые"
          @click="emit('change-tab', 'unassigned')"
        >
          <span>Свободные</span>
          <span
            v-if="tabCounts && tabCounts.unassigned > 0"
            class="min-w-[18px] rounded-full px-1 text-[10px] font-semibold leading-tight"
            style="background: var(--color-brand-50); color: var(--color-brand-200)"
          >{{ tabCounts.unassigned > 99 ? '99+' : tabCounts.unassigned }}</span>
        </button>
        <button
          type="button"
          class="flex flex-1 items-center justify-center gap-1 rounded-[7px] py-[7px] text-[12.5px] font-medium transition"
          :style="activeTab === 'all'
            ? { background: 'var(--bg-inbox)', color: 'var(--text-primary)', fontWeight: 600, boxShadow: 'var(--shadow-sm)' }
            : { color: 'var(--text-muted)' }"
          @click="emit('change-tab', 'all')"
        >
          <span>Все</span>
          <span
            v-if="tabCounts && tabCounts.all > 0"
            class="min-w-[18px] rounded-full px-1 text-[10px] font-semibold leading-tight"
            style="background: var(--color-brand-50); color: var(--color-brand-200)"
          >{{ tabCounts.all > 99 ? '99+' : tabCounts.all }}</span>
        </button>
      </div>

      <div class="mb-3 flex gap-2">
        <div class="relative min-w-0 flex-1">
          <Search
            class="pointer-events-none absolute left-[11px] top-1/2 h-[13px] w-[13px] -translate-y-1/2"
            style="color: var(--text-muted)"
          />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Поиск по имени или теме..."
            class="w-full rounded-[var(--radius-md)] border py-2 pl-9 pr-2.5 text-[13px] outline-none transition"
            style="border-color: var(--border-light); background: var(--bg-thread); color: var(--text-primary)"
          >
        </div>
        <select
          v-model="statusSelect"
          class="min-w-[100px] cursor-pointer rounded-[var(--radius-md)] border px-2.5 py-2 text-xs outline-none"
          style="border-color: var(--border-light); background: var(--bg-thread); color: var(--text-primary)"
        >
          <option value="open">
            Открытые
          </option>
          <option value="closed">
            Закрытые
          </option>
        </select>
      </div>
    </div>

    <div v-if="isLoading" class="flex min-h-0 flex-1 items-center justify-center">
      <Loader2 class="h-6 w-6 animate-spin" style="color: var(--color-brand)" />
    </div>

    <div v-else-if="conversations.length === 0" class="flex min-h-0 flex-1 flex-col items-center justify-center px-6 pb-8 text-center">
      <p class="text-sm font-medium" style="color: var(--text-secondary)">
        Нет обращений
      </p>
      <p class="mt-1 text-xs" style="color: var(--text-muted)">
        По выбранным фильтрам обращения не найдены
      </p>
    </div>

    <div v-else class="custom-scroll min-h-0 flex-1 space-y-0 overflow-y-auto px-2 pb-2">
      <div v-if="canLoadMore" class="flex justify-center pb-2">
        <button
          type="button"
          class="rounded-full border px-4 py-1.5 text-xs font-semibold transition"
          style="border-color: var(--border-light); color: var(--text-secondary)"
          :disabled="isLoading"
          @click="emit('load-more')"
        >
          Загрузить ещё
        </button>
      </div>
      <article
        v-for="chat in conversations"
        :key="chat.id"
        class="group chat-item-m flex cursor-pointer gap-3 rounded-[var(--radius-md)] border border-transparent px-2.5 py-3 transition-colors"
        :class="chat.id === selectedChatId ? 'chat-item-active' : ''"
        :data-unread="(chat.unreadCount ?? 0) > 0 ? '1' : '0'"
        @click="emit('select-chat', chat.id)"
        @contextmenu="openMenuAt(chat.id, $event)"
      >
        <div
          class="relative flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white"
          style="background: var(--color-brand-200)"
        >
          {{ chat.initials || chat.name.slice(0, 2).toUpperCase() }}
          <div
            class="absolute -bottom-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full border-2 text-[8px] text-white"
            style="border-color: var(--bg-inbox)"
            :class="sourceBadgeClass(chat.channel)"
          >
            <Send v-if="chat.channel === 'telegram'" class="h-2 w-2" />
            <MessageCircleMore v-else class="h-2 w-2" />
          </div>
        </div>
        <div class="min-w-0 flex-1">
          <div class="mb-0.5 flex items-center justify-between gap-2">
            <span class="truncate text-[13.5px] font-semibold" style="color: var(--text-primary)">{{ chat.name }}</span>
            <div class="flex shrink-0 items-center gap-2">
              <span
                v-if="(chat.unreadCount ?? 0) > 0"
                class="inline-grid shrink-0 place-items-center rounded-full text-[11px] font-semibold tabular-nums tracking-tight text-white shadow-sm ring-1 ring-inset ring-white/20 [line-height:1]"
                :class="unreadBadgeClass(chat.unreadCount ?? 0)"
                style="background: var(--color-brand-200)"
                :title="`Непрочитанных сообщений: ${chat.unreadCount}`"
              >
                {{ (chat.unreadCount ?? 0) > 99 ? '99+' : chat.unreadCount }}
              </span>
              <span class="shrink-0 text-[11px] tabular-nums leading-none" style="color: var(--text-muted)">{{ chat.time }}</span>
            </div>
          </div>
          <p class="truncate text-[12.5px] leading-snug" style="color: var(--text-secondary)">
            {{ chat.message }}
          </p>
          <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
            <span
              class="rounded-full px-1.5 py-px text-[10px] font-semibold uppercase tracking-wide"
              :style="(chat.status ?? 'open') === 'open'
                ? { background: 'rgba(34,197,94,0.1)', color: '#16a34a' }
                : { background: 'rgba(148,163,184,0.15)', color: 'var(--text-muted)' }"
            >
              {{ (chat.status ?? 'open') === 'open' ? 'Открыт' : 'Закрыт' }}
            </span>
            <span
              v-if="chat.department"
              class="max-w-[140px] truncate rounded-full px-1.5 py-px text-[10px] font-semibold"
              style="background: var(--color-brand-50); color: var(--color-brand-200)"
              :title="chat.department"
            >
              {{ chat.department }}
            </span>
            <span
              v-if="isMutedUntilActive(chat.muted_until)"
              class="rounded-full px-1.5 py-px text-[10px] font-semibold"
              style="background: rgba(148,163,184,0.2); color: var(--text-muted)"
            >
              Без звука
            </span>
          </div>
        </div>
        <button
          type="button"
          class="no-drag-region flex h-8 w-8 shrink-0 items-center justify-center rounded-md opacity-0 transition hover:bg-white/[0.08] group-hover:opacity-100"
          style="color: var(--text-muted)"
          title="Действия"
          @click.stop="openMenuAt(chat.id, $event)"
        >
          <MoreVertical class="h-4 w-4" aria-hidden="true" />
        </button>
      </article>
    </div>

    <Teleport to="body">
      <div
        v-if="menuOpen"
        class="fixed z-[200] min-w-[220px] rounded-[var(--radius-md)] border py-1 text-[13px] shadow-lg"
        style="background: var(--bg-inbox); border-color: var(--border-light); color: var(--text-primary)"
        :style="{ left: `${menuX}px`, top: `${menuY}px` }"
        @click.stop
      >
        <button
          type="button"
          class="flex w-full px-3 py-2 text-left text-[13px] transition hover:bg-[var(--bg-card-hover)]"
          @click="pickAssign"
        >
          Назначить на меня
        </button>
        <button
          type="button"
          class="flex w-full px-3 py-2 text-left text-[13px] transition hover:bg-[var(--bg-card-hover)]"
          @click="pickClose"
        >
          Закрыть чат
        </button>
        <div class="my-1 h-px" style="background: var(--border-light)" />
        <button
          v-if="!isMenuChatMuted()"
          type="button"
          class="flex w-full px-3 py-2 text-left text-[13px] transition hover:bg-[var(--bg-card-hover)]"
          @click="pickMute('1h')"
        >
          Без звука: 1 час
        </button>
        <button
          v-if="!isMenuChatMuted()"
          type="button"
          class="flex w-full px-3 py-2 text-left text-[13px] transition hover:bg-[var(--bg-card-hover)]"
          @click="pickMute('8h')"
        >
          Без звука: 8 часов
        </button>
        <button
          v-if="!isMenuChatMuted()"
          type="button"
          class="flex w-full px-3 py-2 text-left text-[13px] transition hover:bg-[var(--bg-card-hover)]"
          @click="pickMute('forever')"
        >
          Без звука: навсегда
        </button>
        <button
          v-if="isMenuChatMuted()"
          type="button"
          class="flex w-full px-3 py-2 text-left text-[13px] transition hover:bg-[var(--bg-card-hover)]"
          @click="pickMute('unmute')"
        >
          Включить звук уведомлений
        </button>
      </div>
    </Teleport>
  </section>
</template>

<style scoped>
.chat-item-m:hover {
  background: var(--bg-card-hover);
}
.chat-item-active {
  background: var(--color-brand-50);
  border-color: rgba(85, 23, 94, 0.1) !important;
}
[data-theme="dark"] .chat-item-active {
  background: rgba(154, 95, 168, 0.1);
  border-color: rgba(154, 95, 168, 0.15) !important;
}
[data-unread="1"] .font-semibold {
  font-weight: 700;
}
</style>
