<script setup lang="ts">
import { ArrowRight, ArrowUpRight, Sparkles, Tag, X } from 'lucide-vue-next'
import { storeToRefs } from 'pinia'
import type { AiPanelContent } from '../../types/chat'
import { useChatStore } from '../../stores/chatStore'
import { useUiStore } from '../../stores/uiStore'

const chat = useChatStore()
const ui = useUiStore()
const { overlayVisible, panelOpen, aiProcessing, aiContent } = storeToRefs(chat)

function onTransfer(_content: AiPanelContent) {
  ui.pushToast('Обращение передано в отдел регистрации', 'success')
  chat.closeAiPanel()
}
</script>

<template>
  <div>
    <div
      class="ai-overlay-fade absolute inset-0 z-50 bg-black/40 dark:bg-black/60"
      :class="overlayVisible ? 'block opacity-100' : 'pointer-events-none hidden opacity-0'"
      aria-hidden="true"
      @click="chat.closeAiPanel()"
    />
    <div
      class="ai-panel-slide absolute bottom-0 left-0 right-0 z-[51] flex max-h-[75vh] flex-col overflow-hidden rounded-t-[20px] bg-white dark:bg-[var(--zinc-850)]"
      :class="panelOpen ? 'translate-y-0' : 'translate-y-full'"
    >
      <div
        class="mx-auto mb-0 mt-2.5 h-1 w-9 shrink-0 rounded-sm bg-[var(--zinc-300)] dark:bg-[var(--zinc-600)]"
        aria-hidden="true"
      />
      <div class="flex shrink-0 items-center justify-between px-5 pb-2.5 pt-3.5">
        <div
          class="flex items-center gap-2 text-base font-bold text-[var(--color-dark)] dark:text-[var(--zinc-100)]"
        >
          <Sparkles class="size-4 text-[var(--color-brand)]" aria-hidden="true" />
          AI-ассистент
        </div>
        <button
          type="button"
          class="flex size-8 cursor-pointer items-center justify-center rounded-xl border-none bg-[var(--color-brand-50)] text-[13px] text-[var(--color-brand)] dark:bg-[var(--zinc-800)] dark:text-[var(--color-brand-200)]"
          aria-label="Закрыть"
          @click="chat.closeAiPanel()"
        >
          <X class="size-4" />
        </button>
      </div>
      <div class="flex min-h-0 flex-1 flex-col gap-4 overflow-y-auto px-5 pb-5">
        <div
          v-if="aiProcessing"
          class="flex flex-col items-center justify-center gap-3 px-5 py-10"
        >
          <div
            class="size-8 rounded-full border-[3px] border-[var(--color-brand-50)] border-t-[var(--color-brand)] motion-safe:animate-spin dark:border-[var(--zinc-700)] dark:border-t-[var(--color-brand-200)]"
          />
          <div class="text-[13px] text-[var(--zinc-500)]">Анализирую переписку...</div>
        </div>
        <template v-else-if="aiContent">
          <div>
            <div
              class="mb-1.5 text-[11px] font-bold uppercase tracking-wide text-[var(--zinc-400)]"
            >
              Резюме обращения
            </div>
            <div
              class="rounded-[14px] border border-[var(--color-gray-line)] bg-[var(--zinc-50)] p-3.5 dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)]"
            >
              <p class="text-[13px] leading-relaxed text-[var(--zinc-600)] dark:text-[var(--zinc-300)]">
                {{ aiContent.summary }}
              </p>
              <div
                class="mt-2 inline-flex items-center gap-1 rounded-lg bg-[var(--color-brand-50)] px-2.5 py-1 text-[11px] font-semibold text-[var(--color-brand)] dark:bg-[rgba(85,23,94,0.2)] dark:text-[var(--color-brand-200)]"
              >
                <Tag class="size-2.5" aria-hidden="true" />
                {{ aiContent.intentTag }}
              </div>
            </div>
          </div>
          <div>
            <div
              class="mb-1.5 text-[11px] font-bold uppercase tracking-wide text-[var(--zinc-400)]"
            >
              Предложенные ответы
            </div>
            <div class="flex flex-col gap-2">
              <button
                v-for="r in aiContent.replies"
                :key="r.id"
                type="button"
                class="cursor-pointer rounded-[14px] border-[1.5px] border-[var(--color-gray-line)] bg-[var(--zinc-50)] p-3 text-left transition-all active:border-[var(--color-brand)] active:bg-[var(--color-brand-50)] dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)] dark:active:border-[var(--color-brand-500)] dark:active:bg-[rgba(85,23,94,0.15)]"
                @click="chat.useAiReply(r.text)"
              >
                <p class="text-[13px] leading-normal text-[var(--zinc-600)] dark:text-[var(--zinc-300)]">
                  {{ r.text }}
                </p>
                <div
                  class="mt-2 flex items-center gap-1 text-[11px] font-semibold text-[var(--color-brand)] dark:text-[var(--color-brand-200)]"
                >
                  <ArrowUpRight class="size-2.5" aria-hidden="true" />
                  Использовать
                </div>
              </button>
            </div>
          </div>
          <div>
            <div
              class="mb-1.5 text-[11px] font-bold uppercase tracking-wide text-[var(--zinc-400)]"
            >
              Рекомендуемое действие
            </div>
            <div
              class="rounded-[14px] border-[1.5px] border-[var(--color-brand-50)] bg-gradient-to-br from-[rgba(85,23,94,0.06)] to-[rgba(154,95,168,0.04)] p-3.5 dark:border-[var(--color-brand-900)] dark:from-[rgba(85,23,94,0.2)] dark:to-[rgba(154,95,168,0.08)]"
            >
              <div
                class="mb-1 flex items-center gap-1.5 text-[13px] font-semibold text-[var(--color-brand)] dark:text-[var(--color-brand-200)]"
              >
                <ArrowRight class="size-3" aria-hidden="true" />
                {{ aiContent.actionTitle }}
              </div>
              <div class="text-xs leading-normal text-[var(--zinc-500)] dark:text-[var(--zinc-400)]">
                {{ aiContent.actionDesc }}
              </div>
              <button
                type="button"
                class="mt-2.5 cursor-pointer rounded-[10px] border-none bg-[var(--color-brand)] px-4 py-2 text-xs font-semibold text-white active:opacity-[0.85]"
                @click="onTransfer(aiContent)"
              >
                {{ aiContent.actionButtonLabel }}
              </button>
            </div>
          </div>
        </template>
      </div>
    </div>
  </div>
</template>
