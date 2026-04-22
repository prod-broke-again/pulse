<script setup lang="ts">
import { ref, watch, computed, onMounted, onBeforeUnmount } from 'vue'
import {
  Search,
  Send,
  MessageCircle,
  MessageCircleMore,
  Archive,
  Loader2,
  MoreVertical,
  UserPlus,
  CircleCheck,
  Clock,
  Infinity,
  Volume2,
  ChevronDown,
  RotateCcw,
} from 'lucide-vue-next'
import { resolveDepartmentIcon } from '../../constants/departmentIcons'
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

const props = defineProps<{
  conversations: Conversation[]
  /** Для скрытия «Назначить на меня», если чат уже у текущего пользователя. */
  currentUserId?: number | null
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
  (e: 'change-status', status: 'open' | 'closed' | 'all'): void
  (e: 'assign-me', chatId: number): void
  (e: 'close-chat', chatId: number): void
  /** Закрытый чат: вернуть в работу (тот же API, что «назначить на меня»). */
  (e: 'reopen-chat', chatId: number): void
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
  get: () => chatStore.filters.status ?? 'open',
  set: (v: 'open' | 'closed' | 'all') => {
    void chatStore.setFilters({ status: v })
  },
})

const menuConversation = computed((): Conversation | null => {
  const id = menuChatId.value
  if (id == null) {
    return null
  }
  return props.conversations.find((c) => c.id === id) ?? null
})

/** Показывать пункт назначения, если чат не у текущего пользователя (или id неизвестен). */
const showAssignMenuItem = computed((): boolean => {
  const c = menuConversation.value
  if (!c) {
    return true
  }
  const me = props.currentUserId
  if (me == null) {
    return true
  }
  if (c.assignedTo == null) {
    return true
  }
  return c.assignedTo !== me
})

const assignMenuLabel = computed((): string => {
  const c = menuConversation.value
  if (!c) {
    return 'Назначить на меня'
  }
  const me = props.currentUserId
  if (me == null) {
    return 'Назначить на меня'
  }
  if (c.assignedTo == null) {
    return 'Назначить на меня'
  }
  if (c.assignedTo === me) {
    return 'Назначить на меня'
  }
  const name = c.assignee?.name?.trim() ?? ''
  return name !== '' ? `Забрать себе (у ${name})` : 'Забрать себе'
})

function isMenuChatMuted(): boolean {
  const row = menuConversation.value
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
  const c = menuConversation.value
  if (c && (c.status ?? 'open') === 'closed') {
    emit('reopen-chat', id)
  } else {
    emit('close-chat', id)
  }
  closeMenu()
}

const closeOrReopenMenuLabel = computed((): string => {
  const c = menuConversation.value
  if (c && (c.status ?? 'open') === 'closed') {
    return 'Вернуть в работу'
  }
  return 'Закрыть чат'
})

const isMenuChatClosed = computed((): boolean => {
  const c = menuConversation.value
  return c != null && (c.status ?? 'open') === 'closed'
})

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
          class="inbox-total-badge rounded-full px-2 py-0.5 text-xs font-semibold"
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
            class="tab-count-badge min-w-[18px] rounded-full px-1 text-[10px] font-semibold leading-tight"
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
            class="tab-count-badge min-w-[18px] rounded-full px-1 text-[10px] font-semibold leading-tight"
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
            class="tab-count-badge min-w-[18px] rounded-full px-1 text-[10px] font-semibold leading-tight"
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
            class="h-10 w-full rounded-[var(--radius-md)] border py-2 pl-9 pr-2.5 text-[13px] leading-normal outline-none transition"
            style="border-color: var(--border-light); background: var(--bg-thread); color: var(--text-primary)"
          >
        </div>
        <div class="relative isolate shrink-0">
          <select
            v-model="statusSelect"
            class="h-10 min-w-[124px] cursor-pointer appearance-none rounded-[var(--radius-md)] border py-0 pl-2.5 pr-8 text-[13px] leading-normal outline-none transition"
            style="border-color: var(--border-light); background: var(--bg-thread); color: var(--text-primary)"
            aria-label="Фильтр по статусу обращения"
          >
            <option value="open">
              Открытые
            </option>
            <option value="closed">
              Закрытые
            </option>
            <option value="all">
              Все
            </option>
          </select>
          <ChevronDown
            class="pointer-events-none absolute right-2 top-1/2 z-10 h-3.5 w-3.5 -translate-y-1/2"
            style="color: var(--text-muted)"
            aria-hidden="true"
          />
        </div>
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
        :class="[
          chat.id === selectedChatId ? 'chat-item-active' : '',
          (chat.status ?? 'open') === 'closed' ? 'chat-item-closed' : '',
        ]"
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
            <Send v-if="chat.channel === 'telegram' || chat.channel === 'tg'" class="h-2 w-2" />
            <MessageCircleMore v-else class="h-2 w-2" />
          </div>
        </div>
        <div class="min-w-0 flex-1">
          <div class="mb-0.5 flex min-w-0 items-center justify-between gap-2">
            <div class="flex min-w-0 flex-1 items-center gap-1.5">
              <span
                class="inline-flex shrink-0"
                :title="(chat.status ?? 'open') === 'open' ? 'Чат открыт' : 'Чат закрыт'"
                :aria-label="(chat.status ?? 'open') === 'open' ? 'Чат открыт' : 'Чат закрыт'"
              >
                <MessageCircle
                  v-if="(chat.status ?? 'open') === 'open'"
                  class="h-3.5 w-3.5 shrink-0"
                  style="color: var(--status-open)"
                  aria-hidden="true"
                />
                <Archive
                  v-else
                  class="h-3.5 w-3.5 shrink-0"
                  style="color: var(--status-closed)"
                  aria-hidden="true"
                />
              </span>
              <span class="truncate text-[13.5px] font-semibold" style="color: var(--text-primary)">{{ chat.name }}</span>
            </div>
            <div class="flex shrink-0 items-center gap-2">
              <span
                v-if="(chat.unreadCount ?? 0) > 0"
                class="unread-count-badge inline-grid shrink-0 place-items-center rounded-full text-[11px] font-semibold tabular-nums tracking-tight text-white shadow-sm ring-1 ring-inset ring-white/20 [line-height:1]"
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
          <div class="mt-1.5 flex w-full min-w-0 flex-wrap items-center gap-1.5">
            <span
              v-if="chat.department"
              class="inbox-meta-chip inbox-meta-chip-dept inline-flex min-h-[1.25rem] min-w-0 max-w-full flex-1 basis-[min(100%,12rem)] items-center gap-0.5"
              :title="`Отдел: ${chat.department}`"
            >
              <component :is="resolveDepartmentIcon(chat.departmentIcon)" class="h-3 w-3 shrink-0 opacity-90" />
              <span class="min-w-0 flex-1 truncate">{{ chat.department }}</span>
            </span>
            <span
              v-if="isMutedUntilActive(chat.muted_until)"
              class="inline-flex shrink-0 rounded-full px-1.5 py-px text-[10px] font-semibold"
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
          v-if="showAssignMenuItem"
          type="button"
          class="dropdown-item w-full text-left"
          @click="pickAssign"
        >
          <UserPlus class="h-4 w-4 shrink-0" />
          {{ assignMenuLabel }}
        </button>
        <button
          type="button"
          class="dropdown-item w-full"
          :class="isMenuChatClosed
            ? '!text-emerald-600 hover:!bg-emerald-500/10 [&_svg]:!text-emerald-600'
            : '!text-red-500 hover:!bg-red-500/10 [&_svg]:!text-red-500'"
          @click="pickClose"
        >
          <CircleCheck v-if="!isMenuChatClosed" class="h-4 w-4 shrink-0" />
          <RotateCcw v-else class="h-4 w-4 shrink-0" />
          {{ closeOrReopenMenuLabel }}
        </button>
        <div class="my-1 h-px" style="background: var(--border-light)" />
        <button
          v-if="!isMenuChatMuted()"
          type="button"
          class="dropdown-item w-full text-left"
          @click="pickMute('1h')"
        >
          <Clock class="h-4 w-4 shrink-0" />
          Без звука: 1 час
        </button>
        <button
          v-if="!isMenuChatMuted()"
          type="button"
          class="dropdown-item w-full text-left"
          @click="pickMute('8h')"
        >
          <Clock class="h-4 w-4 shrink-0" />
          Без звука: 8 часов
        </button>
        <button
          v-if="!isMenuChatMuted()"
          type="button"
          class="dropdown-item w-full text-left"
          @click="pickMute('forever')"
        >
          <Infinity class="h-4 w-4 shrink-0" />
          Без звука: навсегда
        </button>
        <button
          v-if="isMenuChatMuted()"
          type="button"
          class="dropdown-item w-full text-left"
          @click="pickMute('unmute')"
        >
          <Volume2 class="h-4 w-4 shrink-0" />
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

.chat-item-closed {
  background: color-mix(in srgb, var(--text-primary) 4%, var(--bg-inbox));
}

.chat-item-closed.chat-item-m:hover {
  background: color-mix(in srgb, var(--text-primary) 7%, var(--bg-card-hover));
}

.chat-item-active {
  background: var(--color-brand-50);
  border-color: rgba(85, 23, 94, 0.1) !important;
}
[data-theme="dark"] .chat-item-active {
  background: rgba(154, 95, 168, 0.1);
  border-color: rgba(154, 95, 168, 0.15) !important;
}

.chat-item-closed.chat-item-active {
  background: color-mix(in srgb, var(--color-brand-200) 16%, var(--bg-inbox));
  border-color: rgba(85, 23, 94, 0.12) !important;
}
[data-theme="dark"] .chat-item-closed.chat-item-active {
  background: rgba(154, 95, 168, 0.14);
  border-color: rgba(154, 95, 168, 0.2) !important;
}

[data-theme="dark"] .chat-item-closed {
  background: color-mix(in srgb, #000000 28%, var(--bg-inbox));
}
[data-theme="dark"] .chat-item-closed.chat-item-m:hover {
  background: color-mix(in srgb, #000000 18%, var(--bg-card-hover));
}

[data-theme="dark"] .tab-count-badge {
  background: color-mix(in srgb, var(--color-brand-200) 28%, transparent) !important;
  color: #d6b7f5 !important;
}

[data-theme="dark"] .inbox-total-badge {
  background: color-mix(in srgb, var(--color-brand-200) 28%, transparent) !important;
  color: #d6b7f5 !important;
}

[data-theme="dark"] .unread-count-badge {
  background: color-mix(in srgb, var(--color-brand-200) 46%, #1f1230) !important;
  color: #f3e8ff !important;
}

.inbox-meta-chip {
  padding: 0.125rem 0.4rem;
  border-radius: var(--radius-sm);
  font-size: 10px;
  font-weight: 600;
  line-height: 1.25;
  border: 1px solid transparent;
}

.inbox-meta-chip-dept {
  color: var(--color-brand-200);
  background: color-mix(in srgb, var(--color-brand-200) 12%, var(--bg-inbox));
  border-color: color-mix(in srgb, var(--color-brand-200) 38%, var(--border-light));
}

[data-theme="dark"] .inbox-meta-chip-dept {
  background: color-mix(in srgb, var(--color-brand-200) 16%, transparent);
  border-color: color-mix(in srgb, var(--color-brand-200) 42%, var(--border-light));
}

[data-unread="1"] .font-semibold {
  font-weight: 700;
}
</style>
