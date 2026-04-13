<script setup lang="ts">
import { ChevronLeft, Loader2, Pencil, Plus, Trash2 } from 'lucide-vue-next'
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import * as cannedApi from '../api/cannedResponseRepository'
import BottomNav from '../components/layout/BottomNav.vue'
import { useAuthStore } from '../stores/authStore'
import { useInboxStore } from '../stores/inboxStore'
import type { ApiCannedResponse } from '../api/types'

const router = useRouter()
const auth = useAuthStore()
const inbox = useInboxStore()

const items = ref<ApiCannedResponse[]>([])
const loading = ref(false)
const err = ref<string | null>(null)
const modal = ref(false)
const editing = ref<ApiCannedResponse | null>(null)
const form = ref({
  source_id: '' as string,
  code: '',
  title: '',
  text: '',
  is_active: true,
})

const sourceIds = () => auth.user?.source_ids ?? []

async function load() {
  loading.value = true
  err.value = null
  try {
    items.value = await cannedApi.fetchCannedResponses({ include_inactive: true })
  } catch {
    err.value = 'Не удалось загрузить'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  inbox.setBottomNav('settings')
  void load()
})

function openCreate() {
  editing.value = null
  const first = sourceIds()[0]
  form.value = {
    source_id: first != null ? String(first) : '',
    code: '',
    title: '',
    text: '',
    is_active: true,
  }
  modal.value = true
}

function openEdit(row: ApiCannedResponse) {
  editing.value = row
  form.value = {
    source_id: row.source_id == null ? '' : String(row.source_id),
    code: row.code ?? '',
    title: row.title,
    text: row.text,
    is_active: row.is_active ?? true,
  }
  modal.value = true
}

function parseSid(): number | null {
  return form.value.source_id === '' ? null : Number(form.value.source_id)
}

async function save() {
  loading.value = true
  try {
    if (editing.value) {
      await cannedApi.updateCannedResponse(editing.value.id, {
        source_id: parseSid(),
        code: form.value.code,
        title: form.value.title,
        text: form.value.text,
        is_active: form.value.is_active,
      })
    } else {
      await cannedApi.createCannedResponse({
        source_id: parseSid(),
        code: form.value.code,
        title: form.value.title,
        text: form.value.text,
        is_active: form.value.is_active,
      })
    }
    modal.value = false
    await load()
  } catch {
    err.value = 'Ошибка сохранения'
  } finally {
    loading.value = false
  }
}

async function remove(row: ApiCannedResponse) {
  if (!confirm(`Удалить «${row.title}»?`)) return
  loading.value = true
  try {
    await cannedApi.deleteCannedResponse(row.id)
    await load()
  } catch {
    err.value = 'Ошибка удаления'
  } finally {
    loading.value = false
  }
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
        Шаблоны ответов
      </div>
      <button
        type="button"
        class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[var(--color-brand)] text-white"
        aria-label="Добавить"
        @click="openCreate"
      >
        <Plus class="size-5" />
      </button>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3">
      <p v-if="err" class="mb-2 text-sm text-red-500">
        {{ err }}
      </p>
      <div v-if="loading && items.length === 0" class="flex justify-center py-16">
        <Loader2 class="size-8 animate-spin text-[var(--color-brand)]" />
      </div>
      <div v-else class="space-y-2">
        <div
          v-for="row in items"
          :key="row.id"
          class="flex items-start gap-2 rounded-2xl border border-[var(--color-gray-line)] bg-white p-3 dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)]"
        >
          <div class="min-w-0 flex-1">
            <div class="text-sm font-semibold text-[var(--color-dark)] dark:text-[var(--zinc-100)]">
              {{ row.title }}
            </div>
            <div class="mt-0.5 text-xs text-[var(--zinc-500)]">
              {{ row.code }} · {{ row.is_active ? 'активен' : 'выкл' }}
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
          {{ editing ? 'Редактировать' : 'Новый шаблон' }}
        </div>
        <label class="mb-2 block text-xs text-[var(--zinc-500)]">Источник</label>
        <select
          v-model="form.source_id"
          class="mb-3 w-full rounded-xl border border-[var(--color-gray-line)] bg-white px-3 py-2 text-sm dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-100)]"
        >
          <option value="">
            Глобально
          </option>
          <option v-for="sid in sourceIds()" :key="sid" :value="String(sid)">
            #{{ sid }}
          </option>
        </select>
        <label class="mb-2 block text-xs text-[var(--zinc-500)]">Код</label>
        <input v-model="form.code" class="mb-3 w-full rounded-xl border px-3 py-2 text-sm dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-100)]">
        <label class="mb-2 block text-xs text-[var(--zinc-500)]">Название</label>
        <input v-model="form.title" class="mb-3 w-full rounded-xl border px-3 py-2 text-sm dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-100)]">
        <label class="mb-2 block text-xs text-[var(--zinc-500)]">Текст</label>
        <textarea v-model="form.text" rows="4" class="mb-3 w-full rounded-xl border px-3 py-2 text-sm dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-100)]" />
        <label class="mb-4 flex items-center gap-2 text-sm dark:text-[var(--zinc-200)]">
          <input v-model="form.is_active" type="checkbox">
          Активен
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
