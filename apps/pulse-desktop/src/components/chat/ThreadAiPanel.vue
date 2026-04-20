<script setup lang="ts">
import { computed, ref, watch } from 'vue'
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

const emit = defineEmits<{
  insertComposerText: [text: string]
  notify: [message: string]
}>()

/** По умолчанию свёрнуто; данные AI запрашиваются только после раскрытия. */
const open = ref(false)
const summaryText = ref('Раскройте панель, чтобы загрузить резюме')
/** Текст резюме для кнопки «Рекомендация» (без служебных строк). */
const insertableSummary = ref<string | null>(null)
const suggestions = ref<Array<{ id: string; text: string }>>([])
const loading = ref(false)
const suggestionsLoading = ref(false)
const errorText = ref<string | null>(null)

async function refreshSuggestionsOnly(): Promise<void> {
  const id = props.chatId
  if (id == null || !open.value) {
    return
  }
  suggestionsLoading.value = true
  try {
    const sug = await fetchAiSuggestions(id).catch(() => null)
    suggestions.value = sug?.replies?.length ? sug.replies : []
    if (suggestions.value.length === 0) {
      emit('notify', 'Не удалось получить новые варианты')
    }
  } finally {
    suggestionsLoading.value = false
  }
}

watch(
  () => [props.chatId, open.value] as const,
  async ([id, isOpen], prev) => {
    const prevId = prev?.[0]
    if (id == null) {
      suggestions.value = []
      errorText.value = null
      insertableSummary.value = null
      summaryText.value = 'Выберите обращение'
      return
    }
    if (!isOpen) {
      if (id !== prevId) {
        suggestions.value = []
        errorText.value = null
        insertableSummary.value = null
        summaryText.value = 'Раскройте панель, чтобы загрузить резюме'
      }
      return
    }
    suggestions.value = []
    errorText.value = null
    insertableSummary.value = null
    summaryText.value = 'Загрузка...'
    loading.value = true
    try {
      const [summary, sug] = await Promise.all([
        fetchAiSummary(id).catch(() => null),
        fetchAiSuggestions(id).catch(() => null),
      ])
      if (summary) {
        const body =
          summary.summary?.trim()
          || summary.intent_tag?.trim()
          || ''
        summaryText.value = body || 'Нет данных AI для этого чата.'
        insertableSummary.value = body || null
      } else {
        summaryText.value = 'Не удалось загрузить резюме'
        insertableSummary.value = null
      }
      if (sug?.replies?.length) {
        suggestions.value = sug.replies
      }
    } catch {
      errorText.value = 'Ошибка загрузки AI'
      summaryText.value = 'Не удалось загрузить резюме'
      insertableSummary.value = null
    } finally {
      loading.value = false
    }
  },
  { immediate: true },
)

async function onDraftClick(): Promise<void> {
  if (props.chatId == null || panelBusy.value) {
    return
  }
  if (suggestions.value.length === 0) {
    await refreshSuggestionsOnly()
  }
  const first = suggestions.value[0]?.text?.trim()
  if (!first) {
    emit('notify', 'Нет варианта черновика от AI')
    return
  }
  emit('insertComposerText', first)
}

async function onVariantsClick(): Promise<void> {
  if (props.chatId == null || panelBusy.value) {
    return
  }
  await refreshSuggestionsOnly()
}

function onRecommendationClick(): void {
  const text = insertableSummary.value?.trim()
  if (!text) {
    emit('notify', 'Нет текста рекомендации')
    return
  }
  emit('insertComposerText', text)
}

function onSuggestionPick(text: string): void {
  const t = text.trim()
  if (!t) {
    return
  }
  emit('insertComposerText', t)
}

const panelBusy = computed(() => loading.value || suggestionsLoading.value)
const canInsertRecommendation = computed(
  () => insertableSummary.value != null && insertableSummary.value.trim() !== '',
)
</script>

<template>
  <div class="ai-panel no-drag-region" :class="{ collapsed: !open }">
    <div
      class="ai-panel-header"
      role="button"
      tabindex="0"
      @click="open = !open"
      @keydown.enter.prevent="open = !open"
    >
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
        <button
          type="button"
          class="ai-action-btn"
          id="aiDraft"
          title="Вставить первый вариант ответа в поле ввода"
          :disabled="chatId == null || !open || panelBusy"
          @click="onDraftClick"
        >
          <PenLine class="shrink-0" />
          Черновик ответа
        </button>
        <button
          type="button"
          class="ai-action-btn"
          id="aiVariants"
          title="Запросить варианты ответа заново"
          :disabled="chatId == null || !open || panelBusy"
          @click="onVariantsClick"
        >
          <ListChecks class="shrink-0" />
          3 варианта
        </button>
        <button
          type="button"
          class="ai-action-btn"
          id="aiSolution"
          title="Вставить резюме в поле ввода"
          :disabled="chatId == null || !open || panelBusy || !canInsertRecommendation"
          @click="onRecommendationClick"
        >
          <Lightbulb class="shrink-0" />
          Рекомендация
        </button>
      </div>
      <div
        v-if="suggestions.length > 0"
        id="aiSuggestions"
        class="ai-suggestions"
      >
        <button
          v-for="s in suggestions"
          :key="s.id"
          type="button"
          class="ai-suggestion"
          :disabled="chatId == null || panelBusy"
          @click="onSuggestionPick(s.text)"
        >
          <div class="ai-suggestion-label">
            Вариант
          </div>
          {{ s.text }}
        </button>
      </div>
    </div>
  </div>
</template>
