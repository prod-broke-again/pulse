<script setup lang="ts">
import { ChevronLeft, ChevronDown, ChevronUp, Loader2, Pencil, Plus, Trash2 } from 'lucide-vue-next'
import { computed, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import * as qlApi from '../api/quickLinkRepository'
import { fetchDepartments } from '../api/chatRepository'
import BottomNav from '../components/layout/BottomNav.vue'
import { useAuthStore } from '../stores/authStore'
import { useInboxStore } from '../stores/inboxStore'
import type { ApiQuickLink } from '../api/types'

const router = useRouter()
const auth = useAuthStore()
const inbox = useInboxStore()

const items = ref<ApiQuickLink[]>([])
const loading = ref(false)
const err = ref<string | null>(null)
const visibility = ref<'all' | 'mine' | 'shared'>('all')
const listSourceId = ref<string>('')
const listQ = ref('')
const modal = ref(false)
const editing = ref<ApiQuickLink | null>(null)
const form = ref({
  scope_kind: 'source' as 'global' | 'source' | 'department',
  scope_source_id: '' as string,
  scope_dept_parent_source_id: '' as string,
  scope_department_id: '' as string,
  is_shared: false,
  title: '',
  url: '',
  is_active: true,
  sort_order: 0,
})

const departmentOptions = ref<{ id: number; name: string }[]>([])

const sourceIds = () => auth.user?.source_ids ?? []
const isAdmin = computed(() => auth.user?.roles?.includes('admin'))

const sourceOptions = computed(() => {
  const u = auth.user
  if (u?.sources && u.sources.length > 0) {
    return u.sources.map((s) => ({ id: s.id, label: s.name || `Источник #${s.id}` }))
  }
  return (u?.source_ids ?? []).map((id) => ({ id, label: `Источник #${id}` }))
})

async function loadDepartmentOptions(sourceId: number) {
  try {
    const rows = await fetchDepartments(sourceId)
    departmentOptions.value = rows.map((d) => ({ id: d.id, name: d.name }))
  } catch {
    departmentOptions.value = []
  }
}

watch(
  () => form.value.scope_dept_parent_source_id,
  (sid) => {
    if (form.value.scope_kind !== 'department') return
    const n = sid === '' ? NaN : Number(sid)
    if (!Number.isFinite(n)) {
      departmentOptions.value = []
      return
    }
    void loadDepartmentOptions(n)
  },
)

watch(
  () => form.value.scope_kind,
  (k) => {
    if (k === 'department' && form.value.scope_dept_parent_source_id !== '') {
      const n = Number(form.value.scope_dept_parent_source_id)
      if (Number.isFinite(n)) void loadDepartmentOptions(n)
    }
  },
)

function buildScopePayload(): { scope_type: 'source' | 'department' | null; scope_id: number | null } {
  if (form.value.scope_kind === 'global') {
    return { scope_type: null, scope_id: null }
  }
  if (form.value.scope_kind === 'source') {
    const id = form.value.scope_source_id === '' ? NaN : Number(form.value.scope_source_id)
    return { scope_type: 'source', scope_id: Number.isFinite(id) ? id : null }
  }
  const id = form.value.scope_department_id === '' ? NaN : Number(form.value.scope_department_id)
  return { scope_type: 'department', scope_id: Number.isFinite(id) ? id : null }
}

async function load() {
  loading.value = true
  err.value = null
  try {
    items.value = await qlApi.fetchQuickLinks({
      include_inactive: true,
      visibility: visibility.value,
      source_id: listSourceId.value === '' ? undefined : Number(listSourceId.value) || undefined,
      q: listQ.value.trim() || undefined,
    })
  } catch {
    err.value = 'Не удалось загрузить'
  } finally {
    loading.value = false
  }
}

watch([visibility, listSourceId], () => {
  void load()
})

let qTimer: ReturnType<typeof setTimeout> | null = null
watch(listQ, () => {
  if (qTimer) clearTimeout(qTimer)
  qTimer = setTimeout(() => {
    void load()
  }, 400)
})

onMounted(() => {
  inbox.setBottomNav('settings')
  void load()
})

function openCreate() {
  editing.value = null
  const first = sourceIds()[0]
  form.value = {
    scope_kind: isAdmin.value ? 'global' : 'source',
    scope_source_id: first != null ? String(first) : '',
    scope_dept_parent_source_id: first != null ? String(first) : '',
    scope_department_id: '',
    is_shared: false,
    title: '',
    url: 'https://',
    is_active: true,
    sort_order: items.value.length * 10,
  }
  departmentOptions.value = []
  if (!isAdmin.value && first != null) {
    void loadDepartmentOptions(first)
  }
  modal.value = true
}

function openEdit(row: ApiQuickLink) {
  editing.value = row
  let kind: 'global' | 'source' | 'department' = 'global'
  if (row.scope_type === 'source') kind = 'source'
  if (row.scope_type === 'department') kind = 'department'

  const src = sourceIds()[0] != null ? String(sourceIds()[0]) : ''
  form.value = {
    scope_kind: kind,
    scope_source_id: row.scope_type === 'source' && row.scope_id != null ? String(row.scope_id) : src,
    scope_dept_parent_source_id: src,
    scope_department_id: row.scope_type === 'department' && row.scope_id != null ? String(row.scope_id) : '',
    is_shared: row.is_shared ?? false,
    title: row.title,
    url: row.url,
    is_active: row.is_active ?? true,
    sort_order: row.sort_order ?? 0,
  }
  departmentOptions.value = []
  if (kind === 'department' && row.scope_id != null && src !== '') {
    void loadDepartmentOptions(Number(src))
  }
  modal.value = true
}

async function save() {
  loading.value = true
  try {
    const scope = buildScopePayload()
    const body = {
      scope_type: scope.scope_type,
      scope_id: scope.scope_id,
      is_shared: form.value.is_shared,
      title: form.value.title,
      url: form.value.url,
      is_active: form.value.is_active,
      sort_order: form.value.sort_order,
    }
    if (editing.value) {
      await qlApi.updateQuickLink(editing.value.id, body)
    } else {
      await qlApi.createQuickLink(body)
    }
    modal.value = false
    await load()
  } catch {
    err.value = 'Ошибка сохранения'
  } finally {
    loading.value = false
  }
}

async function remove(row: ApiQuickLink) {
  if (!confirm(`Удалить «${row.title}»?`)) return
  loading.value = true
  try {
    await qlApi.deleteQuickLink(row.id)
    await load()
  } catch {
    err.value = 'Ошибка удаления'
  } finally {
    loading.value = false
  }
}

async function move(index: number, dir: -1 | 1) {
  const j = index + dir
  if (j < 0 || j >= items.value.length) return
  const list = [...items.value]
  const t = list[j]!
  list[j] = list[index]!
  list[index] = t
  const orders = list.map((row, i) => ({ id: row.id, sort_order: i * 10 }))
  loading.value = true
  try {
    await qlApi.reorderQuickLinks(orders)
    await load()
  } catch {
    err.value = 'Порядок не сохранён'
  } finally {
    loading.value = false
  }
}

function scopeLabel(row: ApiQuickLink): string {
  if (row.scope_type == null && row.scope_id == null) {
    return isAdmin.value ? 'глобально' : '—'
  }
  if (row.scope_type === 'source') return `источник #${row.scope_id}`
  if (row.scope_type === 'department') return `отдел #${row.scope_id}`
  return '—'
}
</script>

<template>
  <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
    <div
      class="flex min-h-14 shrink-0 items-center gap-2 border-b border-[var(--color-gray-line)] bg-white px-2 pb-3 pt-[calc(12px+var(--safe-top))] dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-850)]"
    >
      <button
        type="button"
        class="flex size-10 shrink-0 items-center justify-center rounded-xl text-[var(--zinc-500)]"
        aria-label="Назад"
        @click="router.push({ name: 'settings' })"
      >
        <ChevronLeft class="size-6" />
      </button>
      <div class="min-w-0 flex-1 text-lg font-bold text-[var(--color-dark)] dark:text-[var(--zinc-50)]">
        Быстрые ссылки
      </div>
      <button
        type="button"
        class="flex size-11 shrink-0 items-center justify-center rounded-2xl bg-[var(--color-brand)] text-white shadow-lg ring-2 ring-[var(--color-brand)]/40"
        aria-label="Добавить ссылку"
        @click="openCreate"
      >
        <Plus class="size-6" />
      </button>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3">
      <div class="mb-3 flex flex-col gap-2 sm:flex-row sm:items-end sm:gap-3">
        <div class="flex flex-wrap gap-1.5">
          <button
            v-for="v in (['all', 'mine', 'shared'] as const)"
            :key="v"
            type="button"
            class="rounded-full border px-3 py-1.5 text-xs font-medium"
            :class="
              visibility === v
                ? 'border-[var(--color-brand)] bg-[var(--color-brand)] text-white'
                : 'border-[var(--color-gray-line)] bg-white text-[var(--zinc-600)] dark:border-[var(--zinc-600)] dark:bg-[var(--zinc-800)]'
            "
            @click="visibility = v"
          >
            {{ v === 'all' ? 'Все' : v === 'mine' ? 'Мои' : 'Общие' }}
          </button>
        </div>
        <select
          v-model="listSourceId"
          class="w-full min-w-0 rounded-xl border border-[var(--color-gray-line)] bg-white px-3 py-2 text-sm dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)] sm:max-w-[200px]"
        >
          <option value="">
            Все источники
          </option>
          <option v-for="o in sourceOptions" :key="o.id" :value="String(o.id)">
            {{ o.label }}
          </option>
        </select>
        <input
          v-model="listQ"
          type="search"
          placeholder="Поиск…"
          class="w-full min-w-0 rounded-xl border border-[var(--color-gray-line)] bg-white px-3 py-2 text-sm dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)]"
        >
      </div>
      <p v-if="err" class="mb-2 text-sm text-red-500">
        {{ err }}
      </p>
      <div v-if="loading && items.length === 0" class="flex justify-center py-16">
        <Loader2 class="size-8 animate-spin text-[var(--color-brand)]" />
      </div>
      <div v-else class="space-y-2">
        <div
          v-for="(row, index) in items"
          :key="row.id"
          class="flex items-start gap-1 rounded-2xl border border-[var(--color-gray-line)] bg-white p-2 dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)]"
        >
          <div class="flex flex-col py-1">
            <button type="button" class="p-0.5 text-[var(--zinc-400)]" @click="move(index, -1)">
              <ChevronUp class="size-4" />
            </button>
            <button type="button" class="p-0.5 text-[var(--zinc-400)]" @click="move(index, 1)">
              <ChevronDown class="size-4" />
            </button>
          </div>
          <div class="min-w-0 flex-1">
            <div class="text-sm font-semibold text-[var(--color-dark)] dark:text-[var(--zinc-100)]">
              {{ row.title }}
            </div>
            <div class="mt-0.5 truncate text-xs text-[var(--zinc-500)]">
              {{ row.url }}
            </div>
            <div class="mt-0.5 text-[10px] text-[var(--zinc-500)]">
              {{ scopeLabel(row) }} · {{ row.is_shared ? 'общая' : 'личная' }}
            </div>
          </div>
          <button type="button" class="p-2 text-[var(--color-brand)]" @click="openEdit(row)">
            <Pencil class="size-4" />
          </button>
          <button type="button" class="p-2 text-red-500" @click="remove(row)">
            <Trash2 class="size-4" />
          </button>
        </div>
      </div>
    </div>

    <BottomNav :inbox-badge="inbox.inboxBadge" />

    <div
      v-if="modal"
      class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 sm:items-center"
      @click.self="modal = false"
    >
      <div
        class="max-h-[90vh] w-full overflow-y-auto rounded-t-2xl bg-white p-4 dark:bg-[var(--zinc-850)] sm:max-w-md sm:rounded-2xl"
      >
        <div class="mb-3 text-lg font-bold dark:text-[var(--zinc-50)]">
          {{ editing ? 'Редактировать' : 'Новая ссылка' }}
        </div>
        <label class="mb-2 flex items-center gap-2 text-sm dark:text-[var(--zinc-200)]">
          <input v-model="form.is_shared" type="checkbox">
          Общая (коллеги в области)
        </label>
        <label class="mb-2 block text-xs text-[var(--zinc-500)]">Область</label>
        <select
          v-model="form.scope_kind"
          class="mb-3 w-full rounded-xl border border-[var(--color-gray-line)] bg-white px-3 py-2 text-sm dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-100)]"
        >
          <option v-if="isAdmin" value="global">
            Глобально
          </option>
          <option value="source">
            Источник
          </option>
          <option value="department">
            Отдел
          </option>
        </select>
        <template v-if="form.scope_kind === 'source'">
          <label class="mb-2 block text-xs text-[var(--zinc-500)]">Источник</label>
          <select
            v-model="form.scope_source_id"
            class="mb-3 w-full rounded-xl border border-[var(--color-gray-line)] bg-white px-3 py-2 text-sm dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-100)]"
          >
            <option v-for="sid in sourceIds()" :key="sid" :value="String(sid)">
              #{{ sid }}
            </option>
          </select>
        </template>
        <template v-if="form.scope_kind === 'department'">
          <label class="mb-2 block text-xs text-[var(--zinc-500)]">Проект для отделов</label>
          <select
            v-model="form.scope_dept_parent_source_id"
            class="mb-3 w-full rounded-xl border border-[var(--color-gray-line)] bg-white px-3 py-2 text-sm dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-100)]"
          >
            <option v-for="sid in sourceIds()" :key="sid" :value="String(sid)">
              #{{ sid }}
            </option>
          </select>
          <label class="mb-2 block text-xs text-[var(--zinc-500)]">Отдел</label>
          <select
            v-model="form.scope_department_id"
            class="mb-3 w-full rounded-xl border border-[var(--color-gray-line)] bg-white px-3 py-2 text-sm dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-100)]"
          >
            <option value="">
              …
            </option>
            <option v-for="d in departmentOptions" :key="d.id" :value="String(d.id)">
              {{ d.name }}
            </option>
          </select>
        </template>
        <label class="mb-2 block text-xs text-[var(--zinc-500)]">Подпись кнопки</label>
        <input v-model="form.title" class="mb-3 w-full rounded-xl border px-3 py-2 text-sm dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-100)]">
        <label class="mb-2 block text-xs text-[var(--zinc-500)]">URL</label>
        <input v-model="form.url" type="url" class="mb-3 w-full rounded-xl border px-3 py-2 text-sm dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-100)]">
        <label class="mb-2 block text-xs text-[var(--zinc-500)]">Порядок</label>
        <input v-model.number="form.sort_order" type="number" class="mb-3 w-full rounded-xl border px-3 py-2 text-sm dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-100)]">
        <label class="mb-4 flex items-center gap-2 text-sm dark:text-[var(--zinc-200)]">
          <input v-model="form.is_active" type="checkbox">
          Активна
        </label>
        <div class="flex gap-2">
          <button
            type="button"
            class="flex-1 rounded-xl border py-3 text-sm font-semibold dark:border-[var(--zinc-600)] dark:text-[var(--zinc-200)]"
            @click="modal = false"
          >
            Отмена
          </button>
          <button
            type="button"
            class="flex-1 rounded-xl bg-[var(--color-brand)] py-3 text-sm font-semibold text-white"
            :disabled="loading"
            @click="save"
          >
            Сохранить
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
