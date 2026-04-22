<script setup lang="ts">
import { ChevronLeft, UserPlus, X, Loader2, RotateCw, Copy, History } from 'lucide-vue-next'
import { Teleport, computed, ref } from 'vue'
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

/** Скрываем «назначить на себя», если чат уже на текущем модераторе или закрыт. */
const showAssignButton = computed(() => {
  if (props.meta.status === 'closed') return false
  const uid = auth.user?.id
  if (uid == null) return true
  return props.meta.assignedToUserId !== uid
})

const showCloseButton = computed(() => props.meta.status === 'open')
const showReopenButton = computed(() => props.meta.status === 'closed')
const headerAction = ref<'assign' | 'close' | null>(null)
const syncLoading = ref(false)

const deptSheetOpen = ref(false)
const departmentsLoading = ref(false)

const showDepartmentPicker = computed(
  () => props.meta.status === 'open' && props.meta.sourceId != null,
)

function channelColor(ch: ChannelSource) {
  if (ch === 'tg') return '#2AABEE'
  if (ch === 'vk') return '#0077FF'
  if (ch === 'max') return '#6b4f7c'
  return '#8b6b9a'
}

function back() {
  void router.back()
}

async function onAssignMe() {
  if (headerAction.value) return
  headerAction.value = 'assign'
  try {
    await chat.assignToMe()
  } finally {
    headerAction.value = null
  }
}

async function onCloseChat() {
  if (headerAction.value) return
  headerAction.value = 'close'
  try {
    await chat.closeThread()
  } finally {
    headerAction.value = null
  }
}

async function onReopenToWork() {
  if (headerAction.value) return
  headerAction.value = 'assign'
  try {
    await chat.reopenToWork()
  } finally {
    headerAction.value = null
  }
}

function goClientHistory() {
  const eid = props.meta.externalUserId
  if (!eid) return
  void router.push({ name: 'client-history', params: { externalUserId: eid } })
}

async function onSyncHistory(): Promise<void> {
  const id = parseApiChatId(props.meta.id)
  if (id == null || syncLoading.value) return
  syncLoading.value = true
  const ui = useUiStore()
  try {
    await chatApi.syncChatHistory(id)
    await chat.fetchThread(props.meta.id)
    ui.pushToast('История синхронизирована', 'success')
  } catch {
    ui.pushToast('Не удалось синхронизировать', 'error')
  } finally {
    syncLoading.value = false
  }
}

const departments = ref<Awaited<ReturnType<typeof chatApi.fetchDepartments>>>([])

async function openDeptSheet(): Promise<void> {
  const sid = props.meta.sourceId
  if (sid == null) return
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

function closeDeptSheet(): void {
  deptSheetOpen.value = false
}

function pickDepartment(id: number): void {
  if (id === props.meta.departmentId) {
    closeDeptSheet()
    return
  }
  closeDeptSheet()
  void chat.changeDepartment(id)
}
</script>

<template>
  <div
    class="flex shrink-0 items-center gap-2.5 border-b border-[var(--color-gray-line)] bg-white px-3 pb-2.5 pt-[calc(10px+var(--safe-top))] dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-850)]"
  >
    <button
      type="button"
      class="flex size-9 cursor-pointer items-center justify-center rounded-[10px] border-none bg-transparent text-base text-[var(--color-brand)] dark:text-[var(--color-brand-200)]"
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
          class="inline-block size-1.5 rounded-full"
          :class="meta.status === 'open' ? 'bg-[#22c55e]' : 'bg-[var(--zinc-400)]'"
          aria-hidden="true"
        />
      </div>
      <div class="flex items-center gap-1 text-[11px] text-[var(--zinc-400)]">
        <span class="inline-flex shrink-0" :style="{ color: channelColor(meta.channel) }">
          <ChannelGlyph :channel="meta.channel" :size="10" />
        </span>
        <span :style="{ color: channelColor(meta.channel) }">{{ meta.channelLabel }}</span>
        <button
          v-if="showDepartmentPicker"
          type="button"
          class="inline-flex min-w-0 max-w-full items-center gap-0.5 truncate border-none bg-transparent p-0 text-left text-[11px] text-[var(--zinc-400)] underline decoration-dotted underline-offset-2"
          @click="openDeptSheet()"
        >
          <span aria-hidden="true">·</span>
          <component :is="resolveDepartmentIcon(meta.departmentIcon)" class="size-2.5 shrink-0" />
          {{ meta.departmentLabel }}
        </button>
        <span v-else class="inline-flex min-w-0 max-w-full items-center gap-0.5 truncate">
          <span aria-hidden="true">·</span>
          <component :is="resolveDepartmentIcon(meta.departmentIcon)" class="size-2.5 shrink-0" />
          {{ meta.departmentLabel }}
        </span>
      </div>
    </div>
    <div class="flex gap-1">
      <button
        v-if="meta.externalUserId"
        type="button"
        class="flex cursor-pointer items-center gap-1 rounded-[10px] border-[1.5px] border-[var(--color-gray-line)] bg-white px-2.5 py-1.5 text-xs font-medium text-[var(--zinc-600)] transition-all active:scale-[0.97] dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-300)]"
        aria-label="История обращений клиента"
        @click="goClientHistory"
      >
        <History class="size-3.5" />
      </button>
      <button
        v-if="meta.status === 'open'"
        type="button"
        class="flex cursor-pointer items-center gap-1 rounded-[10px] border-[1.5px] border-[var(--color-gray-line)] bg-white px-3 py-1.5 text-xs font-medium text-[var(--zinc-600)] transition-all active:scale-[0.97] enabled:hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-50 dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-300)]"
        aria-label="Выбрать сообщения для копирования"
        @click="emit('selectForCopy')"
      >
        <Copy class="size-3" aria-hidden="true" />
      </button>
      <button
        v-if="meta.status === 'open'"
        type="button"
        class="flex cursor-pointer items-center gap-1 rounded-[10px] border-[1.5px] border-[var(--color-gray-line)] bg-white px-3 py-1.5 text-xs font-medium text-[var(--zinc-600)] transition-all active:scale-[0.97] enabled:hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-50 dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-300)]"
        :disabled="syncLoading"
        aria-label="Синхронизировать историю с мессенджером"
        @click="onSyncHistory()"
      >
        <Loader2
          v-if="syncLoading"
          class="size-3 motion-safe:animate-spin text-[var(--color-brand)]"
          aria-hidden="true"
        />
        <RotateCw v-else class="size-3" aria-hidden="true" />
      </button>
      <button
        v-if="showAssignButton"
        type="button"
        class="flex cursor-pointer items-center gap-1 rounded-[10px] border-[1.5px] border-[var(--color-gray-line)] bg-white px-3 py-1.5 text-xs font-medium text-[var(--zinc-600)] transition-all active:scale-[0.97] enabled:hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-50 dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-300)]"
        :disabled="headerAction !== null"
        :title="
          meta.assignedToUserId != null && meta.assignedToUserId !== auth.user?.id
            ? 'Забрать чат себе'
            : 'Назначить чат на себя'
        "
        :aria-label="
          meta.assignedToUserId != null && meta.assignedToUserId !== auth.user?.id
            ? 'Забрать чат себе'
            : 'Назначить чат на себя'
        "
        @click="onAssignMe()"
      >
        <UserPlus
          class="size-3 motion-safe:transition-transform"
          :class="{ 'motion-safe:animate-pulse': headerAction === 'assign' }"
          aria-hidden="true"
        />
      </button>
      <button
        v-if="showCloseButton"
        type="button"
        class="flex cursor-pointer items-center gap-1 rounded-[10px] border-[1.5px] border-[#fecaca] bg-white px-3 py-1.5 text-xs font-medium text-[#ef4444] transition-all active:scale-[0.97] enabled:hover:opacity-95 disabled:cursor-not-allowed disabled:opacity-50 dark:border-[rgba(248,113,113,0.2)] dark:bg-[var(--zinc-800)] dark:text-[#f87171]"
        :disabled="headerAction !== null"
        aria-label="Закрыть чат"
        @click="onCloseChat()"
      >
        <X
          class="size-3 motion-safe:transition-transform"
          :class="{ 'motion-safe:animate-pulse': headerAction === 'close' }"
          aria-hidden="true"
        />
      </button>
      <button
        v-if="showReopenButton"
        type="button"
        class="flex cursor-pointer items-center gap-1 rounded-[10px] border-[1.5px] border-[var(--color-brand-200)] bg-[var(--color-brand-50)] px-3 py-1.5 text-xs font-medium text-[var(--color-brand)] transition-all active:scale-[0.97] dark:border-[var(--zinc-600)] dark:bg-[var(--zinc-800)] dark:text-[var(--color-brand-200)]"
        :disabled="headerAction !== null"
        aria-label="Вернуть в работу"
        @click="onReopenToWork()"
      >
        <UserPlus
          class="size-3"
          aria-hidden="true"
        />
        <span class="max-sm:hidden sm:inline">В работу</span>
      </button>
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
                <component :is="resolveDepartmentIcon(d.icon)" class="size-4 shrink-0 text-[var(--color-brand)]" />
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
