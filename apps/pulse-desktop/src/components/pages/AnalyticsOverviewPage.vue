<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { Loader2, BarChart3 } from 'lucide-vue-next'
import { fetchAnalyticsOverview } from '../../api/analytics'
import { useAuthStore } from '../../stores/authStore'
import type { AnalyticsOverviewData } from '../../types/dto/analytics.types'

const authStore = useAuthStore()

function defaultTo(): string {
  return new Date().toISOString().slice(0, 10)
}

function defaultFrom(): string {
  return new Date(Date.now() - 7 * 86400000).toISOString().slice(0, 10)
}

const from = ref(defaultFrom())
const to = ref(defaultTo())
const sourceId = ref<string>('all')
const loading = ref(false)
const error = ref<string | null>(null)
const data = ref<AnalyticsOverviewData | null>(null)

const sourceOptions = computed(() => authStore.user?.source_ids ?? [])
const sourceNamesById = computed(() => {
  const map = new Map<number, string>()
  for (const source of authStore.user?.sources ?? []) {
    map.set(source.id, source.name)
  }
  return map
})

function sourceLabel(sourceId: number): string {
  return sourceNamesById.value.get(sourceId) ?? `Проект #${sourceId}`
}

async function load() {
  loading.value = true
  error.value = null
  try {
    data.value = await fetchAnalyticsOverview({
      from: from.value,
      to: to.value,
      source_id: sourceId.value === 'all' ? undefined : Number(sourceId.value),
    })
  } catch (e) {
    error.value = 'Не удалось загрузить аналитику'
    console.error(e)
  } finally {
    loading.value = false
  }
}

onMounted(load)

const cards = [
  { key: 'chats_created' as const, label: 'Новых чатов', hint: 'Создано в периоде' },
  { key: 'chats_closed' as const, label: 'Закрыто', hint: 'Переведено в closed в периоде' },
  { key: 'messages_total' as const, label: 'Сообщений всего', hint: '' },
  { key: 'messages_from_clients' as const, label: 'От клиентов', hint: '' },
  { key: 'messages_from_moderators' as const, label: 'От модераторов', hint: '' },
  { key: 'messages_from_system' as const, label: 'Системные', hint: '' },
]
</script>

<template>
  <section class="custom-scroll flex min-h-0 flex-1 flex-col overflow-y-auto p-10" style="background: var(--bg-thread)">
    <div class="mb-6">
      <h2 class="flex items-center gap-2 text-2xl font-bold" style="color: var(--text-primary)">
        <BarChart3 class="h-7 w-7" style="color: var(--color-brand)" />
        Аналитика
      </h2>
      <p class="mt-1 text-sm" style="color: var(--text-secondary)">
        Показатели по вашим источникам и отделам за выбранный период
      </p>
    </div>

    <div class="mb-6 flex flex-wrap items-end gap-4">
      <label class="flex flex-col gap-1 text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary)">
        С даты
        <input
          v-model="from"
          type="date"
          class="h-10 rounded-[var(--radius-md)] border px-3 font-medium outline-none"
          style="border-color: var(--border-light); background: var(--bg-inbox); color: var(--text-primary)"
        >
      </label>
      <label class="flex flex-col gap-1 text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary)">
        По дату
        <input
          v-model="to"
          type="date"
          class="h-10 rounded-[var(--radius-md)] border px-3 font-medium outline-none"
          style="border-color: var(--border-light); background: var(--bg-inbox); color: var(--text-primary)"
        >
      </label>
      <label class="flex flex-col gap-1 text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary)">
        Источник
        <select
          v-model="sourceId"
          class="h-10 min-w-[180px] rounded-[var(--radius-md)] border px-3 font-medium outline-none"
          style="border-color: var(--border-light); background: var(--bg-inbox); color: var(--text-primary)"
        >
          <option value="all">
            Все
          </option>
          <option v-for="sid in sourceOptions" :key="sid" :value="String(sid)">
            {{ sourceLabel(sid) }}
          </option>
        </select>
      </label>
      <button type="button" class="btn btn-primary mt-auto h-10 px-6" :disabled="loading" @click="load">
        Показать
      </button>
    </div>

    <p v-if="error" class="mb-4 text-sm text-red-500">
      {{ error }}
    </p>

    <div v-if="loading && !data" class="flex flex-1 items-center justify-center py-24">
      <Loader2 class="h-10 w-10 animate-spin" style="color: var(--color-brand)" />
    </div>

    <div v-else-if="data" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
      <div
        v-for="c in cards"
        :key="c.key"
        class="rounded-[var(--radius-md)] border p-5"
        style="border-color: var(--border-light); background: var(--bg-inbox)"
      >
        <div class="text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary)">
          {{ c.label }}
        </div>
        <div class="mt-2 text-3xl font-bold tabular-nums" style="color: var(--text-primary)">
          {{ data[c.key] }}
        </div>
        <p v-if="c.hint" class="mt-1 text-xs" style="color: var(--text-secondary)">
          {{ c.hint }}
        </p>
      </div>
    </div>
  </section>
</template>
