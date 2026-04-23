<script setup lang="ts">
import {
  ArrowRightLeft,
  ChevronLeft,
  CircleCheck,
  Copy,
  EllipsisVertical,
  History,
  Loader2,
  RefreshCw,
  RotateCcw,
  RotateCw,
  UserPlus,
} from 'lucide-vue-next'
import { Teleport, computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { resolveDepartmentIcon } from '../../constants/departmentIcons'
import { useRouter } from 'vue-router'
import * as chatApi from '../../api/chatRepository'
import { parseApiChatId } from '../../lib/chatIds'
import type { ChatThreadMeta, ChannelSource } from '../../types/chat'
import { useUiStore } from '../../stores/uiStore'
import ChannelGlyph from '../common/ChannelGlyph.vue'
import { useAuthStore } from '../../stores/authStore'
import { useChatStore } from '../../stores/chatStore'

const props = defineProps<{
  meta: ChatThreadMeta
}>()

const emit = defineEmits<{
  selectForCopy: []
}>()

const router = useRouter()
const auth = useAuthStore()
const chat = useChatStore()

const showMenu = ref(false)
const menuRef = ref<HTMLElement | null>(null)
const headerAction = ref<'assign' | 'close' | null>(null)
const syncLoading = ref(false)

const deptSheetOpen = ref(false)
const departmentsLoading = ref(false)
const departments = ref<Awaited<ReturnType<typeof chatApi.fetchDepartments>>>([])

const isChatClosed = computed(() => props.meta.status === 'closed')
const meId = computed(() => auth.user?.id ?? null)

const assignButtonDisabled = computed(() => {
  const a = props.meta.assignedToUserId
  const me = meId.value
  if (a == null) {
    return false
  }
  if (me != null && a === me) {
    return true
  }
  return false
})

const assignButtonTitle = computed(() => {
  const a = props.meta.assignedToUserId
  const me = meId.value
  if (a == null) {
    return 'Назначить на себя'
  }
  if (me != null && a === me) {
    return 'Уже в работе у вас'
  }
  return 'Забрать чат себе (чат у другого модератора)'
})

const assignButtonLabel = computed(() => {
  const a = props.meta.assignedToUserId
  const me = meId.value
  if (me != null && a === me) {
    return 'У вас'
  }
  if (a == null) {
    return 'Назначить'
  }
  return 'Забрать'
})

const showDepartmentPicker = computed(
  () => props.meta.status === 'open' && props.meta.sourceId != null,
)

const closeOrReopenMenuLabel = computed(() =>
  isChatClosed.value ? 'Вернуть в работу' : 'Закрыть чат',
)

function channelColor(ch: ChannelSource) {
  if (ch === 'tg') {
    return '#2AABEE'
  }
  if (ch === 'vk') {
    return '#0077FF'
  }
  if (ch === 'max') {
    return '#6b4f7c'
  }
  return '#8b6b9a'
}

function onDocumentClick(e: MouseEvent) {
  const t = e.target as Node
  if (menuRef.value && !menuRef.value.contains(t)) {
    showMenu.value = false
  }
}

function toggleMenu(e: MouseEvent) {
  e.stopPropagation()
  showMenu.value = !showMenu.value
}

function closeMenu() {
  showMenu.value = false
}

function back() {
  void router.back()
}

async function onAssignMe() {
  if (headerAction.value || assignButtonDisabled.value) {
    return
  }
  headerAction.value = 'assign'
  try {
    await chat.assignToMe()
  } finally {
    headerAction.value = null
  }
}

async function onCloseChat() {
  if (headerAction.value) {
    return
  }
  closeMenu()
  headerAction.value = 'close'
  try {
    await chat.closeThread()
  } finally {
    headerAction.value = null
  }
}

async function onReopenToWork() {
  if (headerAction.value) {
    return
  }
  closeMenu()
  headerAction.value = 'assign'
  try {
    await chat.reopenToWork()
  } finally {
    headerAction.value = null
  }
}

function onCloseOrReopenFromMenu() {
  if (isChatClosed.value) {
    void onReopenToWork()
  } else {
    void onCloseChat()
  }
}

function goClientHistory() {
  closeMenu()
  const eid = props.meta.externalUserId
  if (!eid) {
    return
  }
  void router.push({ name: 'client-history', params: { externalUserId: eid } })
}

function onRefreshThread() {
  closeMenu()
  chat.refreshThread()
}

function onCopyFromMenu() {
  closeMenu()
  emit('selectForCopy')
}

async function onSyncFromMenu() {
  if (syncLoading.value) {
    return
  }
  closeMenu()
  const id = parseApiChatId(props.meta.id)
  if (id == null) {
    return
  }
  syncLoading.value = true
  const ui = useUiStore()
  try {
    await chatApi.syncChatHistory(id)
    await chat.fetchThread(props.meta.id, { force: true })
    ui.pushToast('История синхронизирована', 'success')
  } catch {
    ui.pushToast('Не удалось синхронизировать', 'error')
  } finally {
    syncLoading.value = false
  }
}

async function openDeptSheet(): Promise<void> {
  const sid = props.meta.sourceId
  if (sid == null) {
    return
  }
  closeMenu()
  deptSheetOpen.value = true
  departmentsLoading.value = true
  try {
    departments.value = await chatApi.fetchDepartments(sid)
  } catch {
    departments.value = []
  } finally {
    departmentsLoading.value = false
  }
}

function closeDeptSheet() {
  deptSheetOpen.value = false
}

function pickDepartment(id: number) {
  if (id === props.meta.departmentId) {
    closeDeptSheet()
    return
  }
  closeDeptSheet()
  void chat.changeDepartment(id)
}

onMounted(() => {
  document.addEventListener('click', onDocumentClick)
})
onBeforeUnmount(() => {
  document.removeEventListener('click', onDocumentClick)
})
</script>

<template>
  <div
    class="flex shrink-0 items-center gap-2 border-b border-[var(--color-gray-line)] bg-white px-2.5 pb-2.5 pt-[calc(10px+var(--safe-top))] dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-850)]"
  >
    <button
      type="button"
      class="flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-[10px] border-none bg-transparent text-base text-[var(--color-brand)] dark:text-[var(--color-brand-200)]"
      aria-label="Назад"
      @click="back()"
    >
      <ChevronLeft class="size-4" />
    </button>
    <div class="min-w-0 flex-1">
      <div
        class="flex items-center gap-1.5 text-[15px] font-semibold text-[var(--color-dark)] dark:text-[var(--zinc-100)]"
      >
        {{ meta.userName }}
        <span
          class="inline-block size-1.5 shrink-0 rounded-full"
          :class="meta.status === 'open' ? 'bg-[#22c55e]' : 'bg-[var(--zinc-400)]'"
          aria-hidden="true"
        />
      </div>
      <div class="flex min-w-0 flex-wrap items-center gap-x-1 gap-y-0.5 text-[11px] text-[var(--zinc-500)]">
        <span class="inline-flex shrink-0 items-center gap-0.5" :style="{ color: channelColor(meta.channel) }">
          <ChannelGlyph :channel="meta.channel" :size="10" />
          {{ meta.channelLabel }}
        </span>
        <span class="text-[var(--zinc-400)]" aria-hidden="true">·</span>
        <span
          class="font-mono tabular-nums text-[var(--zinc-500)]"
          :title="`ID: ${meta.id}`"
        >
          #{{ meta.id }}
        </span>
        <span
          v-if="meta.departmentLabel"
          class="inline-flex min-w-0 max-w-full items-center gap-0.5 truncate text-[var(--zinc-400)]"
        >
          <span aria-hidden="true">·</span>
          <component
            :is="resolveDepartmentIcon(meta.departmentIcon)"
            class="size-2.5 shrink-0"
          />
          <span class="min-w-0 truncate">{{ meta.departmentLabel }}</span>
        </span>
      </div>
    </div>
    <div class="flex shrink-0 items-center gap-1">
      <button
        v-if="meta.status === 'open'"
        type="button"
        class="inline-flex h-10 max-w-[5.5rem] shrink-0 items-center justify-center gap-0.5 rounded-[10px] border-[1.5px] border-[var(--color-gray-line)] bg-white px-2 text-xs font-medium text-[var(--zinc-700)] transition-all active:scale-[0.98] enabled:hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-50 dark:border-[var(--zinc-600)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-200)]"
        :disabled="headerAction !== null || assignButtonDisabled"
        :title="assignButtonTitle"
        @click="onAssignMe"
      >
        <UserPlus
          class="size-3.5 shrink-0"
          :class="{ 'motion-safe:animate-pulse': headerAction === 'assign' }"
          aria-hidden="true"
        />
        <span class="truncate">{{ assignButtonLabel }}</span>
      </button>
      <div ref="menuRef" class="relative shrink-0">
        <button
          type="button"
          class="flex size-10 cursor-pointer items-center justify-center rounded-xl border-none bg-[var(--zinc-100)] text-[var(--zinc-500)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-400)]"
          title="Действия"
          aria-label="Действия"
          :aria-expanded="showMenu"
          @click="toggleMenu"
        >
          <EllipsisVertical class="size-5" aria-hidden="true" />
        </button>
        <div
          v-if="showMenu"
          class="header-actions-dropdown absolute right-0 top-[calc(100%+6px)] z-[60] min-w-[min(calc(100vw-2rem),260px)] overflow-hidden rounded-xl border border-[var(--color-gray-line)] bg-white py-1.5 text-[var(--color-dark)] shadow-lg dark:border-[var(--zinc-600)]/80 dark:bg-[var(--zinc-850)] dark:text-[var(--zinc-100)]"
          role="menu"
          @click.stop
        >
          <button
            v-if="!isChatClosed"
            type="button"
            class="flex w-full items-center gap-2.5 px-3 py-2.5 text-left text-sm text-[var(--color-dark)] active:bg-[var(--zinc-100)] dark:text-[var(--zinc-100)] dark:active:bg-[var(--zinc-800)]"
            @click="onCopyFromMenu"
          >
            <Copy class="size-4 shrink-0" aria-hidden="true" />
            Выбрать для копирования
          </button>
          <button
            type="button"
            class="flex w-full items-center gap-2.5 px-3 py-2.5 text-left text-sm text-[var(--color-dark)] active:bg-[var(--zinc-100)] dark:text-[var(--zinc-100)] dark:active:bg-[var(--zinc-800)]"
            :disabled="chat.threadSyncing"
            @click="onRefreshThread"
          >
            <RefreshCw
              class="size-4 shrink-0"
              :class="{ 'motion-safe:animate-spin': chat.threadSyncing }"
              aria-hidden="true"
            />
            Обновить чат
          </button>
          <button
            v-if="meta.externalUserId"
            type="button"
            class="flex w-full items-center gap-2.5 px-3 py-2.5 text-left text-sm text-[var(--color-dark)] active:bg-[var(--zinc-100)] dark:text-[var(--zinc-100)] dark:active:bg-[var(--zinc-800)]"
            @click="goClientHistory"
          >
            <History class="size-4 shrink-0" aria-hidden="true" />
            История обращений
          </button>
          <button
            v-if="!isChatClosed"
            type="button"
            class="flex w-full items-center gap-2.5 px-3 py-2.5 text-left text-sm text-[var(--color-dark)] active:bg-[var(--zinc-100)] dark:text-[var(--zinc-100)] dark:active:bg-[var(--zinc-800)]"
            :disabled="syncLoading"
            @click="onSyncFromMenu"
          >
            <Loader2
              v-if="syncLoading"
              class="size-4 shrink-0 motion-safe:animate-spin"
              aria-hidden="true"
            />
            <RotateCw v-else class="size-4 shrink-0" aria-hidden="true" />
            Синхронизировать
          </button>
          <div
            v-if="!isChatClosed && showDepartmentPicker"
            class="my-0.5 h-px bg-[var(--zinc-200)] dark:bg-[var(--zinc-700)]/80"
            role="separator"
          />
          <button
            v-if="!isChatClosed && showDepartmentPicker"
            type="button"
            class="flex w-full items-center gap-2.5 px-3 py-2.5 text-left text-sm text-[var(--color-dark)] active:bg-[var(--zinc-100)] dark:text-[var(--zinc-100)] dark:active:bg-[var(--zinc-800)]"
            @click="openDeptSheet"
          >
            <ArrowRightLeft class="size-4 shrink-0" aria-hidden="true" />
            Перевести в отдел
          </button>
          <div class="my-0.5 h-px bg-[var(--zinc-200)] dark:bg-[var(--zinc-700)]/80" role="separator" />
          <button
            type="button"
            class="flex w-full items-center gap-2.5 px-3 py-2.5 text-left text-sm active:bg-[var(--zinc-100)] dark:active:bg-[var(--zinc-800)]"
            :class="isChatClosed ? 'text-emerald-600 dark:text-emerald-400' : 'text-red-600 dark:text-red-400'"
            :disabled="headerAction !== null"
            @click="onCloseOrReopenFromMenu"
          >
            <RotateCcw v-if="isChatClosed" class="size-4 shrink-0" aria-hidden="true" />
            <CircleCheck v-else class="size-4 shrink-0" aria-hidden="true" />
            {{ closeOrReopenMenuLabel }}
          </button>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <div
        v-if="deptSheetOpen"
        class="fixed inset-0 z-[100] flex flex-col justify-end bg-black/40"
        role="dialog"
        aria-modal="true"
        aria-label="Выбор отдела"
        @click.self="closeDeptSheet"
      >
        <div
          class="max-h-[70vh] overflow-y-auto rounded-t-2xl bg-white px-4 pb-[calc(16px+var(--safe-bottom))] pt-4 dark:bg-[var(--zinc-900)]"
          @click.stop
        >
          <div class="mb-3 text-sm font-semibold text-[var(--color-dark)] dark:text-[var(--zinc-100)]">
            Перевести в отдел
          </div>
          <div v-if="departmentsLoading" class="flex justify-center py-8">
            <Loader2 class="size-8 animate-spin text-[var(--color-brand)]" aria-hidden="true" />
          </div>
          <p v-else-if="departments.length === 0" class="py-4 text-center text-sm text-[var(--zinc-500)]">
            Нет доступных отделов
          </p>
          <ul v-else class="space-y-1 pb-2">
            <li v-for="d in departments" :key="d.id">
              <button
                type="button"
                class="flex w-full items-center gap-2 rounded-xl px-3 py-3 text-left text-sm font-medium text-[var(--color-dark)] transition active:bg-[var(--zinc-100)] dark:text-[var(--zinc-100)] dark:active:bg-[var(--zinc-800)]"
                :class="d.id === meta.departmentId ? 'bg-[var(--color-brand-bg)] dark:bg-[var(--zinc-800)]' : ''"
                @click="pickDepartment(d.id)"
              >
                <component
                  :is="resolveDepartmentIcon(d.icon)"
                  class="size-4 shrink-0 text-[var(--color-brand)]"
                />
                {{ d.name }}
              </button>
            </li>
          </ul>
          <button
            type="button"
            class="mt-2 w-full rounded-xl border border-[var(--color-gray-line)] py-2.5 text-sm font-medium text-[var(--zinc-600)] dark:border-[var(--zinc-700)] dark:text-[var(--zinc-300)]"
            @click="closeDeptSheet"
          >
            Отмена
          </button>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.header-actions-dropdown {
  max-height: min(70vh, 400px);
  overflow-y: auto;
  overscroll-behavior: contain;
}
</style>
