<script setup lang="ts">
import { computed, ref, onMounted, onBeforeUnmount, watch } from 'vue'
import {
  UserPlus,
  ArrowRightLeft,
  CircleCheck,
  Copy,
  EllipsisVertical,
  Hash,
  Loader2,
  MessageCircleMore,
  RotateCw,
  RotateCcw,
  Send,
  Sparkles,
} from 'lucide-vue-next'
import type { Conversation, ConversationChannel } from '../../types/chat'
import { fetchDepartments, type DepartmentOption } from '../../api/departments'
import { resolveDepartmentIcon } from '../../constants/departmentIcons'

const props = defineProps<{
  activeConversation: Conversation
  currentUserId: number | null
  syncHistoryLoading?: boolean
}>()

const emit = defineEmits<{
  (e: 'assign-me'): void
  (e: 'close-chat'): void
  (e: 'reopen-chat'): void
  (e: 'change-department', departmentId: number): void
  (e: 'sync-history'): void
  (e: 'select-messages-for-copy'): void
}>()

const showMenu = ref(false)
const departmentExpanded = ref(false)
const showAssigneePopover = ref(false)
const menuRef = ref<HTMLElement | null>(null)
const assignBtnRef = ref<HTMLElement | null>(null)
const departments = ref<DepartmentOption[]>([])
const departmentsLoading = ref(false)

const assignedToOther = computed(() => {
  const a = props.activeConversation.assignedTo
  const me = props.currentUserId
  if (a == null || me == null) {
    return false
  }
  return a !== me
})

const assignButtonDisabled = computed(() => {
  const a = props.activeConversation.assignedTo
  const me = props.currentUserId
  if (a == null) {
    return false
  }
  if (me != null && a === me) {
    return true
  }
  return false
})

const assignButtonTitle = computed(() => {
  const a = props.activeConversation.assignedTo
  const me = props.currentUserId
  if (a == null) {
    return 'Назначить на себя'
  }
  if (me != null && a === me) {
    return 'Уже в работе у вас'
  }
  const name = props.activeConversation.assignee?.name?.trim() ?? ''
  return name !== '' ? `Забрать себе (сейчас у: ${name})` : 'Забрать себе (чат у другого модератора)'
})

const assignButtonLabel = computed(() => {
  const a = props.activeConversation.assignedTo
  const me = props.currentUserId
  if (me != null && a === me) {
    return 'У вас'
  }
  if (a == null) {
    return 'Назначить'
  }
  return 'Забрать себе'
})

const isChatClosed = computed(
  () => (props.activeConversation.status ?? 'open') === 'closed',
)

const closeOrReopenMenuLabel = computed(() =>
  isChatClosed.value ? 'Вернуть в работу' : 'Закрыть чат',
)

function sourceBadgeClass(channel: ConversationChannel): string {
  if (channel === 'telegram' || channel === 'tg') {
    return 'bg-[#2AABEE]'
  }
  if (channel === 'vk') {
    return 'bg-[#4C75A3]'
  }
  return 'bg-[var(--color-brand-200)]'
}

function channelSourceTitle(ch: ConversationChannel): string {
  if (ch === 'telegram' || ch === 'tg') return 'Источник: Telegram'
  if (ch === 'vk') return 'Источник: VK'
  return 'Источник: Web'
}

function onDocumentClick(e: MouseEvent): void {
  const t = e.target as Node
  if (menuRef.value && !menuRef.value.contains(t)) {
    showMenu.value = false
  }
  if (assignBtnRef.value && !assignBtnRef.value.contains(t)) {
    showAssigneePopover.value = false
  }
}

async function loadDepartments(): Promise<void> {
  const sid = props.activeConversation.sourceId
  if (sid == null) {
    departments.value = []
    return
  }
  departmentsLoading.value = true
  try {
    departments.value = await fetchDepartments(sid)
  } catch {
    departments.value = []
  } finally {
    departmentsLoading.value = false
  }
}

watch(departmentExpanded, (open) => {
  if (open) {
    void loadDepartments()
  }
})

watch(showMenu, (open) => {
  if (!open) {
    departmentExpanded.value = false
  }
})

function toggleMoreMenu(e: MouseEvent): void {
  e.stopPropagation()
  showMenu.value = !showMenu.value
}

function toggleDepartmentSection(e: MouseEvent): void {
  e.stopPropagation()
  departmentExpanded.value = !departmentExpanded.value
}

function selectDepartment(departmentId: number): void {
  showMenu.value = false
  departmentExpanded.value = false
  emit('change-department', departmentId)
}

function onAssignClick(): void {
  if (assignButtonDisabled.value) {
    return
  }
  showMenu.value = false
  emit('assign-me')
}

function onCloseOrReopenFromMenu(): void {
  showMenu.value = false
  if (isChatClosed.value) {
    emit('reopen-chat')
  } else {
    emit('close-chat')
  }
}

function onSyncFromMenu(): void {
  if (props.syncHistoryLoading) {
    return
  }
  showMenu.value = false
  emit('sync-history')
}

function onCopyFromMenu(): void {
  showMenu.value = false
  emit('select-messages-for-copy')
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
})
</script>

<template>
  <header class="thread-header">
    <div class="thread-header-left">
      <div
        class="relative flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-semibold text-white"
        :class="activeConversation.clientAvatarUrl ? 'overflow-hidden p-0' : ''"
        style="background: var(--color-brand-200)"
      >
        <img
          v-if="activeConversation.clientAvatarUrl"
          :src="activeConversation.clientAvatarUrl"
          :alt="activeConversation.name"
          class="h-full w-full object-cover"
        >
        <template v-else>
          {{ activeConversation.initials }}
        </template>
        <div
          class="absolute -bottom-0.5 -right-0.5 z-[1] flex h-4 w-4 items-center justify-center rounded-full border-2 text-[8px] text-white"
          style="border-color: var(--bg-inbox)"
          :class="sourceBadgeClass(activeConversation.channel)"
          :title="channelSourceTitle(activeConversation.channel)"
        >
          <Send
            v-if="activeConversation.channel === 'telegram' || activeConversation.channel === 'tg'"
            class="h-2 w-2"
            aria-hidden="true"
          />
          <MessageCircleMore v-else class="h-2 w-2" aria-hidden="true" />
        </div>
      </div>
      <div class="thread-user-info">
        <div class="thread-user-name">
          {{ activeConversation.name }}
        </div>
        <div class="thread-user-meta">
          <span class="inline-flex items-center gap-0">
            <Hash class="h-3 w-3 shrink-0 -mr-px" />
            {{ activeConversation.id }}
          </span>
          <span
            v-if="activeConversation.department"
            class="meta-chip meta-chip-dept inline-flex min-w-0 max-w-full items-center gap-0.5 sm:max-w-[min(280px,45vw)]"
            :title="`Отдел: ${activeConversation.department}`"
          >
            <component
              :is="resolveDepartmentIcon(activeConversation.departmentIcon)"
              class="h-3 w-3 shrink-0 opacity-90"
            />
            <span class="min-w-0 flex-1 truncate">{{ activeConversation.department }}</span>
          </span>
          <span
            v-if="activeConversation.topic"
            class="meta-chip meta-chip-topic inline-flex min-w-0 max-w-full items-center gap-0.5 sm:max-w-[min(280px,45vw)]"
            :title="`Тема (AI): ${activeConversation.topic}`"
          >
            <Sparkles class="h-3 w-3 shrink-0 opacity-90" />
            <span class="min-w-0 flex-1 truncate">{{ activeConversation.topic }}</span>
          </span>
        </div>
      </div>
    </div>

    <div class="thread-actions">
      <div
        ref="assignBtnRef"
        class="relative"
        @mouseenter="assignedToOther ? (showAssigneePopover = true) : null"
        @mouseleave="showAssigneePopover = false"
      >
        <button
          type="button"
          class="btn btn-secondary"
          :disabled="assignButtonDisabled"
          :title="assignButtonTitle"
          @click="onAssignClick"
        >
          <UserPlus class="h-3.5 w-3.5" />
          {{ assignButtonLabel }}
        </button>
        <div
          v-if="assignedToOther && showAssigneePopover && activeConversation.assignee"
          class="assignee-popover absolute left-0 top-full z-50 mt-1 flex min-w-[220px] items-center gap-2 rounded-[var(--radius-md)] border px-3 py-2 shadow-lg"
          style="background: var(--bg-inbox); border-color: var(--border-light)"
          role="tooltip"
        >
          <img
            v-if="activeConversation.assignee.avatar_url"
            :src="activeConversation.assignee.avatar_url"
            alt=""
            class="h-9 w-9 shrink-0 rounded-full object-cover"
          >
          <div
            v-else
            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-semibold text-white"
            style="background: var(--color-brand-200)"
          >
            {{ activeConversation.assignee.name.slice(0, 2).toUpperCase() }}
          </div>
          <div class="min-w-0">
            <div class="text-[11px] font-medium opacity-70">
              В работе у
            </div>
            <div class="truncate text-sm font-semibold">
              {{ activeConversation.assignee.name }}
            </div>
          </div>
        </div>
      </div>

      <div ref="menuRef" class="relative">
        <button
          type="button"
          class="btn btn-icon btn-secondary"
          title="Действия"
          @click="toggleMoreMenu"
        >
          <EllipsisVertical class="h-4 w-4" />
        </button>
        <div
          v-if="showMenu"
          class="dropdown-menu absolute right-0 top-full z-30 mt-1 min-w-[240px] overflow-hidden rounded-[var(--radius-md)] border py-1 shadow-lg"
          style="background: var(--bg-inbox); border-color: var(--border-light)"
          role="menu"
        >
          <button
            type="button"
            class="dropdown-item"
            @click="onCopyFromMenu"
          >
            <Copy class="h-4 w-4 shrink-0" />
            Выбрать для копирования
          </button>
          <button
            type="button"
            class="dropdown-item"
            :disabled="syncHistoryLoading"
            @click="onSyncFromMenu"
          >
            <Loader2 v-if="syncHistoryLoading" class="h-4 w-4 shrink-0 animate-spin" />
            <RotateCw v-else class="h-4 w-4 shrink-0" />
            Синхронизировать
          </button>
          <div class="border-t" style="border-color: var(--border-light)" />
          <button
            type="button"
            class="dropdown-item w-full justify-between"
            @click="toggleDepartmentSection"
          >
            <span class="inline-flex items-center gap-2">
              <ArrowRightLeft class="h-4 w-4 shrink-0" />
              Отдел
            </span>
          </button>
          <div
            v-if="departmentExpanded"
            class="max-h-[220px] overflow-y-auto border-t px-1 py-1"
            style="border-color: var(--border-light); background: var(--bg-app)"
          >
            <div v-if="departmentsLoading" class="flex justify-center px-3 py-4">
              <Loader2 class="h-5 w-5 animate-spin" style="color: var(--color-brand)" />
            </div>
            <template v-else-if="departments.length === 0">
              <div class="px-3 py-2 text-[12px]" style="color: var(--text-muted)">
                Нет доступных отделов
              </div>
            </template>
            <template v-else>
              <button
                v-for="d in departments"
                :key="d.id"
                type="button"
                class="dropdown-item text-left"
                role="menuitem"
                @click="selectDepartment(d.id)"
              >
                <component :is="resolveDepartmentIcon(d.icon)" class="h-4 w-4 shrink-0" />
                {{ d.name }}
              </button>
            </template>
          </div>
          <div class="border-t" style="border-color: var(--border-light)" />
          <button
            type="button"
            class="dropdown-item"
            :class="isChatClosed
              ? '!text-emerald-600 hover:!bg-emerald-500/10 [&_svg]:!text-emerald-600'
              : '!text-red-500 hover:!bg-red-500/10 [&_svg]:!text-red-500'"
            @click="onCloseOrReopenFromMenu"
          >
            <RotateCcw v-if="isChatClosed" class="h-4 w-4 shrink-0" />
            <CircleCheck v-else class="h-4 w-4 shrink-0" />
            {{ closeOrReopenMenuLabel }}
          </button>
        </div>
      </div>
    </div>
  </header>
</template>

<style scoped>
.assignee-popover {
  pointer-events: auto;
}

.meta-chip {
  display: inline-flex;
  align-items: center;
  gap: 0.35rem;
  padding: 0.2rem 0.45rem;
  border-radius: var(--radius-sm);
  font-size: 11px;
  font-weight: 600;
  line-height: 1.25;
  border: 1px solid transparent;
}

.meta-chip-dept {
  color: var(--color-brand-200);
  background: color-mix(in srgb, var(--color-brand-200) 12%, var(--bg-inbox));
  border-color: color-mix(in srgb, var(--color-brand-200) 38%, var(--border-light));
}

.meta-chip-topic {
  color: var(--text-secondary);
  background: color-mix(in srgb, var(--color-brand-200) 8%, var(--bg-inbox));
  border-color: color-mix(in srgb, var(--color-brand-200) 28%, var(--border-light));
}

[data-theme='dark'] .meta-chip-dept {
  background: color-mix(in srgb, var(--color-brand-200) 16%, transparent);
  border-color: color-mix(in srgb, var(--color-brand-200) 42%, var(--border-light));
}

[data-theme='dark'] .meta-chip-topic {
  color: var(--text-secondary);
  background: color-mix(in srgb, var(--color-brand-200) 10%, transparent);
  border-color: color-mix(in srgb, var(--color-brand-200) 30%, var(--border-light));
}
</style>
