<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Loader2, Plus, Pencil, Trash2 } from 'lucide-vue-next'
import {
  fetchCannedResponses,
  createCannedResponse,
  updateCannedResponse,
  deleteCannedResponse,
} from '../../api/canned-responses'
import { useAuthStore } from '../../stores/authStore'
import type { ApiCannedResponse } from '../../types/dto/canned-response.types'
import ModeratorGuideCard from '../common/ModeratorGuideCard.vue'

const authStore = useAuthStore()
const items = ref<ApiCannedResponse[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const sourceFilter = ref<number | 'all'>('all')
const q = ref('')

const formOpen = ref(false)
const editing = ref<ApiCannedResponse | null>(null)
const form = ref({
  source_id: '' as string,
  code: '',
  title: '',
  text: '',
  is_active: true,
})

const sourceOptions = computed(() => {
  const ids = authStore.user?.source_ids ?? []
  return ids
})

const isAdmin = computed(() => authStore.user?.roles?.includes('admin'))

async function load() {
  loading.value = true
  error.value = null
  try {
    const params: { source_id?: number; q?: string; include_inactive: boolean } = {
      include_inactive: true,
      q: q.value.trim() || undefined,
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
    source_id: sid,
    code: '',
    title: '',
    text: '',
    is_active: true,
  }
  formOpen.value = true
}

function openEdit(row: ApiCannedResponse) {
  editing.value = row
  form.value = {
    source_id: row.source_id == null ? '' : String(row.source_id),
    code: row.code,
    title: row.title,
    text: row.text,
    is_active: row.is_active,
  }
  formOpen.value = true
}

function parsedSourceId(): number | null {
  const s = form.value.source_id
  return s === '' ? null : Number(s)
}

async function saveForm() {
  loading.value = true
  error.value = null
  try {
    if (editing.value) {
      await updateCannedResponse(editing.value.id, {
        source_id: parsedSourceId(),
        code: form.value.code,
        title: form.value.title,
        text: form.value.text,
        is_active: form.value.is_active,
      })
    } else {
      await createCannedResponse({
        source_id: parsedSourceId(),
        code: form.value.code,
        title: form.value.title,
        text: form.value.text,
        is_active: form.value.is_active,
      })
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
        class="btn btn-primary inline-flex items-center gap-2"
        @click="openCreate"
      >
        <Plus class="h-4 w-4" />
        Новый шаблон
      </button>
    </div>

    <ModeratorGuideCard
      storage-key="cannedResponses"
      title="Инструкция: шаблоны ответов"
      purpose="Готовые тексты, которые модератор вставляет в чат одним кликом. Это экономит время, выдерживает единый тон общения и снижает ошибки в формулировках."
      :tips="[
        'Создавайте шаблоны под частые ситуации: приветствие, уточнение данных, инструкция, завершение диалога.',
        'Поле «Код» — короткий идентификатор (латиница/цифры), удобный для поиска в списке.',
        '«Источник»: привязка к проекту; пусто — глобально (доступно только администраторам).',
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
            Проект #{{ sid }}
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
      <table class="w-full min-w-[720px] text-left text-sm">
        <thead style="background: var(--bg-inbox); color: var(--text-secondary)">
          <tr>
            <th class="px-4 py-3 font-semibold">
              Код
            </th>
            <th class="px-4 py-3 font-semibold">
              Название
            </th>
            <th class="px-4 py-3 font-semibold">
              Источник
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
              {{ row.source_id == null ? (isAdmin ? 'Глобально' : '—') : `#${row.source_id}` }}
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
          <label class="block text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary)">
            Источник (пусто = глобально, только админ)
            <select
              v-model="form.source_id"
              class="mt-1 h-10 w-full rounded-[var(--radius-md)] border px-3"
              style="border-color: var(--border-light); background: var(--bg-thread); color: var(--text-primary)"
            >
              <option value="">
                Глобально
              </option>
              <option v-for="sid in sourceOptions" :key="sid" :value="String(sid)">
                Проект #{{ sid }}
              </option>
            </select>
          </label>
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
