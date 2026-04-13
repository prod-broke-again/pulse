<script setup lang="ts">
import { Paperclip, Send, Sparkles, X, Zap } from 'lucide-vue-next'
import { storeToRefs } from 'pinia'
import { computed, ref } from 'vue'
import { useChatStore } from '../../stores/chatStore'
import type { ReplyMarkupButton } from '../../types/chat'

const chat = useChatStore()
const { composerText, canSend, pendingReplyMarkup, cannedQuickReplies, quickLinkPresets } =
  storeToRefs(chat)
const fileInput = ref<HTMLInputElement | null>(null)
const actionsDetails = ref<HTMLDetailsElement | null>(null)

function pickPreset(btn: ReplyMarkupButton) {
  chat.addReplyMarkupPreset(btn)
  actionsDetails.value?.removeAttribute('open')
}

function onPickFiles(e: Event) {
  const input = e.target as HTMLInputElement
  const files = input.files
  if (!files?.length) return
  void chat.sendWithAttachments(Array.from(files))
  input.value = ''
}

/** Used when API returns no canned responses. */
const fallbackQuickReplies = [
  {
    label: 'Уточню информацию',
    text: 'Спасибо за обращение! Сейчас уточню информацию.',
  },
  {
    label: 'Передам специалисту',
    text: 'Пожалуйста, подождите, я передам ваш вопрос специалисту.',
  },
  {
    label: 'Рады помочь',
    text: 'Рады были помочь! Если возникнут вопросы — пишите.',
  },
]

const quickReplies = computed(() =>
  cannedQuickReplies.value.length > 0 ? cannedQuickReplies.value : fallbackQuickReplies,
)

function onSend() {
  chat.sendMessage()
}
</script>

<template>
  <div
    class="shrink-0 border-t border-[var(--color-gray-line)] bg-white dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-850)]"
  >
    <div class="-mx-1 flex gap-1.5 overflow-x-auto px-4 pb-0 pt-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
      <button
        type="button"
        class="shrink-0 cursor-pointer whitespace-nowrap rounded-2xl border-[1.5px] border-[var(--color-brand-200)] bg-gradient-to-br from-[rgba(85,23,94,0.1)] to-[rgba(154,95,168,0.1)] px-3 py-1.5 text-xs font-medium text-[var(--color-brand)] transition-all active:bg-[var(--color-brand)] active:text-white dark:border-[var(--color-brand-500)] dark:from-[rgba(85,23,94,0.3)] dark:to-[rgba(154,95,168,0.15)] dark:text-[var(--color-brand-200)]"
        @click="chat.openAiPanel()"
      >
        <Sparkles class="mr-0.5 inline size-2.5 align-middle" aria-hidden="true" />
        AI-ответ
      </button>
      <button
        v-for="(q, i) in quickReplies"
        :key="i"
        type="button"
        class="shrink-0 cursor-pointer whitespace-nowrap rounded-2xl border-[1.5px] border-[var(--color-brand-50)] bg-[var(--color-brand-50)] px-3 py-1.5 text-xs font-medium text-[var(--color-brand)] transition-all active:bg-[var(--color-brand)] active:text-white dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)] dark:text-[var(--color-brand-200)]"
        @click="chat.insertQuickReply(q.text)"
      >
        {{ q.label }}
      </button>
    </div>
    <div class="px-3 pt-2.5">
      <div
        v-if="pendingReplyMarkup.length > 0"
        class="mb-2 flex flex-wrap gap-2"
      >
        <span
          v-for="(chip, i) in pendingReplyMarkup"
          :key="`${chip.url}-${i}`"
          class="inline-flex max-w-full items-center gap-1 rounded-full border border-[var(--zinc-600)]/50 bg-[var(--zinc-800)] px-2.5 py-1 text-xs font-medium text-[var(--zinc-200)] dark:bg-[var(--zinc-800)]"
        >
          <span class="truncate">{{ chip.text }}</span>
          <button
            type="button"
            class="flex size-5 shrink-0 cursor-pointer items-center justify-center rounded-full text-[var(--zinc-400)] transition-colors hover:bg-[var(--zinc-700)] hover:text-white"
            :aria-label="`Удалить: ${chip.text}`"
            @click="chat.removeReplyMarkupPreset(i)"
          >
            <X class="size-3.5" />
          </button>
        </span>
      </div>
    </div>
    <div
      class="flex items-end gap-2 px-3 pb-[calc(10px+var(--safe-bottom))] pt-0"
    >
      <input
        ref="fileInput"
        type="file"
        class="hidden"
        accept="image/*,application/pdf,audio/*,.m4a,.aac,.webm"
        multiple
        @change="onPickFiles"
      />
      <details
        ref="actionsDetails"
        class="group relative shrink-0"
      >
        <summary
          class="flex size-10 cursor-pointer list-none items-center justify-center rounded-xl border-none bg-[var(--zinc-100)] text-[var(--zinc-500)] marker:hidden dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-400)] [&::-webkit-details-marker]:hidden"
          aria-label="Действия"
        >
          <Zap class="size-4" aria-hidden="true" />
        </summary>
        <div
          class="absolute bottom-[calc(100%+6px)] left-0 z-20 min-w-[220px] overflow-hidden rounded-xl border border-[var(--zinc-700)] bg-[var(--zinc-850)] py-1 shadow-lg dark:bg-[var(--zinc-850)]"
        >
          <div
            v-if="quickLinkPresets.length === 0"
            class="px-3 py-2.5 text-xs text-[var(--zinc-400)]"
          >
            Нет быстрых ссылок для этого источника
          </div>
          <button
            v-for="(preset, idx) in quickLinkPresets"
            :key="`${preset.url}-${idx}`"
            type="button"
            class="flex w-full cursor-pointer px-3 py-2.5 text-left text-sm text-[var(--zinc-100)] transition-colors hover:bg-[var(--zinc-800)]"
            @click="pickPreset(preset)"
          >
            {{ preset.text }}
          </button>
        </div>
      </details>
      <button
        type="button"
        class="flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-xl border-none bg-[var(--zinc-100)] text-base text-[var(--zinc-500)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-400)]"
        aria-label="Вложение"
        @click="fileInput?.click()"
      >
        <Paperclip class="size-4" />
      </button>
      <div
        class="flex min-h-10 max-h-[120px] flex-1 items-center rounded-[20px] border-[1.5px] border-transparent bg-[var(--zinc-100)] px-4 py-2 transition-[border-color] focus-within:border-[var(--color-brand-200)] dark:bg-[var(--zinc-800)]"
      >
        <input
          :value="composerText"
          type="text"
          class="max-h-[100px] min-w-0 flex-1 border-none bg-transparent text-sm leading-snug text-[var(--color-dark)] outline-none placeholder:text-[var(--zinc-400)] dark:text-[var(--zinc-100)]"
          placeholder="Написать ответ..."
          autocomplete="off"
          @input="chat.setComposerText(($event.target as HTMLInputElement).value)"
          @keydown.enter.prevent="canSend && onSend()"
        />
      </div>
      <button
        type="button"
        class="flex size-10 shrink-0 cursor-pointer items-center justify-center rounded-full border-none bg-[var(--color-brand)] text-base text-white transition-all active:scale-[0.93] disabled:bg-[var(--zinc-200)] disabled:text-[var(--zinc-400)] dark:disabled:bg-[var(--zinc-700)] dark:disabled:text-[var(--zinc-500)]"
        :disabled="!canSend"
        aria-label="Отправить"
        @click="onSend()"
      >
        <Send class="size-4" />
      </button>
    </div>
  </div>
</template>
