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
import { useChatStore } from '../../stores/chatStore'
import { useMessageStore } from '../../stores/messageStore'

const props = defineProps<{
  chatId: number | null
  /** Секция треда показывает панель (v-show); при true — разворачиваем тело панели. */
  reveal?: boolean
}>()

const emit = defineEmits<{
  insertComposerText: [text: string]
  notify: [message: string]
}>()

const chatStore = useChatStore()
const messageStore = useMessageStore()

const open = ref(false)
const summaryText = ref('Раскройте панель, чтобы загрузить резюме')
const insertableSummary = ref<string | null>(null)
const suggestions = ref<Array<{ id: string; text: string }>>([])
const loading = ref(false)
const suggestionsLoading = ref(false)
const errorText = ref<string | null>(null)

let prefetchDebounce: ReturnType<typeof setTimeout> | null = null

/** Хвост треда: новое сообщение → фоновое обновление AI. */
const threadTailRevision = computed(() => {
  const id = props.chatId
  if (id == null || chatStore.selectedChatId !== id) {
    return 0
  }
  const msgs = messageStore.messages
  if (msgs.length === 0) {
    return 0
  }
  const last = msgs[msgs.length - 1]!
  return last.id * 1_000_000 + msgs.length
})

function isPlaceholderSummary(text: string): boolean {
  const t = text.trim()
  return (
    t === 'Выберите обращение'
    || t === 'Раскройте панель, чтобы загрузить резюме'
    || t === 'Загрузка...'
  )
}

async function runAiFetch(id: number, mode: 'full' | 'quiet'): Promise<void> {
  const quiet = mode === 'quiet'
  if (!quiet) {
    loading.value = true
    errorText.value = null
    if (isPlaceholderSummary(summaryText.value) || summaryText.value === 'Не удалось загрузить резюме') {
      summaryText.value = 'Загрузка...'
    }
  }
  try {
    const [summary, sug] = await Promise.all([
      fetchAiSummary(id).catch(() => null),
      fetchAiSuggestions(id).catch(() => null),
    ])
    if (props.chatId !== id) {
      return
    }
    if (summary) {
      const body =
        summary.summary?.trim()
        || summary.intent_tag?.trim()
        || ''
      summaryText.value = body || 'Нет данных AI для этого чата.'
      insertableSummary.value = body || null
    } else if (!quiet) {
      summaryText.value = 'Не удалось загрузить резюме'
      insertableSummary.value = null
    }

    if (sug?.replies?.length) {
      suggestions.value = sug.replies
    } else if (!quiet) {
      suggestions.value = []
    }
  } catch {
    if (!quiet) {
      errorText.value = 'Ошибка загрузки AI'
      summaryText.value = 'Не удалось загрузить резюме'
      insertableSummary.value = null
    }
  } finally {
    if (!quiet) {
      loading.value = false
    }
  }
}

function scheduleQuietPrefetch(id: number): void {
  if (prefetchDebounce != null) {
    clearTimeout(prefetchDebounce)
  }
  prefetchDebounce = setTimeout(() => {
    prefetchDebounce = null
    if (props.chatId === id) {
      void runAiFetch(id, 'quiet')
    }
  }, 450)
}

watch(
  () => props.chatId,
  (id, prevId) => {
    if (prefetchDebounce != null) {
      clearTimeout(prefetchDebounce)
      prefetchDebounce = null
    }
    if (id == null) {
      suggestions.value = []
      errorText.value = null
      insertableSummary.value = null
      summaryText.value = 'Выберите обращение'
      return
    }
    if (id !== prevId) {
      suggestions.value = []
      errorText.value = null
      insertableSummary.value = null
      summaryText.value = open.value ? 'Загрузка...' : 'Раскройте панель, чтобы загрузить резюме'
      void runAiFetch(id, open.value ? 'full' : 'quiet')
    }
  },
  { immediate: true },
)

watch(
  () => threadTailRevision.value,
  () => {
    const id = props.chatId
    if (id == null || chatStore.selectedChatId !== id) {
      return
    }
    scheduleQuietPrefetch(id)
  },
)

watch(
  () => open.value,
  (isOpen) => {
    const id = props.chatId
    if (id == null || !isOpen) {
      return
    }
    const needsFull =
      isPlaceholderSummary(summaryText.value)
      || summaryText.value === 'Не удалось загрузить резюме'
      || (insertableSummary.value == null && suggestions.value.length === 0)
    void runAiFetch(id, needsFull ? 'full' : 'quiet')
  },
)

watch(
  () => props.reveal,
  (v) => {
    if (v == null) {
      return
    }
    open.value = v
  },
)

async function refreshSuggestionsOnly(): Promise<void> {
  const id = props.chatId
  if (id == null) {
    return
  }
  suggestionsLoading.value = true
  try {
    const sug = await fetchAiSuggestions(id).catch(() => null)
    if (props.chatId !== id) {
      return
    }
    suggestions.value = sug?.replies?.length ? sug.replies : []
    if (suggestions.value.length === 0) {
      emit('notify', 'Не удалось получить новые варианты')
    }
  } finally {
    suggestionsLoading.value = false
  }
}

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
