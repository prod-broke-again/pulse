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
  RotateCw,
  Sparkles,
} from 'lucide-vue-next'
import type { Conversation } from '../../types/chat'
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
  (e: 'change-department', departmentId: number): void
  (e: 'sync-history'): void
  (e: 'select-messages-for-copy'): void
}>()

const showMenu = ref(false)
const showDepartmentMenu = ref(false)
const showAssigneePopover = ref(false)
const menuRef = ref<HTMLElement | null>(null)
const departmentRef = ref<HTMLElement | null>(null)
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

function onDocumentClick(e: MouseEvent): void {
  const t = e.target as Node
  if (menuRef.value && !menuRef.value.contains(t)) {
    showMenu.value = false
  }
  if (departmentRef.value && !departmentRef.value.contains(t)) {
    showDepartmentMenu.value = false
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

watch(showDepartmentMenu, (open) => {
  if (open) {
    void loadDepartments()
  }
})

function toggleDepartmentMenu(e: MouseEvent): void {
  e.stopPropagation()
  showDepartmentMenu.value = !showDepartmentMenu.value
  if (showDepartmentMenu.value) {
    showMenu.value = false
  }
}

function toggleMoreMenu(e: MouseEvent): void {
  e.stopPropagation()
  showMenu.value = !showMenu.value
  if (showMenu.value) {
    showDepartmentMenu.value = false
  }
}

function selectDepartment(departmentId: number): void {
  showDepartmentMenu.value = false
  emit('change-department', departmentId)
}

function onAssignClick(): void {
  if (assignButtonDisabled.value) {
    return
  }
  showDepartmentMenu.value = false
  emit('assign-me')
}

function onCloseChatClick(): void {
  showDepartmentMenu.value = false
  showMenu.value = false
  emit('close-chat')
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
})

function channelLabel(ch: Conversation['channel']): string {
  if (ch === 'telegram' || ch === 'tg') return 'Telegram'
  if (ch === 'vk') return 'VK'
  return 'Web'
}
</script>

<template>
  <header class="thread-header">
    <div class="thread-header-left">
      <div
        v-if="activeConversation.clientAvatarUrl"
        class="thread-user-avatar overflow-hidden p-0"
      >
        <img
          :src="activeConversation.clientAvatarUrl"
          :alt="activeConversation.name"
          class="h-full w-full object-cover"
        >
      </div>
      <div
        v-else
        class="thread-user-avatar"
        style="background: var(--color-brand-200)"
      >
        {{ activeConversation.initials }}
      </div>
      <div class="thread-user-info">
        <div class="thread-user-name">
          {{ activeConversation.name }}
        </div>
        <div class="thread-user-meta">
          <span class="inline-flex items-center gap-1">
            <Hash class="h-3 w-3 shrink-0" />
            {{ activeConversation.id }}
          </span>
          <span>{{ channelLabel(activeConversation.channel) }}</span>
          <span v-if="activeConversation.department" class="inline-flex items-center gap-1" title="Отдел">
            <component
              :is="resolveDepartmentIcon(activeConversation.departmentIcon)"
              class="h-3 w-3 shrink-0"
            />
            {{ activeConversation.department }}
          </span>
          <span
            v-if="activeConversation.topic"
            class="inline-flex max-w-[min(280px,45vw)] items-center gap-1 truncate"
            :title="`Тема (AI): ${activeConversation.topic}`"
          >
            <Sparkles class="h-3 w-3 shrink-0" />
            {{ activeConversation.topic }}
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

      <button
        type="button"
        class="btn btn-secondary"
        :disabled="syncHistoryLoading"
        title="Синхронизировать с мессенджером (история / метаданные)"
        @click="emit('sync-history')"
      >
        <Loader2 v-if="syncHistoryLoading" class="h-3.5 w-3.5 animate-spin" />
        <RotateCw v-else class="h-3.5 w-3.5" />
        Синхр.
      </button>

      <div ref="departmentRef" class="relative">
        <button
          type="button"
          class="btn btn-secondary"
          title="Отдел"
          @click="toggleDepartmentMenu"
        >
          <ArrowRightLeft class="h-3.5 w-3.5" />
          Отдел
        </button>
        <div
          v-if="showDepartmentMenu"
          class="dropdown-menu absolute left-0 top-full z-40 mt-1 min-w-[220px] overflow-hidden rounded-[var(--radius-md)] border py-1 shadow-lg"
          style="background: var(--bg-inbox); border-color: var(--border-light)"
          role="menu"
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
              class="dropdown-item"
              role="menuitem"
              @click="selectDepartment(d.id)"
            >
              <component :is="resolveDepartmentIcon(d.icon)" class="h-4 w-4 shrink-0" />
              {{ d.name }}
            </button>
          </template>
        </div>
      </div>
      <button type="button" class="btn btn-danger" @click="onCloseChatClick">
        <CircleCheck class="h-3.5 w-3.5" />
        Закрыть
      </button>
      <div ref="menuRef" class="relative">
        <button
          type="button"
          class="btn btn-icon btn-secondary"
          title="Ещё"
          @click="toggleMoreMenu"
        >
          <EllipsisVertical class="h-4 w-4" />
        </button>
        <div
          v-if="showMenu"
          class="dropdown-menu absolute right-0 top-full z-30 mt-1 min-w-[180px] overflow-hidden rounded-[var(--radius-md)] border py-1 shadow-lg"
          style="background: var(--bg-inbox); border-color: var(--border-light)"
        >
          <button
            type="button"
            class="dropdown-item"
            @click="showMenu = false; emit('select-messages-for-copy')"
          >
            <Copy class="h-4 w-4 shrink-0" />
            Выбрать для копирования
          </button>
          <button
            type="button"
            class="dropdown-item"
            :disabled="assignButtonDisabled"
            @click="showMenu = false; onAssignClick()"
          >
            <UserPlus class="h-4 w-4 shrink-0" />
            {{ assignButtonLabel }}
          </button>
          <button
            type="button"
            class="dropdown-item"
            @click="showMenu = false; onCloseChatClick()"
          >
            <CircleCheck class="h-4 w-4 shrink-0" />
            Завершить чат
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
</style>
