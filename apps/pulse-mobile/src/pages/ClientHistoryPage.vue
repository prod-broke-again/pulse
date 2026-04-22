<script setup lang="ts">
import { ChevronLeft, Loader2 } from 'lucide-vue-next'
import { onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import * as chatApi from '../api/chatRepository'
import { mapApiChatToPreview } from '../mappers/chatMapper'
import { useInboxStore } from '../stores/inboxStore'
import type { ChatPreviewItem } from '../types/chat'

const route = useRoute()
const router = useRouter()
const inbox = useInboxStore()

const items = ref<ChatPreviewItem[]>([])
const loading = ref(true)
const err = ref<string | null>(null)

const externalId = ref<string>('')

async function load() {
  const eid = typeof route.params.externalUserId === 'string' ? route.params.externalUserId : ''
  externalId.value = eid
  if (!eid.trim()) {
    err.value = 'Нет идентификатора клиента'
    loading.value = false
    return
  }
  loading.value = true
  err.value = null
  try {
    const listRes = await chatApi.fetchChats({
      tab: 'all',
      status: 'all',
      search: eid.trim(),
      page: 1,
      per_page: 100,
    })
    const needle = eid.trim()
    const rows = listRes.data
      .filter((r) => {
        const ext = String(r.external_user_id ?? '')
        return ext === needle || ext.includes(needle) || needle.includes(ext)
      })
      .map(mapApiChatToPreview)
    items.value = rows
  } catch {
    err.value = 'Не удалось загрузить историю'
  } finally {
    loading.value = false
  }
}

onMounted(() => {
  inbox.setBottomNav('inbox')
  void load()
})

watch(
  () => route.params.externalUserId,
  () => {
    void load()
  },
)
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
        @click="router.back()"
      >
        <ChevronLeft class="size-6" />
      </button>
      <div class="min-w-0 flex-1">
        <div class="text-lg font-bold text-[var(--color-dark)] dark:text-[var(--zinc-50)]">
          История обращений
        </div>
        <div class="truncate text-xs text-[var(--zinc-500)]" :title="externalId">
          Клиент: {{ externalId || '—' }}
        </div>
      </div>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto px-4 py-3">
      <p v-if="err" class="text-sm text-red-500">
        {{ err }}
      </p>
      <div v-else-if="loading" class="flex justify-center py-16">
        <Loader2 class="size-8 animate-spin text-[var(--color-brand)]" />
      </div>
      <p v-else-if="items.length === 0" class="text-center text-sm text-[var(--zinc-500)]">
        Нет чатов по этому идентификатору (поиск по API может включать лишние совпадения — список отфильтрован).
      </p>
      <ul v-else class="space-y-2">
        <li
          v-for="c in items"
          :key="c.id"
        >
          <button
            type="button"
            class="flex w-full items-center gap-3 rounded-2xl border border-[var(--color-gray-line)] bg-white p-3 text-left dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)]"
            @click="router.push({ name: 'chat', params: { id: c.id } })"
          >
            <div
              class="flex size-10 shrink-0 items-center justify-center rounded-full text-[11px] font-bold text-white"
              :class="{
                'bg-[#2AABEE]': c.channel === 'tg',
                'bg-[#0077FF]': c.channel === 'vk',
                'bg-[var(--color-brand-300)]': c.channel === 'web',
                'bg-[#6b4f7c]': c.channel === 'max',
              }"
            >
              {{ c.initials }}
            </div>
            <div class="min-w-0 flex-1">
              <div class="text-sm font-semibold text-[var(--color-dark)] dark:text-[var(--zinc-100)]">
                {{ c.name }}
              </div>
              <div class="truncate text-xs text-[var(--zinc-500)]">
                {{ c.preview }} · {{ c.department }} ·
                <span :class="c.status === 'closed' ? 'text-[var(--zinc-400)]' : 'text-[#22c55e]'">
                  {{ c.status === 'closed' ? 'закрыт' : 'открыт' }}
                </span>
              </div>
            </div>
            <div class="shrink-0 text-[10px] text-[var(--zinc-400)]">
              {{ c.timeLabel }}
            </div>
          </button>
        </li>
      </ul>
    </div>
  </div>
</template>
