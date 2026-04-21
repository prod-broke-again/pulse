<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Loader2, Plus, Pencil, Trash2, X } from 'lucide-vue-next'
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
import CustomSelect from '../common/CustomSelect.vue'

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
          box-shadow: 0 0 0 2px color-mix(in srgb, var(--color-brand) 35%, transparent);
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
        <CustomSelect
          v-model="sourceFilter"
          :options="filterSourceOptions"
          @change="load"
        />
      </label>
      <label class="flex flex-col gap-1 text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary)">
        Видимость
        <CustomSelect
          v-model="visibilityFilter"
          :options="visibilityOptions"
          @change="load"
        />
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
      class="tm-modal-overlay"
      role="dialog"
      aria-modal="true"
      @click.self="formOpen = false"
    >
      <div class="tm-modal custom-scroll">
        <div class="tm-modal-header">
          <div>
            <h2 class="tm-modal-title">
              {{ editing ? 'Редактировать шаблон' : 'Новый шаблон' }}
            </h2>
            <p class="tm-modal-sub">
              Настройте область видимости и содержание шаблона.
            </p>
          </div>
          <button type="button" class="tm-modal-close" aria-label="Закрыть" @click="formOpen = false">
            <X class="h-4 w-4" />
          </button>
        </div>

        <div class="tm-modal-body">
          <div class="tm-form-section">
            <div class="tm-section-title">
              Видимость
            </div>
            <div class="tm-switch-row">
              <div class="tm-switch-text">
                <span class="tm-switch-title">Общий шаблон</span>
                <span class="tm-switch-desc">Виден коллегам в этой же области</span>
              </div>
              <label class="tm-switch">
                <input v-model="form.is_shared" type="checkbox">
                <span class="tm-switch-slider" />
              </label>
            </div>
          </div>

          <div class="tm-form-section">
            <div class="tm-section-title">
              Область действия
            </div>
            <div class="tm-form-grid">
              <label class="tm-field tm-field-full">
                <span class="tm-field-label">Тип области</span>
                <CustomSelect v-model="form.scope_kind" :options="scopeKindOptions" />
              </label>
              <label v-if="form.scope_kind === 'source'" class="tm-field tm-field-full">
                <span class="tm-field-label">Источник<span class="tm-req">*</span></span>
                <CustomSelect v-model="form.scope_source_id" :options="formSourceOptions" />
              </label>
              <template v-if="form.scope_kind === 'department'">
                <label class="tm-field">
                  <span class="tm-field-label">Проект<span class="tm-req">*</span></span>
                  <CustomSelect v-model="form.scope_dept_parent_source_id" :options="formSourceOptions" />
                </label>
                <label class="tm-field">
                  <span class="tm-field-label">Отдел<span class="tm-req">*</span></span>
                  <CustomSelect v-model="form.scope_department_id" :options="formDepartmentOptions" />
                </label>
              </template>
            </div>
          </div>

          <div class="tm-form-section">
            <div class="tm-section-title">
              Параметры шаблона
            </div>
            <div class="tm-form-grid">
              <label class="tm-field tm-field-full">
                <span class="tm-field-label">Код<span class="tm-req">*</span></span>
                <input v-model="form.code" type="text" class="tm-input">
              </label>
              <label class="tm-field tm-field-full">
                <span class="tm-field-label">Название<span class="tm-req">*</span></span>
                <input v-model="form.title" type="text" class="tm-input">
              </label>
              <label class="tm-field tm-field-full">
                <span class="tm-field-label">Текст<span class="tm-req">*</span></span>
                <textarea v-model="form.text" rows="7" class="tm-input tm-textarea" />
              </label>
              <div class="tm-field">
                <span class="tm-field-label">Статус</span>
                <div class="tm-switch-row tm-switch-row-compact">
                  <span class="tm-switch-title">Активен</span>
                  <label class="tm-switch">
                    <input v-model="form.is_active" type="checkbox">
                    <span class="tm-switch-slider" />
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="tm-modal-footer">
          <button type="button" class="tm-btn tm-btn-ghost" @click="formOpen = false">
            Отмена
          </button>
          <button type="button" class="tm-btn tm-btn-primary" :disabled="loading" @click="saveForm">
            Сохранить
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<style scoped>
.tm-section-title {
  margin: 0 0 10px;
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.09em;
  color: var(--text-secondary);
}

.tm-switch-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
  padding: 14px 16px;
  border: 1px solid var(--border-light);
  border-radius: var(--radius-md);
  background: var(--bg-thread);
}

.tm-switch-row-compact {
  padding: 10px 12px;
}

.tm-switch-text {
  display: flex;
  flex-direction: column;
  gap: 2px;
}

.tm-switch-title {
  font-size: 14px;
  font-weight: 500;
  color: var(--text-primary);
}

.tm-switch-desc {
  font-size: 12.5px;
  color: var(--text-secondary);
}

.tm-switch {
  position: relative;
  width: 40px;
  height: 22px;
  flex-shrink: 0;
}

.tm-switch input {
  opacity: 0;
  width: 0;
  height: 0;
}

.tm-switch-slider {
  position: absolute;
  inset: 0;
  background: var(--border-medium);
  border-radius: 22px;
  transition: 0.2s;
}

.tm-switch-slider::before {
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

.tm-switch input:checked + .tm-switch-slider {
  background: var(--color-brand);
}

.tm-switch input:checked + .tm-switch-slider::before {
  transform: translateX(18px);
}

.tm-modal-overlay {
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

.tm-modal {
  width: 100%;
  max-width: 620px;
  max-height: 92vh;
  overflow-y: auto;
  border-radius: 16px;
  background: var(--bg-inbox);
  box-shadow: var(--shadow-lg);
}

.tm-modal-header {
  padding: 24px 28px 18px;
  border-bottom: 1px solid var(--border-light);
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  gap: 16px;
}

.tm-modal-title {
  margin: 0 0 4px;
  font-size: 20px;
  font-weight: 700;
  letter-spacing: -0.01em;
  color: var(--text-primary);
}

.tm-modal-sub {
  margin: 0;
  font-size: 13.5px;
  color: var(--text-secondary);
}

.tm-modal-close {
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

.tm-modal-close:hover {
  background: color-mix(in srgb, var(--bg-thread) 80%, transparent);
  color: var(--text-primary);
}

.tm-modal-body {
  padding: 24px 28px;
}

.tm-modal-footer {
  padding: 16px 28px 22px;
  border-top: 1px solid var(--border-light);
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  background: var(--bg-thread);
}

.tm-form-section {
  margin-bottom: 24px;
}

.tm-form-section:last-child {
  margin-bottom: 0;
}

.tm-form-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
}

.tm-field {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.tm-field-full {
  grid-column: 1 / -1;
}

.tm-field-label {
  font-size: 13px;
  font-weight: 500;
  color: var(--text-primary);
}

.tm-req {
  color: #b84646;
  margin-left: 2px;
}

.tm-input {
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

.tm-textarea {
  min-height: 132px;
  height: auto;
  padding: 10px 12px;
}

.tm-btn {
  height: 42px;
  border-radius: var(--radius-md);
  border: 1px solid transparent;
  padding: 0 16px;
  font-size: 14px;
  font-weight: 600;
}

.tm-btn-ghost {
  background: var(--bg-inbox);
  color: var(--text-primary);
  border-color: var(--border-light);
}

.tm-btn-primary {
  background: var(--text-primary);
  color: #fff;
  border-color: var(--text-primary);
}

.tm-btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 640px) {
  .tm-form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
