<script setup lang="ts">
import { ref, watch } from 'vue'
import {
  ChevronDown,
  Sparkles,
  PenLine,
  ListChecks,
  Lightbulb,
} from 'lucide-vue-next'
import { fetchAiSummary, fetchAiSuggestions } from '../../api/ai'

const props = defineProps<{
  chatId: number | null
}>()

/** По умолчанию свёрнуто; данные AI запрашиваются только после раскрытия. */
const open = ref(false)
const summaryText = ref('Раскройте панель, чтобы загрузить резюме')
const suggestions = ref<Array<{ id: string; text: string }>>([])
const loading = ref(false)
const errorText = ref<string | null>(null)

watch(
  () => [props.chatId, open.value] as const,
  async ([id, isOpen], prev) => {
    const prevId = prev?.[0]
    if (id == null) {
      suggestions.value = []
      errorText.value = null
      summaryText.value = 'Выберите обращение'
      return
    }
    if (!isOpen) {
      if (id !== prevId) {
        suggestions.value = []
        errorText.value = null
        summaryText.value = 'Раскройте панель, чтобы загрузить резюме'
      }
      return
    }
    suggestions.value = []
    errorText.value = null
    summaryText.value = 'Загрузка...'
    loading.value = true
    try {
      const [summary, sug] = await Promise.all([
        fetchAiSummary(id).catch(() => null),
        fetchAiSuggestions(id).catch(() => null),
      ])
      if (summary) {
        summaryText.value =
          summary.summary?.trim() ||
          summary.intent_tag?.trim() ||
          'Нет данных AI для этого чата.'
      } else {
        summaryText.value = 'Не удалось загрузить резюме'
      }
      if (sug?.replies?.length) {
        suggestions.value = sug.replies
      }
    } catch {
      errorText.value = 'Ошибка загрузки AI'
      summaryText.value = 'Не удалось загрузить резюме'
    } finally {
      loading.value = false
    }
  },
  { immediate: true },
)
</script>

<template>
  <div class="ai-panel" :class="{ collapsed: !open }">
    <div class="ai-panel-header" role="button" tabindex="0" @click="open = !open" @keydown.enter.prevent="open = !open">
      <div class="ai-panel-title">
        <Sparkles class="h-3.5 w-3.5 shrink-0" />
        <span>AI-ассистент</span>
      </div>
      <div class="ai-panel-toggle">
        <ChevronDown class="h-3.5 w-3.5" />
      </div>
    </div>
    <div class="ai-panel-body">
      <div id="aiSummary" class="ai-summary">
        <div class="ai-summary-label">
          Резюме обращения
        </div>
        <div id="aiSummaryText">
          {{ summaryText }}
        </div>
        <p v-if="errorText" class="mt-1 text-[11px]" style="color: var(--text-muted)">
          {{ errorText }}
        </p>
      </div>
      <div class="ai-actions">
        <button type="button" class="ai-action-btn" id="aiDraft" disabled title="Скоро">
          <PenLine class="shrink-0" />
          Черновик ответа
        </button>
        <button type="button" class="ai-action-btn" id="aiVariants" disabled title="Скоро">
          <ListChecks class="shrink-0" />
          3 варианта
        </button>
        <button type="button" class="ai-action-btn" id="aiSolution" disabled title="Скоро">
          <Lightbulb class="shrink-0" />
          Рекомендация
        </button>
      </div>
      <div
        v-if="suggestions.length > 0"
        id="aiSuggestions"
        class="ai-suggestions"
      >
        <div
          v-for="s in suggestions"
          :key="s.id"
          class="ai-suggestion"
        >
          <div class="ai-suggestion-label">
            Вариант
          </div>
          {{ s.text }}
        </div>
      </div>
    </div>
  </div>
</template>
