<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Loader2, Plus, Pencil, Trash2, ChevronUp, ChevronDown } from 'lucide-vue-next'
import {
  fetchQuickLinks,
  createQuickLink,
  updateQuickLink,
  deleteQuickLink,
  reorderQuickLinks,
} from '../../api/quick-links'
import { useAuthStore } from '../../stores/authStore'
import type { ApiQuickLink } from '../../types/dto/quick-link.types'
import ModeratorGuideCard from '../common/ModeratorGuideCard.vue'

const authStore = useAuthStore()
const items = ref<ApiQuickLink[]>([])
const loading = ref(false)
const error = ref<string | null>(null)
const sourceFilter = ref<number | 'all'>('all')
const q = ref('')

const formOpen = ref(false)
const editing = ref<ApiQuickLink | null>(null)
const form = ref({
  source_id: '' as string,
  title: '',
  url: '',
  is_active: true,
  sort_order: 0,
})

const sourceOptions = computed(() => authStore.user?.source_ids ?? [])
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
  return form.value.source_id === '' ? null : Number(form.value.source_id)
}

function openCreate() {
  editing.value = null
  const sid =
    sourceFilter.value === 'all'
      ? (sourceOptions.value[0] != null ? String(sourceOptions.value[0]) : '')
      : String(sourceFilter.value)
  form.value = {
    source_id: sid,
    title: '',
    url: 'https://',
    is_active: true,
    sort_order: items.value.length * 10,
  }
  formOpen.value = true
}

function openEdit(row: ApiQuickLink) {
  editing.value = row
  form.value = {
    source_id: row.source_id == null ? '' : String(row.source_id),
    title: row.title,
    url: row.url,
    is_active: row.is_active,
    sort_order: row.sort_order,
  }
  formOpen.value = true
}

async function saveForm() {
  loading.value = true
  error.value = null
  try {
    if (editing.value) {
      await updateQuickLink(editing.value.id, {
        source_id: parsedSourceId(),
        title: form.value.title,
        url: form.value.url,
        is_active: form.value.is_active,
        sort_order: form.value.sort_order,
      })
    } else {
      await createQuickLink({
        source_id: parsedSourceId(),
        title: form.value.title,
        url: form.value.url,
        is_active: form.value.is_active,
        sort_order: form.value.sort_order,
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
        class="btn btn-primary inline-flex items-center gap-2"
        @click="openCreate"
      >
        <Plus class="h-4 w-4" />
        Новая ссылка
      </button>
    </div>

    <ModeratorGuideCard
      storage-key="quickLinks"
      title="Инструкция: быстрые ссылки"
      purpose="Кнопки с переходом по URL, которые отправляются пользователю в чате (inline-кнопки). Удобно, когда нужно дать ссылку на оплату, инструкцию или раздел сайта без длинного текста."
      :tips="[
        '«Подпись» — текст на кнопке; делайте её короткой и понятной (что откроется по нажатию).',
        'URL должен быть полным (https://…) и вести на нужную страницу; проверяйте ссылку перед сохранением.',
        'Порядок в таблице задаёт порядок кнопок в чате — важные ссылки держите выше.',
        '«Источник» ограничивает кнопку проектом; глобальные ссылки доступны администраторам.',
        'Отключайте неактуальные ссылки (не активна), чтобы не путать пользователя.',
      ]"
      :examples="[
        'Оплата: подпись «Оплатить заказ», URL страницы оплаты или личного кабинета.',
        'FAQ: подпись «Частые вопросы», URL раздела помощи.',
        'ЛК: подпись «Мой кабинет», URL входа в личный кабинет.',
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
          placeholder="Текст или URL"
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
      <table class="w-full min-w-[800px] text-left text-sm">
        <thead style="background: var(--bg-inbox); color: var(--text-secondary)">
          <tr>
            <th class="px-2 py-3 w-24 font-semibold">
              Порядок
            </th>
            <th class="px-4 py-3 font-semibold">
              Подпись
            </th>
            <th class="px-4 py-3 font-semibold">
              URL
            </th>
            <th class="px-4 py-3 font-semibold">
              Источник
            </th>
            <th class="px-4 py-3 font-semibold">
              Активна
            </th>
            <th class="px-4 py-3 font-semibold w-36" />
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, index) in items"
            :key="row.id"
            class="border-t transition hover:bg-white/5"
            style="border-color: var(--border-light); color: var(--text-primary)"
          >
            <td class="px-2 py-2">
              <div class="flex flex-col gap-0.5">
                <button type="button" class="rounded p-1 hover:bg-white/10" title="Выше" @click="move(index, -1)">
                  <ChevronUp class="h-4 w-4" />
                </button>
                <button type="button" class="rounded p-1 hover:bg-white/10" title="Ниже" @click="move(index, 1)">
                  <ChevronDown class="h-4 w-4" />
                </button>
              </div>
            </td>
            <td class="px-4 py-2 max-w-[200px] truncate">
              {{ row.title }}
            </td>
            <td class="px-4 py-2 max-w-[280px] truncate font-mono text-xs" style="color: var(--text-secondary)">
              {{ row.url }}
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
          {{ editing ? 'Редактировать ссылку' : 'Новая ссылка' }}
        </h3>
        <div class="space-y-4">
          <label class="block text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary)">
            Источник
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
            Подпись кнопки *
            <input v-model="form.title" type="text" class="mt-1 h-10 w-full rounded-[var(--radius-md)] border px-3" style="border-color: var(--border-light); background: var(--bg-thread); color: var(--text-primary)">
          </label>
          <label class="block text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary)">
            URL *
            <input v-model="form.url" type="url" class="mt-1 h-10 w-full rounded-[var(--radius-md)] border px-3" style="border-color: var(--border-light); background: var(--bg-thread); color: var(--text-primary)">
          </label>
          <label class="block text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary)">
            Порядок
            <input v-model.number="form.sort_order" type="number" min="0" class="mt-1 h-10 w-full rounded-[var(--radius-md)] border px-3" style="border-color: var(--border-light); background: var(--bg-thread); color: var(--text-primary)">
          </label>
          <label class="flex items-center gap-2 text-sm" style="color: var(--text-primary)">
            <input v-model="form.is_active" type="checkbox" class="rounded border" style="border-color: var(--border-light)">
            Активна
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
