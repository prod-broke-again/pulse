<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Loader2, Plus, Pencil, Trash2, ChevronUp, ChevronDown, X, ArrowUpRight, Globe, Folder, Users } from 'lucide-vue-next'
import {
  fetchQuickLinks,
  createQuickLink,
  updateQuickLink,
  deleteQuickLink,
  reorderQuickLinks,
} from '../../api/quick-links'
import { fetchDepartments } from '../../api/departments'
import type { SourceListQueryParams } from '../../api/sourceListQuery'
import { useAuthStore } from '../../stores/authStore'
import type { ApiQuickLink } from '../../types/dto/quick-link.types'
import ModeratorGuideCard from '../common/ModeratorGuideCard.vue'
import CustomSelect from '../common/CustomSelect.vue'

const authStore = useAuthStore()
const items = ref<ApiQuickLink[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const sourceFilter = ref<number | 'all'>('all')
const visibilityFilter = ref<'all' | 'mine' | 'shared'>('all')
const q = ref('')

const formOpen = ref(false)
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

const sourceOptions = computed(() => authStore.user?.source_ids ?? [])
const sourceNamesById = computed(() => {
  const map = new Map<number, string>()
  for (const source of authStore.user?.sources ?? []) {
    map.set(source.id, source.name)
  }
  return map
})
const isAdmin = computed(() => authStore.user?.roles?.includes('admin'))

const filterSourceOptions = computed(() => [
  { label: 'Все доступные', value: 'all' as const },
  ...sourceOptions.value.map((sid) => ({ label: sourceLabel(sid), value: sid })),
])

const visibilityOptions = [
  { label: 'Все', value: 'all' as const },
  { label: 'Мои', value: 'mine' as const },
  { label: 'Общие', value: 'shared' as const },
]

const scopeKindOptions = computed(() => {
  const rows: Array<{ label: string; value: 'global' | 'source' | 'department' }> = []
  if (isAdmin.value) {
    rows.push({ label: 'Глобально (все источники)', value: 'global' })
  }
  rows.push({ label: 'Источник (проект)', value: 'source' })
  rows.push({ label: 'Отдел', value: 'department' })
  return rows
})

const formSourceOptions = computed(() => sourceOptions.value.map((sid) => ({
  label: sourceLabel(sid),
  value: String(sid),
})))

const formDepartmentOptions = computed(() => [
  { label: 'Выберите…', value: '' },
  ...departmentOptions.value.map((d) => ({ label: d.name, value: String(d.id) })),
])

const previewTitle = computed(() => form.value.title.trim() || 'Подпись кнопки')

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

function scopeLabel(row: ApiQuickLink): string {
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
    items.value = await fetchQuickLinks(params)
  } catch (e) {
    error.value = 'Не удалось загрузить ссылки'
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(load)

function parsedSourceId(): number | null {
  return form.value.scope_source_id === '' ? null : Number(form.value.scope_source_id)
}

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
    title: '',
    url: 'https://',
    is_active: true,
    sort_order: items.value.length * 10,
  }
  departmentOptions.value = []
  if (!isAdmin.value && sid !== '') {
    void loadDepartmentOptions(Number(sid))
  }
  formOpen.value = true
}

function openEdit(row: ApiQuickLink) {
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
    title: row.title,
    url: row.url,
    is_active: row.is_active,
    sort_order: row.sort_order,
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
      title: form.value.title,
      url: form.value.url,
      is_active: form.value.is_active,
      sort_order: form.value.sort_order,
    }
    if (editing.value) {
      await updateQuickLink(editing.value.id, body)
    } else {
      await createQuickLink(body)
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

async function remove(row: ApiQuickLink) {
  if (!confirm(`Удалить «${row.title}»?`)) return
  loading.value = true
  try {
    await deleteQuickLink(row.id)
    await load()
  } catch (e) {
    error.value = 'Удаление не удалось'
    console.error(e)
  } finally {
    loading.value = false
  }
}

async function move(index: number, dir: -1 | 1) {
  const j = index + dir
  if (j < 0 || j >= items.value.length) return
  const list = [...items.value]
  const tmp = list[j]!
  list[j] = list[index]!
  list[index] = tmp
  const orders = list.map((row, i) => ({ id: row.id, sort_order: i * 10 }))
  loading.value = true
  try {
    await reorderQuickLinks(orders)
    await load()
  } catch (e) {
    error.value = 'Не удалось изменить порядок'
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
          Быстрые ссылки
        </h2>
        <p class="mt-1 text-sm" style="color: var(--text-secondary)">
          Кнопки с URL для отправки пользователю в чате (inline-кнопки)
        </p>
      </div>
      <button
        type="button"
        class="inline-flex items-center gap-2 rounded-xl px-5 py-3 text-sm font-bold shadow-lg ring-2 transition hover:opacity-95 active:scale-[0.99]"
        style="
          background: linear-gradient(135deg, var(--color-brand) 0%, #7c3a86 100%);
          color: white;
          box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-brand) 35%, transparent);
        "
        @click="openCreate"
      >
        <Plus class="h-5 w-5" aria-hidden="true" />
        Новая ссылка
      </button>
    </div>

    <ModeratorGuideCard
      storage-key="quickLinks"
      title="Инструкция: быстрые ссылки"
      purpose="Кнопки с переходом по URL, которые отправляются пользователю в чате (inline-кнопки). Удобно, когда нужно дать ссылку на оплату, инструкцию или раздел сайта без длинного текста."
      :tips="[
        'По умолчанию ссылка личная. Включите «Общий», чтобы коллеги в той же области тоже видели кнопку.',
        '«Подпись» — текст на кнопке; делайте её короткой и понятной (что откроется по нажатию).',
        'URL должен быть полным (https://…) и вести на нужную страницу; проверяйте ссылку перед сохранением.',
        'Порядок в таблице задаёт порядок кнопок в чате — важные ссылки держите выше.',
      ]"
      :examples="[
        'Оплата: подпись «Оплатить заказ», URL страницы оплаты или личного кабинета.',
        'FAQ: подпись «Частые вопросы», URL раздела помощи.',
        'ЛК: подпись «Мой кабинет», URL входа в личный кабинет.',
      ]"
    />

    <div class="ql-filters">
      <label class="ql-filter-group">
        Источник
        <CustomSelect
          v-model="sourceFilter"
          :options="filterSourceOptions"
          @change="load"
        />
      </label>
      <label class="ql-filter-group">
        Видимость
        <CustomSelect
          v-model="visibilityFilter"
          :options="visibilityOptions"
          @change="load"
        />
      </label>
      <label class="ql-filter-group">
        Поиск
        <div class="ql-search-wrap">
          <input
            v-model="q"
            type="search"
            class="ql-input ql-input-search"
            placeholder="Текст или URL"
            @keydown.enter="load"
          >
        </div>
      </label>
      <button
        type="button"
        class="ql-btn ql-btn-ghost"
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

    <div v-else class="ql-table-wrap">
      <div class="overflow-x-auto">
      <table class="w-full min-w-[900px] text-left text-sm">
        <thead class="ql-thead">
          <tr>
            <th class="w-24">
              Порядок
            </th>
            <th>
              Подпись
            </th>
            <th>
              URL
            </th>
            <th>
              Область
            </th>
            <th>
              Статус
            </th>
            <th class="w-36 text-right" />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, index) in items"
            :key="row.id"
            class="ql-tr"
          >
            <td class="px-2 py-2">
              <div class="ql-order-btns">
                <button type="button" class="ql-icon-btn" title="Выше" @click="move(index, -1)">
                  <ChevronUp class="h-4 w-4" />
                </button>
                <button type="button" class="ql-icon-btn" title="Ниже" @click="move(index, 1)">
                  <ChevronDown class="h-4 w-4" />
                </button>
              </div>
            </td>
            <td class="px-4 py-2 max-w-[220px] truncate font-semibold">
              {{ row.title }}
            </td>
            <td class="px-4 py-2 max-w-[320px] truncate font-mono text-xs" style="color: var(--text-secondary)">
              {{ row.url }}
            </td>
            <td class="px-4 py-2">
              <span class="ql-badge ql-badge-scope">
                <Globe v-if="row.scope_type == null" class="h-3 w-3" />
                <Folder v-else-if="row.scope_type === 'source'" class="h-3 w-3" />
                <Users v-else class="h-3 w-3" />
                {{ scopeLabel(row) }}
              </span>
            </td>
            <td class="px-4 py-2">
              <div class="flex flex-wrap gap-2">
                <span class="ql-badge" :class="row.is_active ? 'ql-badge-active' : 'ql-badge-inactive'">
                  {{ row.is_active ? 'Активна' : 'Выкл.' }}
                </span>
                <span v-if="row.is_shared" class="ql-badge ql-badge-shared">
                  Общая
                </span>
              </div>
            </td>
            <td class="px-4 py-2 text-right">
              <div class="ql-row-actions">
                <button type="button" class="ql-icon-btn ql-row-action-btn" title="Изменить" @click="openEdit(row)">
                  <Pencil class="h-4 w-4" />
                </button>
                <button type="button" class="ql-icon-btn ql-row-action-btn ql-icon-btn-danger" title="Удалить" @click="remove(row)">
                  <Trash2 class="h-4 w-4" />
                </button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
      </div>
    </div>

    <div
      v-if="formOpen"
      class="ql-modal-overlay"
      role="dialog"
      aria-modal="true"
      @click.self="formOpen = false"
    >
      <div class="ql-modal custom-scroll">
        <div class="ql-modal-header">
          <div>
            <h2 class="ql-modal-title">
              {{ editing ? 'Редактировать ссылку' : 'Новая ссылка' }}
            </h2>
            <p class="ql-modal-sub">
              Настройте область показа и параметры inline-кнопки.
            </p>
          </div>
          <button type="button" class="ql-modal-close" aria-label="Закрыть" @click="formOpen = false">
            <X class="h-4 w-4" />
          </button>
        </div>

        <div class="ql-modal-body">
          <div class="ql-form-section">
            <div class="ql-section-title">
              Видимость
            </div>
            <div class="ql-switch-row">
              <div class="ql-switch-text">
                <span class="ql-switch-title">Общая ссылка</span>
                <span class="ql-switch-desc">Видна коллегам в этой же области</span>
              </div>
              <label class="ql-switch">
                <input v-model="form.is_shared" type="checkbox">
                <span class="ql-switch-slider" />
              </label>
            </div>
          </div>

          <div class="ql-form-section">
            <div class="ql-section-title">
              Область действия
            </div>
            <div class="ql-form-grid">
              <label class="ql-field ql-field-full">
                <span class="ql-field-label">Тип области</span>
                <CustomSelect v-model="form.scope_kind" :options="scopeKindOptions" />
              </label>
              <label v-if="form.scope_kind === 'source'" class="ql-field ql-field-full">
                <span class="ql-field-label">Источник<span class="ql-req">*</span></span>
                <CustomSelect v-model="form.scope_source_id" :options="formSourceOptions" />
              </label>
              <template v-if="form.scope_kind === 'department'">
                <label class="ql-field">
                  <span class="ql-field-label">Проект<span class="ql-req">*</span></span>
                  <CustomSelect v-model="form.scope_dept_parent_source_id" :options="formSourceOptions" />
                </label>
                <label class="ql-field">
                  <span class="ql-field-label">Отдел<span class="ql-req">*</span></span>
                  <CustomSelect v-model="form.scope_department_id" :options="formDepartmentOptions" />
                </label>
              </template>
            </div>
          </div>

          <div class="ql-form-section">
            <div class="ql-section-title">
              Параметры кнопки
            </div>
            <div class="ql-form-grid">
              <label class="ql-field ql-field-full">
                <span class="ql-field-label">Подпись кнопки<span class="ql-req">*</span></span>
                <input
                  v-model="form.title"
                  type="text"
                  class="ql-input"
                  placeholder="Например, «Оплатить заказ»"
                >
              </label>
              <label class="ql-field ql-field-full">
                <span class="ql-field-label">URL<span class="ql-req">*</span></span>
                <input
                  v-model="form.url"
                  type="url"
                  class="ql-input"
                  placeholder="https://example.com/pay"
                >
              </label>
              <label class="ql-field">
                <span class="ql-field-label">Порядок</span>
                <input
                  v-model.number="form.sort_order"
                  type="number"
                  min="0"
                  class="ql-input"
                >
              </label>
              <div class="ql-field">
                <span class="ql-field-label">Статус</span>
                <div class="ql-switch-row ql-switch-row-compact">
                  <span class="ql-switch-title">Активна</span>
                  <label class="ql-switch">
                    <input v-model="form.is_active" type="checkbox">
                    <span class="ql-switch-slider" />
                  </label>
                </div>
              </div>
              <div class="ql-field ql-field-full">
                <span class="ql-field-label">Предпросмотр</span>
                <div class="ql-url-preview">
                  <div class="ql-url-preview-label">
                    Так кнопка будет выглядеть в чате:
                  </div>
                  <span class="ql-url-preview-btn">
                    <ArrowUpRight class="h-3.5 w-3.5" />
                    {{ previewTitle }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="ql-modal-footer">
          <button type="button" class="ql-btn ql-btn-ghost" @click="formOpen = false">
            Отмена
          </button>
          <button
            type="button"
            class="ql-btn ql-btn-primary"
            :disabled="loading"
            @click="saveForm"
          >
            Сохранить
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.ql-filters {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  align-items: flex-end;
  margin-bottom: 20px;
}

.ql-filter-group {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 180px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.08em;
  color: var(--text-secondary);
}

.ql-search-wrap {
  width: 100%;
}

.ql-input {
  width: 100%;
  height: 44px;
  border: 1px solid var(--border-light);
  border-radius: var(--radius-md);
  background: var(--bg-inbox);
  color: var(--text-primary);
  font-size: 14px;
  padding: 0 12px;
  outline: none;
}

.ql-input-search {
  min-width: 240px;
  padding-left: 34px;
  background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%239a9a9a' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3ccircle cx='11' cy='11' r='8'%3e%3c/circle%3e%3cline x1='21' y1='21' x2='16.65' y2='16.65'%3e%3c/line%3e%3c/svg%3e");
  background-repeat: no-repeat;
  background-position: 10px center;
  background-size: 14px;
}

.ql-btn {
  height: 44px;
  border-radius: var(--radius-md);
  border: 1px solid transparent;
  padding: 0 16px;
  font-size: 14px;
  font-weight: 600;
  transition: all 0.15s ease;
}

.ql-btn-ghost {
  background: var(--bg-inbox);
  color: var(--text-primary);
  border-color: var(--border-light);
}

.ql-btn-ghost:hover {
  border-color: var(--border-medium);
}

.ql-btn-primary {
  background: var(--text-primary);
  color: #fff;
  border-color: var(--text-primary);
}

.ql-btn-primary:hover:not(:disabled) {
  opacity: 0.95;
}

.ql-btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.ql-table-wrap {
  border: 1px solid var(--border-light);
  border-radius: var(--radius-lg);
  background: var(--bg-inbox);
  overflow: hidden;
}

.ql-thead th {
  padding: 14px 18px;
  background: var(--bg-thread);
  font-weight: 600;
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text-secondary);
  border-bottom: 1px solid var(--border-light);
  white-space: nowrap;
}

.ql-tr td {
  padding-top: 14px;
  padding-bottom: 14px;
  border-bottom: 1px solid var(--border-light);
}

.ql-tr:last-child td {
  border-bottom: none;
}

.ql-tr:hover {
  background: color-mix(in srgb, var(--bg-thread) 85%, transparent);
}

.ql-order-btns {
  display: inline-flex;
  flex-direction: column;
  gap: 2px;
}

.ql-icon-btn {
  width: 28px;
  height: 22px;
  border-radius: 6px;
  border: 1px solid transparent;
  color: var(--text-secondary);
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: all 0.15s ease;
}

.ql-icon-btn:hover {
  border-color: var(--border-light);
  background: color-mix(in srgb, var(--bg-thread) 90%, transparent);
  color: var(--text-primary);
}

.ql-icon-btn-danger:hover {
  border-color: #b84646;
  color: #b84646;
  background: rgba(184, 70, 70, 0.1);
}

.ql-row-actions {
  display: inline-flex;
  gap: 4px;
}

.ql-row-action-btn {
  width: 32px;
  height: 32px;
}

.ql-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 12px;
  font-weight: 500;
  border: 1px solid var(--border-light);
  background: var(--bg-thread);
  color: var(--text-secondary);
}

.ql-badge-scope {
  color: var(--text-primary);
  background: var(--bg-inbox);
}

.ql-badge-active {
  color: #2d5f3f;
  background: color-mix(in srgb, #2d5f3f 16%, transparent);
  border-color: transparent;
}

.ql-badge-inactive {
  color: var(--text-secondary);
}

.ql-badge-shared {
  color: #8a5a00;
  background: rgba(253, 244, 227, 0.8);
  border-color: transparent;
}

.ql-section-title {
  margin: 0 0 10px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.09em;
  color: var(--text-secondary);
}

.ql-switch-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 16px;
  border: 1px solid var(--border-light);
  border-radius: var(--radius-md);
  background: var(--bg-thread);
}

.ql-switch-row-compact {
  padding: 10px 12px;
}

.ql-switch-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.ql-switch-title {
  font-size: 14px;
  font-weight: 500;
  color: var(--text-primary);
}

.ql-switch-desc {
  font-size: 12.5px;
  color: var(--text-secondary);
}

.ql-switch {
  position: relative;
  width: 40px;
  height: 22px;
  flex-shrink: 0;
}

.ql-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.ql-switch-slider {
  position: absolute;
  inset: 0;
  background: var(--border-medium);
  border-radius: 22px;
  transition: 0.2s;
}

.ql-switch-slider::before {
  content: "";
  position: absolute;
  left: 3px;
  top: 3px;
  width: 16px;
  height: 16px;
  border-radius: 50%;
  background: #fff;
  transition: 0.2s;
}

.ql-switch input:checked + .ql-switch-slider {
  background: var(--color-brand);
}

.ql-switch input:checked + .ql-switch-slider::before {
  transform: translateX(18px);
}

.ql-modal-overlay {
  position: fixed;
  inset: 0;
  z-index: 50;
  background: rgba(20, 20, 20, 0.4);
  backdrop-filter: blur(4px);
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.ql-modal {
  width: 100%;
  max-width: 620px;
  max-height: 92vh;
  overflow-y: auto;
  border-radius: 16px;
  background: var(--bg-inbox);
  box-shadow: var(--shadow-lg);
}

.ql-modal-header {
  padding: 24px 28px 18px;
  border-bottom: 1px solid var(--border-light);
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.ql-modal-title {
  margin: 0 0 4px;
  font-size: 20px;
  font-weight: 700;
  letter-spacing: -0.01em;
  color: var(--text-primary);
}

.ql-modal-sub {
  margin: 0;
  font-size: 13.5px;
  color: var(--text-secondary);
}

.ql-modal-close {
  width: 32px;
  height: 32px;
  border: 0;
  border-radius: 8px;
  background: transparent;
  color: var(--text-secondary);
  display: inline-flex;
  align-items: center;
  justify-content: center;
}

.ql-modal-close:hover {
  background: color-mix(in srgb, var(--bg-thread) 80%, transparent);
  color: var(--text-primary);
}

.ql-modal-body {
  padding: 24px 28px;
}

.ql-modal-footer {
  padding: 16px 28px 22px;
  border-top: 1px solid var(--border-light);
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  background: var(--bg-thread);
}

.ql-form-section {
  margin-bottom: 24px;
}

.ql-form-section:last-child {
  margin-bottom: 0;
}

.ql-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.ql-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.ql-field-full {
  grid-column: 1 / -1;
}

.ql-field-label {
  font-size: 13px;
  font-weight: 500;
  color: var(--text-primary);
}

.ql-req {
  color: #b84646;
  margin-left: 2px;
}

.ql-url-preview {
  margin-top: 4px;
  padding: 12px 14px;
  border: 1px dashed var(--border-medium);
  border-radius: var(--radius-md);
  background: var(--bg-thread);
}

.ql-url-preview-label {
  margin-bottom: 6px;
  font-size: 12px;
  color: var(--text-secondary);
}

.ql-url-preview-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  border-radius: 20px;
  background: var(--color-brand);
  color: #fff;
  font-size: 13px;
  font-weight: 500;
}

@media (max-width: 640px) {
  .ql-form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
