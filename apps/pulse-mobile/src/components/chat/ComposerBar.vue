<script setup lang="ts">
import { Paperclip, Send, Sparkles } from 'lucide-vue-next'
import { storeToRefs } from 'pinia'
import { ref } from 'vue'
import { useChatStore } from '../../stores/chatStore'

const chat = useChatStore()
const { composerText, canSend } = storeToRefs(chat)
const fileInput = ref<HTMLInputElement | null>(null)

function onPickFiles(e: Event) {
  const input = e.target as HTMLInputElement
  const files = input.files
  if (!files?.length) return
  void chat.sendWithAttachments(Array.from(files))
  input.value = ''
}

const quickReplies = [
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
    <div
      class="flex items-end gap-2 px-3 pb-[calc(10px+var(--safe-bottom))] pt-2.5"
    >
      <input
        ref="fileInput"
        type="file"
        class="hidden"
        accept="image/*,application/pdf,audio/*,.m4a,.aac,.webm"
        multiple
        @change="onPickFiles"
      />
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
