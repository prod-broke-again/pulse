<script setup lang="ts">
import { Loader2, X } from 'lucide-vue-next'
import { computed, onMounted, ref, watch } from 'vue'
import { fetchDepartments } from '../../api/chatRepository'
import * as inboxPrefsApi from '../../api/inboxFilterPreferences'
import { useAuthStore } from '../../stores/authStore'
import { useInboxStore } from '../../stores/inboxStore'
import { useUiStore } from '../../stores/uiStore'
import type { InboxFilterPrefs } from '../../types/inbox'

const props = defineProps<{
  open: boolean
}>()

const emit = defineEmits<{
  close: []
  applied: []
}>()

const auth = useAuthStore()
const inbox = useInboxStore()
const ui = useUiStore()

const localSourceIds = ref<number[]>([])
const localDeptIds = ref<number[]>([])
const allDepartments = ref<Array<{ id: number; name: string; source_id?: number }>>([])
const loadingDepts = ref(false)
const savingDefault = ref(false)

const sources = computed(() => {
  const u = auth.user
  if (u?.sources && u.sources.length > 0) {
    return u.sources.map((s) => ({ id: s.id, name: s.name || `Источник ${s.id}` }))
  }
  return (u?.source_ids ?? []).map((id) => ({ id, name: `Источник #${id}` }))
})

const showDeptSection = computed(() => sources.value.length > 0)

function syncFromStore(): void {
  localSourceIds.value = [...(inbox.filterSourceIds ?? [])]
  localDeptIds.value = [...(inbox.filterDepartmentIds ?? [])]
}

watch(
  () => props.open,
  (o) => {
    if (o) {
      syncFromStore()
      void loadAllDepartments()
    }
  },
)

onMounted(() => {
  if (props.open) {
    syncFromStore()
    void loadAllDepartments()
  }
})

async function loadAllDepartments(): Promise<void> {
  const ids = auth.user?.source_ids ?? []
  if (ids.length === 0) {
    allDepartments.value = []
    return
  }
  loadingDepts.value = true
  try {
    const lists = await Promise.all(ids.map((sid) => fetchDepartments(sid).catch(() => [])))
    const merged = new Map<number, { id: number; name: string; source_id?: number }>()
    ids.forEach((sid, i) => {
      for (const d of lists[i] ?? []) {
        merged.set(d.id, { id: d.id, name: d.name, source_id: sid })
      }
    })
    allDepartments.value = Array.from(merged.values()).sort((a, b) => a.name.localeCompare(b.name, 'ru'))
  } finally {
    loadingDepts.value = false
  }
}

function toggleSource(id: number): void {
  const s = new Set(localSourceIds.value)
  if (s.has(id)) s.delete(id)
  else s.add(id)
  localSourceIds.value = Array.from(s)
}

function toggleDepartment(id: number): void {
  const s = new Set(localDeptIds.value)
  if (s.has(id)) s.delete(id)
  else s.add(id)
  localDeptIds.value = Array.from(s)
}

function clearSources(): void {
  localSourceIds.value = []
}

function clearDepartments(): void {
  localDeptIds.value = []
}

function apply(): void {
  inbox.setFilterSourceIds(localSourceIds.value.length > 0 ? [...localSourceIds.value] : undefined)
  inbox.setFilterDepartmentIds(localDeptIds.value.length > 0 ? [...localDeptIds.value] : undefined)
  emit('applied')
  emit('close')
}

async function saveAsDefault(): Promise<void> {
  const ch = (['tg', 'vk', 'web', 'max'] as const).filter((c) => inbox.activeFilters.has(c))
  const patch: Partial<InboxFilterPrefs> = {
    enabled_source_ids: localSourceIds.value.length > 0 ? [...localSourceIds.value] : null,
    enabled_department_ids: localDeptIds.value.length > 0 ? [...localDeptIds.value] : null,
    enabled_channel_types: ch.length > 0 ? [...ch] : null,
  }
  savingDefault.value = true
  try {
    const data = await inboxPrefsApi.patchInboxFilterPreferences(patch)
    auth.$patch((s) => {
      if (s.user) {
        s.user = { ...s.user, ...data.user, inbox_filter_prefs: data.inbox_filter_prefs }
      }
    })
    apply()
    ui.pushToast('Сохранено как инбокс по умолчанию', 'success')
  } catch {
    ui.pushToast('Не удалось сохранить', 'error')
  } finally {
    savingDefault.value = false
  }
}
</script>

<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[400] flex flex-col justify-end bg-black/50"
      role="dialog"
      aria-modal="true"
      aria-label="Фильтры инбокса"
      @click.self="emit('close')"
    >
      <div
        class="max-h-[85vh] overflow-y-auto rounded-t-2xl bg-white px-4 pb-[calc(16px+var(--safe-bottom))] pt-3 dark:bg-[var(--zinc-900)]"
        @click.stop
      >
        <div class="mb-3 flex items-center justify-between">
          <div class="text-base font-semibold text-[var(--color-dark)] dark:text-[var(--zinc-100)]">
            Фильтры
          </div>
          <button
            type="button"
            class="flex size-9 items-center justify-center rounded-xl text-[var(--zinc-500)]"
            aria-label="Закрыть"
            @click="emit('close')"
          >
            <X class="size-5" />
          </button>
        </div>
        <p class="mb-2 text-xs text-[var(--zinc-500)]">
          Ограничьте список по источникам и отделам (как на десктопе). Пустой выбор = все доступные.
        </p>

        <div class="mb-3 text-xs font-medium uppercase text-[var(--zinc-400)]">
          Источники
        </div>
        <div v-if="sources.length === 0" class="mb-3 text-sm text-[var(--zinc-500)]">
          Нет привязанных источников
        </div>
        <div v-else class="mb-3 flex flex-wrap gap-2">
          <button
            v-for="s in sources"
            :key="s.id"
            type="button"
            class="rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
            :class="
              localSourceIds.includes(s.id)
                ? 'border-[var(--color-brand)] bg-[var(--color-brand)] text-white'
                : 'border-[var(--color-gray-line)] bg-white text-[var(--zinc-600)] dark:border-[var(--zinc-600)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-200)]'
            "
            @click="toggleSource(s.id)"
          >
            {{ s.name }}
          </button>
        </div>
        <button
          v-if="localSourceIds.length > 0"
          type="button"
          class="mb-4 text-xs text-[var(--color-brand)]"
          @click="clearSources"
        >
          Сбросить источники
        </button>

        <div v-if="showDeptSection" class="mb-2 text-xs font-medium uppercase text-[var(--zinc-400)]">
          Отделы
        </div>
        <div v-if="loadingDepts" class="mb-4 flex justify-center py-4">
          <Loader2 class="size-6 animate-spin text-[var(--color-brand)]" />
        </div>
        <div v-else-if="allDepartments.length > 0" class="mb-3 flex max-h-40 flex-wrap gap-2 overflow-y-auto">
          <button
            v-for="d in allDepartments"
            :key="d.id"
            type="button"
            class="rounded-full border px-3 py-1.5 text-xs font-medium transition-colors"
            :class="
              localDeptIds.includes(d.id)
                ? 'border-[var(--color-brand)] bg-[var(--color-brand)] text-white'
                : 'border-[var(--color-gray-line)] bg-white text-[var(--zinc-600)] dark:border-[var(--zinc-600)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-200)]'
            "
            @click="toggleDepartment(d.id)"
          >
            {{ d.name }}
          </button>
        </div>
        <button
          v-if="localDeptIds.length > 0"
          type="button"
          class="mb-4 text-xs text-[var(--color-brand)]"
          @click="clearDepartments"
        >
          Сбросить отделы
        </button>

        <div class="flex flex-col gap-2">
          <button
            type="button"
            class="w-full rounded-xl bg-[var(--color-brand)] py-3 text-sm font-semibold text-white"
            @click="apply"
          >
            Применить
          </button>
          <button
            type="button"
            class="w-full rounded-xl border border-[var(--color-gray-line)] py-3 text-sm font-medium text-[var(--zinc-600)] dark:border-[var(--zinc-600)]"
            :disabled="savingDefault"
            @click="saveAsDefault"
          >
            <Loader2
              v-if="savingDefault"
              class="inline size-4 animate-spin align-middle"
            />
            <span v-else>Сохранить как по умолчанию</span>
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>
