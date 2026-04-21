<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Loader2, Plus, Pencil, Trash2 } from 'lucide-vue-next'
import {
  fetchCannedResponses,
  createCannedResponse,
  updateCannedResponse,
  deleteCannedResponse,
} from '../../api/canned-responses'
import { fetchDepartments } from '../../api/departments'
import type { SourceListQueryParams } from '../../api/sourceListQuery'
import { useAuthStore } from '../../stores/authStore'
import type { ApiCannedResponse } from '../../types/dto/canned-response.types'
import ModeratorGuideCard from '../common/ModeratorGuideCard.vue'

const authStore = useAuthStore()
const items = ref<ApiCannedResponse[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const sourceFilter = ref<number | 'all'>('all')
const visibilityFilter = ref<'all' | 'mine' | 'shared'>('all')
const q = ref('')

const formOpen = ref(false)
const editing = ref<ApiCannedResponse | null>(null)
const form = ref({
  scope_kind: 'source' as 'global' | 'source' | 'department',
  scope_source_id: '' as string,
  /** Source used to load department list when scope_kind === department */
  scope_dept_parent_source_id: '' as string,
  scope_department_id: '' as string,
  is_shared: false,
  code: '',
  title: '',
  text: '',
  is_active: true,
})

const departmentOptions = ref<{ id: number; name: string }[]>([])

const sourceOptions = computed(() => authStore.user?.source_ids ?? [])
const sourceNamesById = computed(() => {
  const map = new Map<number, string>()
  for (const source of authStore.user?.sources ?? []) {
    map.set(source.id, source.name)
  }
  return map
})

const isAdmin = computed(() => authStore.user?.roles?.includes('admin'))

function sourceLabel(sourceId: number): string {
  return sourceNamesById.value.get(sourceId) ?? `Проект #${sourceId}`
}

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

function scopeLabel(row: ApiCannedResponse): string {
  if (row.scope_type == null && row.scope_id == null) {
    return isAdmin.value ? 'Глобально' : '—'
  }
  if (row.scope_type === 'source') {
    return row.scope_id != null ? sourceLabel(row.scope_id) : '—'
  }
  if (row.scope_type === 'department') {
    return `Отдел #${row.scope_id}`
  }
  return '—'
}

async function load() {
  loading.value = true
  error.value = null
  try {
    const params: SourceListQueryParams = {
      include_inactive: true,
      q: q.value.trim() || undefined,
      visibility: visibilityFilter.value === 'all' ? undefined : visibilityFilter.value,
    }
    if (sourceFilter.value !== 'all') {
      params.source_id = sourceFilter.value
    }
    items.value = await fetchCannedResponses(params)
  } catch (e) {
    error.value = 'Не удалось загрузить шаблоны'
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(load)

function openCreate() {
  editing.value = null
  const sid =
    sourceFilter.value === 'all'
      ? (sourceOptions.value[0] != null ? String(sourceOptions.value[0]) : '')
      : String(sourceFilter.value)
  form.value = {
    scope_kind: isAdmin.value ? 'global' : 'source',
    scope_source_id: sid,
    scope_dept_parent_source_id: sid,
    scope_department_id: '',
    is_shared: false,
    code: '',
    title: '',
    text: '',
    is_active: true,
  }
  departmentOptions.value = []
  if (!isAdmin.value && sid !== '') {
    void loadDepartmentOptions(Number(sid))
  }
  formOpen.value = true
}

function openEdit(row: ApiCannedResponse) {
  editing.value = row
  let kind: 'global' | 'source' | 'department' = 'global'
  if (row.scope_type === 'source') kind = 'source'
  if (row.scope_type === 'department') kind = 'department'

  const src =
    sourceOptions.value[0] != null ? String(sourceOptions.value[0]) : ''
  form.value = {
    scope_kind: kind,
    scope_source_id: row.scope_type === 'source' && row.scope_id != null ? String(row.scope_id) : src,
    scope_dept_parent_source_id: src,
    scope_department_id: row.scope_type === 'department' && row.scope_id != null ? String(row.scope_id) : '',
    is_shared: row.is_shared,
    code: row.code,
    title: row.title,
    text: row.text,
    is_active: row.is_active,
  }
  departmentOptions.value = []
  if (kind === 'department' && row.scope_id != null) {
    const parent = form.value.scope_dept_parent_source_id === '' ? src : form.value.scope_dept_parent_source_id
    if (parent !== '') {
      void loadDepartmentOptions(Number(parent))
    }
  }
  formOpen.value = true
}

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

async function saveForm() {
  loading.value = true
  error.value = null
  try {
    const scope = buildScopePayload()
    const body = {
      scope_type: scope.scope_type,
      scope_id: scope.scope_id,
      is_shared: form.value.is_shared,
      code: form.value.code,
      title: form.value.title,
      text: form.value.text,
      is_active: form.value.is_active,
    }
    if (editing.value) {
      await updateCannedResponse(editing.value.id, body)
    } else {
      await createCannedResponse(body)
    }
    formOpen.value = false
    await load()
  } catch (e) {
    error.value = 'Сохранение не удалось'
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function remove(row: ApiCannedResponse) {
  if (!confirm(`Удалить шаблон «${row.title}»?`)) return
  loading.value = true
  try {
    await deleteCannedResponse(row.id)
    await load()
  } catch (e) {
    error.value = 'Удаление не удалось'
    console.error(e)
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <section class="custom-scroll flex min-h-0 flex-1 flex-col overflow-y-auto p-10" style="background: var(--bg-thread)">
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
      <div>
        <h2 class="text-2xl font-bold" style="color: var(--text-primary)">
          Шаблоны ответов
        </h2>
        <p class="mt-1 text-sm" style="color: var(--text-secondary)">
          Тексты для вставки в чат (и в мобильном приложении)
        </p>
      </div>
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-xl px-5 py-3 text-sm font-bold shadow-lg ring-2 transition hover:opacity-95 active:scale-[0.99]"
        style="
          background: linear-gradient(135deg, var(--color-brand) 0%, #7c3a86 100%);
          color: white;
          ring-color: color-mix(in srgb, var(--color-brand) 35%, transparent);
        "
        @click="openCreate"
      >
        <Plus class="h-5 w-5" aria-hidden="true" />
        Новый шаблон
      </button>
    </div>

    <ModeratorGuideCard
      storage-key="cannedResponses"
      title="Инструкция: шаблоны ответов"
      purpose="Готовые тексты, которые модератор вставляет в чат одним кликом. Это экономит время, выдерживает единый тон общения и снижает ошибки в формулировках."
      :tips="[
        'По умолчанию шаблон личный — видите только вы. Включите «Общий», чтобы коллеги в том же источнике/отделе тоже видели шаблон.',
        'Область: источник (проект) или отдел. Глобальные шаблоны может создавать только администратор.',
        'Поле «Код» — короткий идентификатор (латиница/цифры), удобный для поиска в списке.',
        'Деактивируйте устаревшие шаблоны, чтобы они не попадали в выбор в чате.',
      ]"
      :examples="[
        'Приветствие: «Здравствуйте! Я на связи, помогу разобраться. Опишите, пожалуйста, что именно произошло.»',
        'Уточнение: «Уточните, пожалуйста, номер заказа или дату обращения — так я смогу проверить быстрее.»',
        'Завершение: «Спасибо за обращение. Если появятся вопросы — напишите сюда, мы поможем.»',
      ]"
    />

    <div class="mb-4 flex flex-wrap gap-3">
      <label class="flex flex-col gap-1 text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary)">
        Источник
        <select
          v-model="sourceFilter"
          class="h-10 min-w-[180px] rounded-[var(--radius-md)] border px-3 font-medium outline-none"
          style="border-color: var(--border-light); background: var(--bg-inbox); color: var(--text-primary)"
          @change="load"
        >
          <option value="all">
            Все доступные
          </option>
          <option v-for="sid in sourceOptions" :key="sid" :value="sid">
            {{ sourceLabel(sid) }}
          </option>
        </select>
      </label>
      <label class="flex flex-col gap-1 text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary)">
        Видимость
        <select
          v-model="visibilityFilter"
          class="h-10 min-w-[160px] rounded-[var(--radius-md)] border px-3 font-medium outline-none"
          style="border-color: var(--border-light); background: var(--bg-inbox); color: var(--text-primary)"
          @change="load"
        >
          <option value="all">
            Все
          </option>
          <option value="mine">
            Мои
          </option>
          <option value="shared">
            Общие
          </option>
        </select>
      </label>
      <label class="flex flex-col gap-1 text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary)">
        Поиск
        <input
          v-model="q"
          type="search"
          class="h-10 min-w-[200px] rounded-[var(--radius-md)] border px-3 font-medium outline-none"
          style="border-color: var(--border-light); background: var(--bg-inbox); color: var(--text-primary)"
          placeholder="Код, название, текст..."
          @keydown.enter="load"
        >
      </label>
      <button
        type="button"
        class="mt-auto h-10 rounded-[var(--radius-md)] border px-4 text-sm font-semibold transition hover:bg-white/5"
        style="border-color: var(--border-light); color: var(--text-primary)"
        @click="load"
      >
        Обновить
      </button>
    </div>

    <p v-if="error" class="mb-3 text-sm text-red-500" role="alert">
      {{ error }}
    </p>

    <div
      v-if="loading && items.length === 0"
      class="flex flex-1 items-center justify-center py-20"
    >
      <Loader2 class="h-8 w-8 animate-spin" style="color: var(--color-brand)" />
    </div>

    <div v-else class="overflow-x-auto rounded-[var(--radius-md)] border" style="border-color: var(--border-light)">
      <table class="w-full min-w-[860px] text-left text-sm">
        <thead style="background: var(--bg-inbox); color: var(--text-secondary)">
          <tr>
            <th class="px-4 py-3 font-semibold">
              Код
            </th>
            <th class="px-4 py-3 font-semibold">
              Название
            </th>
            <th class="px-4 py-3 font-semibold">
              Область
            </th>
            <th class="px-4 py-3 font-semibold">
              Общий
            </th>
            <th class="px-4 py-3 font-semibold">
              Активен
            </th>
            <th class="px-4 py-3 font-semibold w-28" />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="row in items"
            :key="row.id"
            class="border-t transition hover:bg-white/5"
            style="border-color: var(--border-light); color: var(--text-primary)"
          >
            <td class="px-4 py-2 font-mono text-xs">
              {{ row.code }}
            </td>
            <td class="px-4 py-2 max-w-[240px] truncate">
              {{ row.title }}
            </td>
            <td class="px-4 py-2">
              {{ scopeLabel(row) }}
            </td>
            <td class="px-4 py-2">
              {{ row.is_shared ? 'Да' : 'Нет' }}
            </td>
            <td class="px-4 py-2">
              {{ row.is_active ? 'Да' : 'Нет' }}
            </td>
            <td class="px-4 py-2">
              <div class="flex gap-2">
                <button type="button" class="rounded p-1.5 hover:bg-white/10" title="Изменить" @click="openEdit(row)">
                  <Pencil class="h-4 w-4" />
                </button>
                <button type="button" class="rounded p-1.5 text-red-400 hover:bg-red-500/10" title="Удалить" @click="remove(row)">
                  <Trash2 class="h-4 w-4" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div
      v-if="formOpen"
      class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
      role="dialog"
      aria-modal="true"
    >
      <div
        class="custom-scroll max-h-[90vh] w-full max-w-lg overflow-y-auto rounded-[var(--radius-md)] border p-6 shadow-xl"
        style="border-color: var(--border-light); background: var(--bg-inbox)"
      >
        <h3 class="mb-4 text-lg font-bold" style="color: var(--text-primary)">
          {{ editing ? 'Редактировать шаблон' : 'Новый шаблон' }}
        </h3>
        <div class="space-y-4">
          <label class="flex items-center gap-2 text-sm" style="color: var(--text-primary)">
            <input v-model="form.is_shared" type="checkbox" class="rounded border" style="border-color: var(--border-light)">
            Общий (виден коллегам в этой области)
          </label>
          <label class="block text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary)">
            Область
            <select
              v-model="form.scope_kind"
              class="mt-1 h-10 w-full rounded-[var(--radius-md)] border px-3"
              style="border-color: var(--border-light); background: var(--bg-thread); color: var(--text-primary)"
            >
              <option v-if="isAdmin" value="global">
                Глобально (все источники)
              </option>
              <option value="source">
                Источник (проект)
              </option>
              <option value="department">
                Отдел
              </option>
            </select>
          </label>
          <label
            v-if="form.scope_kind === 'source'"
            class="block text-xs font-bold uppercase tracking-wide"
            style="color: var(--text-secondary)"
          >
            Источник *
            <select
              v-model="form.scope_source_id"
              class="mt-1 h-10 w-full rounded-[var(--radius-md)] border px-3"
              style="border-color: var(--border-light); background: var(--bg-thread); color: var(--text-primary)"
            >
              <option v-for="sid in sourceOptions" :key="sid" :value="String(sid)">
                {{ sourceLabel(sid) }}
              </option>
            </select>
          </label>
          <template v-if="form.scope_kind === 'department'">
            <label class="block text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary)">
              Проект для списка отделов *
              <select
                v-model="form.scope_dept_parent_source_id"
                class="mt-1 h-10 w-full rounded-[var(--radius-md)] border px-3"
                style="border-color: var(--border-light); background: var(--bg-thread); color: var(--text-primary)"
              >
                <option v-for="sid in sourceOptions" :key="sid" :value="String(sid)">
                  {{ sourceLabel(sid) }}
                </option>
              </select>
            </label>
            <label class="block text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary)">
              Отдел *
              <select
                v-model="form.scope_department_id"
                class="mt-1 h-10 w-full rounded-[var(--radius-md)] border px-3"
                style="border-color: var(--border-light); background: var(--bg-thread); color: var(--text-primary)"
              >
                <option value="">
                  Выберите…
                </option>
                <option v-for="d in departmentOptions" :key="d.id" :value="String(d.id)">
                  {{ d.name }}
                </option>
              </select>
            </label>
          </template>
          <label class="block text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary)">
            Код *
            <input v-model="form.code" type="text" class="mt-1 h-10 w-full rounded-[var(--radius-md)] border px-3" style="border-color: var(--border-light); background: var(--bg-thread); color: var(--text-primary)">
          </label>
          <label class="block text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary)">
            Название *
            <input v-model="form.title" type="text" class="mt-1 h-10 w-full rounded-[var(--radius-md)] border px-3" style="border-color: var(--border-light); background: var(--bg-thread); color: var(--text-primary)">
          </label>
          <label class="block text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary)">
            Текст *
            <textarea v-model="form.text" rows="6" class="mt-1 w-full rounded-[var(--radius-md)] border px-3 py-2" style="border-color: var(--border-light); background: var(--bg-thread); color: var(--text-primary)" />
          </label>
          <label class="flex items-center gap-2 text-sm" style="color: var(--text-primary)">
            <input v-model="form.is_active" type="checkbox" class="rounded border" style="border-color: var(--border-light)">
            Активен
          </label>
        </div>
        <div class="mt-6 flex justify-end gap-3">
          <button
            type="button"
            class="rounded-[var(--radius-md)] border px-4 py-2 text-sm font-semibold"
            style="border-color: var(--border-light); color: var(--text-primary)"
            @click="formOpen = false"
          >
            Отмена
          </button>
          <button type="button" class="btn btn-primary px-4" :disabled="loading" @click="saveForm">
            Сохранить
          </button>
        </div>
      </div>
    </div>
  </section>
</template>
