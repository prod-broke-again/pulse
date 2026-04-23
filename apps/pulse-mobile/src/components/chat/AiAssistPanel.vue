<script setup lang="ts">
import { Lightbulb, ListChecks, PenLine, Sparkles, X } from 'lucide-vue-next'
import { storeToRefs } from 'pinia'
import { useChatStore } from '../../stores/chatStore'
import { useChatUiStore } from '../../stores/chatUiStore'
import { useUiStore } from '../../stores/uiStore'

const chat = useChatStore()
const chatUi = useChatUiStore()
const ui = useUiStore()
const { overlayVisible, panelOpen, aiProcessing } = storeToRefs(chatUi)
const { aiContent, aiPanelSuggestionsLoading } = storeToRefs(chat)

const panelBusy = () => aiProcessing.value || aiPanelSuggestionsLoading.value

async function onDraftClick() {
  if (panelBusy()) {
    return
  }
  await chat.insertAiDraftReply()
}

async function onVariantsClick() {
  if (panelBusy()) {
    return
  }
  await chat.refreshAiPanelSuggestions()
  if ((aiContent.value?.replies.length ?? 0) === 0) {
    ui.pushToast('Не удалось получить новые варианты', 'error')
  }
}

function onRecommendationClick() {
  if (panelBusy()) {
    return
  }
  chat.insertAiSummaryRecommendation()
}
</script>

<template>
  <div>
    <div
      class="ai-overlay-fade absolute inset-0 z-50 bg-black/40 dark:bg-black/60"
      :class="overlayVisible ? 'block opacity-100' : 'pointer-events-none hidden opacity-0'"
      aria-hidden="true"
      @click="chatUi.closeAiPanel()"
    />
    <div
      class="ai-panel-slide absolute bottom-0 left-0 right-0 z-[51] flex max-h-[78vh] flex-col overflow-hidden rounded-t-[20px] border-t border-[var(--color-gray-line)] bg-white dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-850)]"
      :class="panelOpen ? 'translate-y-0' : 'translate-y-full'"
    >
      <div
        class="mx-auto mb-0 mt-2.5 h-1 w-9 shrink-0 rounded-sm bg-[var(--zinc-300)] dark:bg-[var(--zinc-600)]"
        aria-hidden="true"
      />
      <div class="flex shrink-0 items-center justify-between border-b border-[var(--color-gray-line)]/80 px-4 pb-2.5 pt-1 dark:border-[var(--zinc-700)]/80">
        <div
          class="flex min-w-0 items-center gap-2 text-base font-bold text-[var(--color-dark)] dark:text-[var(--zinc-100)]"
        >
          <Sparkles class="size-4 shrink-0 text-[var(--color-brand)]" aria-hidden="true" />
          AI-ассистент
        </div>
        <button
          type="button"
          class="flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-xl border-none bg-[var(--color-brand-50)] text-[13px] text-[var(--color-brand)] dark:bg-[var(--zinc-800)] dark:text-[var(--color-brand-200)]"
          aria-label="Закрыть"
          @click="chatUi.closeAiPanel()"
        >
          <X class="size-4" />
        </button>
      </div>
      <div class="flex min-h-0 flex-1 flex-col overflow-y-auto overscroll-contain px-4 pb-[calc(1rem+var(--safe-bottom))] pt-3">
        <div
          v-if="aiProcessing"
          class="flex flex-col items-center justify-center gap-3 py-10"
        >
          <div
            class="size-8 rounded-full border-[3px] border-[var(--color-brand-50)] border-t-[var(--color-brand)] motion-safe:animate-spin dark:border-[var(--zinc-700)] dark:border-t-[var(--color-brand-200)]"
          />
          <div class="text-center text-[13px] text-[var(--zinc-500)]">Загружаю резюме и подсказки...</div>
        </div>
        <template v-else-if="aiContent">
          <div class="mb-4 min-w-0">
            <div
              class="mb-1.5 text-[11px] font-bold uppercase tracking-wide text-[var(--zinc-400)]"
            >
              Резюме обращения
            </div>
            <div
              class="rounded-[14px] border border-[var(--color-gray-line)] bg-[var(--zinc-50)] p-3.5 dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)]"
            >
              <p
                class="text-[13px] leading-[1.55] break-words whitespace-pre-wrap text-[var(--zinc-600)] [overflow-wrap:anywhere] dark:text-[var(--zinc-300)]"
              >
                {{ aiContent.summary }}
              </p>
              <div
                v-if="aiContent.intentTag"
                class="mt-2 inline-flex max-w-full items-center gap-1 rounded-lg bg-[var(--color-brand-50)] px-2.5 py-1 text-[11px] font-semibold break-words text-[var(--color-brand)] dark:bg-[rgba(85,23,94,0.2)] dark:text-[var(--color-brand-200)]"
              >
                {{ aiContent.intentTag }}
              </div>
            </div>
          </div>
          <div
            class="mb-3 flex min-w-0 flex-wrap gap-2 border-b border-dashed border-[var(--zinc-200)] pb-3 dark:border-[var(--zinc-600)]"
          >
            <button
              type="button"
              class="ai-action-btn-mobile flex min-w-0 flex-1 items-center justify-center gap-1.5 rounded-xl border border-[var(--color-gray-line)] bg-white px-2.5 py-2.5 text-left text-xs font-medium text-[var(--zinc-700)] shadow-sm transition active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 dark:border-[var(--zinc-600)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-200)]"
              :disabled="panelBusy() || !aiContent"
              @click="onDraftClick"
            >
              <PenLine class="size-3.5 shrink-0" aria-hidden="true" />
              <span class="min-w-0">Черновик ответа</span>
            </button>
            <button
              type="button"
              class="ai-action-btn-mobile flex min-w-0 flex-1 items-center justify-center gap-1.5 rounded-xl border border-[var(--color-gray-line)] bg-white px-2.5 py-2.5 text-left text-xs font-medium text-[var(--zinc-700)] shadow-sm transition active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 dark:border-[var(--zinc-600)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-200)]"
              :disabled="panelBusy() || !aiContent"
              @click="onVariantsClick"
            >
              <ListChecks
                class="size-3.5 shrink-0"
                :class="{ 'motion-safe:animate-spin': aiPanelSuggestionsLoading }"
                aria-hidden="true"
              />
              <span class="min-w-0">3 варианта</span>
            </button>
            <button
              type="button"
              class="ai-action-btn-mobile flex min-w-0 basis-full items-center justify-center gap-1.5 rounded-xl border border-[var(--color-brand-200)] bg-[var(--color-brand-50)] px-2.5 py-2.5 text-left text-xs font-medium text-[var(--color-brand)] transition active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-50 dark:border-[var(--zinc-600)] dark:bg-[rgba(85,23,94,0.2)] dark:text-[var(--color-brand-200)]"
              :disabled="panelBusy() || !aiContent"
              @click="onRecommendationClick"
            >
              <Lightbulb class="size-3.5 shrink-0" aria-hidden="true" />
              <span class="min-w-0">Рекомендация</span>
            </button>
          </div>
          <div v-if="aiContent.replies.length > 0" class="min-w-0">
            <div
              class="mb-1.5 text-[11px] font-bold uppercase tracking-wide text-[var(--zinc-400)]"
            >
              Предложенные ответы
            </div>
            <div class="flex min-w-0 flex-col gap-2">
              <button
                v-for="r in aiContent.replies"
                :key="r.id"
                type="button"
                class="w-full min-w-0 cursor-pointer rounded-[14px] border-[1.5px] border-[var(--color-gray-line)] bg-[var(--zinc-50)] p-3 text-left transition active:border-[var(--color-brand)] active:bg-[var(--color-brand-50)] dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)] dark:active:border-[var(--color-brand-500)] dark:active:bg-[rgba(85,23,94,0.15)]"
                @click="chat.insertFromAi(r.text)"
              >
                <div class="text-[9px] font-semibold uppercase tracking-wide text-[var(--zinc-400)]">Вариант</div>
                <p
                  class="mt-0.5 text-[13px] leading-[1.45] break-words text-[var(--zinc-600)] [overflow-wrap:anywhere] dark:text-[var(--zinc-300)]"
                >
                  {{ r.text }}
                </p>
                <div
                  class="mt-2 text-[11px] font-semibold text-[var(--color-brand)] dark:text-[var(--color-brand-200)]"
                >
                  Вставить в поле
                </div>
              </button>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>
