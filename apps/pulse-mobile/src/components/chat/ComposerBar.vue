<script setup lang="ts">
import { FileUp, MoreHorizontal, Send, Sparkles, X, Zap } from 'lucide-vue-next'
import { storeToRefs } from 'pinia'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useChatStore } from '../../stores/chatStore'
import type { ReplyMarkupButton } from '../../types/chat'

const chat = useChatStore()
const {
  composerText,
  canSend,
  composerLocked,
  composerFocusSeq,
  threadMeta,
  pendingReplyMarkup,
  cannedQuickReplies,
  quickLinkPresets,
  replyToMessageId,
} = storeToRefs(chat)
const fileInput = ref<HTMLInputElement | null>(null)
const composerTextareaRef = ref<HTMLTextAreaElement | null>(null)
const menuDetails = ref<HTMLDetailsElement | null>(null)

function closeMenu() {
  menuDetails.value?.removeAttribute('open')
}

function pickQuickLink(btn: ReplyMarkupButton) {
  chat.addReplyMarkupPreset(btn)
  closeMenu()
}

function onMenuAttach() {
  closeMenu()
  fileInput.value?.click()
}

function onMenuOpenAi() {
  closeMenu()
  chat.openAiPanel()
}

function onMenuInsertTemplate(text: string) {
  chat.insertQuickReply(text)
  closeMenu()
}

function onPickFiles(e: Event) {
  const input = e.target as HTMLInputElement
  const files = input.files
  if (!files?.length) {
    return
  }
  void chat.sendWithAttachments(Array.from(files))
  input.value = ''
}

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
  if (!canSend.value) {
    return
  }
  chat.sendMessage()
}

function onComposerKeydown(e: KeyboardEvent) {
  if (e.key === 'Enter' && !e.shiftKey) {
    e.preventDefault()
    onSend()
  }
}

function focusComposer() {
  void nextTick(() => {
    composerTextareaRef.value?.focus()
  })
}

defineExpose({
  insertFromAi: (text: string) => {
    chat.insertFromAi(text)
  },
  focusComposer,
})

watch(composerFocusSeq, () => {
  focusComposer()
  void nextTick(() => {
    syncTextareaHeight()
  })
})

const isChatClosed = computed(() => threadMeta.value?.status === 'closed')
const isOtherModerator = computed(
  () => composerLocked.value && !isChatClosed.value,
)

const isMultiline = computed(() => composerText.value.includes('\n'))

const COMPOSER_LINE_H = 40

function syncTextareaHeight() {
  const el = composerTextareaRef.value
  if (!el) {
    return
  }
  el.style.height = 'auto'
  const h = Math.min(Math.max(el.scrollHeight, COMPOSER_LINE_H), 168)
  el.style.height = `${h}px`
}

watch(
  composerText,
  (t) => {
    if (t.trim().length > 0) {
      chat.scheduleTypingNotify()
    }
    void nextTick(() => {
      syncTextareaHeight()
    })
  },
)

function onComposerInput(e: Event) {
  const t = (e.target as HTMLTextAreaElement).value
  chat.setComposerText(t)
  void nextTick(() => {
    syncTextareaHeight()
  })
}

onMounted(() => {
  void nextTick(() => {
    syncTextareaHeight()
  })
})
</script>

<template>
  <div
    class="shrink-0 border-t border-[var(--color-gray-line)] bg-white dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-850)]"
  >
    <div class="px-3 pt-2.5">
      <div
        v-if="isChatClosed"
        class="mb-2 rounded-xl border border-[var(--zinc-300)] bg-[var(--zinc-100)] px-3 py-2 text-xs text-[var(--zinc-700)] dark:border-[var(--zinc-600)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-200)]"
        role="status"
      >
        Чат закрыт. Нажмите «Вернуть в работу» в шапке, чтобы отвечать.
      </div>
      <div
        v-else-if="isOtherModerator"
        class="mb-2 rounded-xl border border-amber-200/80 bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100"
        role="status"
      >
        Чат у другого модератора. Нажмите «Назначить» в шапке, чтобы ответить.
      </div>
      <div
        v-if="replyToMessageId != null"
        class="mb-2 flex items-start gap-2 rounded-xl border border-[var(--color-brand-200)]/40 bg-[var(--color-brand-bg)] px-3 py-2 text-xs dark:border-[var(--zinc-600)] dark:bg-[var(--zinc-800)]"
      >
        <div class="min-w-0 flex-1">
          <div class="mb-0.5 font-semibold text-[var(--color-brand)] dark:text-[var(--color-brand-200)]">
            Ответ на сообщение
          </div>
          <p class="line-clamp-2 text-[var(--zinc-600)] dark:text-[var(--zinc-300)]">
            {{
              chat.messages.find((m) => Number(m.id) === replyToMessageId)?.text?.slice(0, 240) || '…'
            }}
          </p>
        </div>
        <button
          type="button"
          class="flex size-8 shrink-0 items-center justify-center rounded-full text-[var(--zinc-500)] transition-colors hover:bg-[var(--zinc-200)] dark:hover:bg-[var(--zinc-700)]"
          aria-label="Отменить ответ"
          @click="chat.clearReplyTarget()"
        >
          <X class="size-4" />
        </button>
      </div>
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
      class="flex min-h-0 items-end gap-2 px-3 pb-[calc(10px+var(--safe-bottom))] pt-0"
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
        ref="menuDetails"
        class="group relative shrink-0"
      >
        <summary
          class="flex size-10 cursor-pointer list-none items-center justify-center rounded-xl border-none bg-[var(--zinc-100)] text-[var(--zinc-500)] marker:hidden dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-400)] [&::-webkit-details-marker]:hidden"
          aria-label="Вложения, ИИ, шаблоны"
        >
          <MoreHorizontal class="size-5" aria-hidden="true" />
        </summary>
        <div
          class="composer-actions-menu absolute bottom-[calc(100%+8px)] left-0 z-40 max-h-[min(70vh,420px)] w-[min(calc(100vw-1.5rem),280px)] overflow-y-auto overscroll-contain rounded-xl border border-[var(--color-gray-line)] bg-white py-1.5 text-[var(--color-dark)] shadow-lg dark:border-[var(--zinc-600)]/80 dark:bg-[var(--zinc-850)] dark:text-[var(--zinc-100)]"
        >
          <div
            class="px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-[var(--zinc-500)]"
          >
            Действия
          </div>
          <button
            type="button"
            class="flex w-full items-center gap-2.5 px-3 py-2.5 text-left text-sm text-[var(--color-dark)] transition-colors active:bg-[var(--zinc-100)] dark:text-[var(--zinc-100)] dark:active:bg-[var(--zinc-800)]"
            :disabled="composerLocked"
            @click="onMenuAttach"
          >
            <FileUp class="size-4 shrink-0 opacity-80" aria-hidden="true" />
            Прикрепить файл
          </button>
          <button
            type="button"
            class="flex w-full items-center gap-2.5 px-3 py-2.5 text-left text-sm text-[var(--color-dark)] transition-colors active:bg-[var(--zinc-100)] dark:text-[var(--zinc-100)] dark:active:bg-[var(--zinc-800)]"
            @click="onMenuOpenAi"
          >
            <Sparkles
              class="size-4 shrink-0 text-[var(--color-brand)] dark:text-[var(--color-brand-200)]"
              aria-hidden="true"
            />
            AI-ассистент
          </button>

          <div
            v-if="quickReplies.length > 0"
            class="mt-1 border-t border-[var(--zinc-200)] pt-1.5 dark:border-[var(--zinc-700)]/80"
          >
            <div
              class="px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-[var(--zinc-500)]"
            >
              Шаблоны ответов
            </div>
            <button
              v-for="(q, i) in quickReplies"
              :key="`tpl-${i}`"
              type="button"
              class="flex w-full px-3 py-2.5 text-left text-sm leading-snug text-[var(--zinc-700)] transition-colors active:bg-[var(--zinc-100)] dark:text-[var(--zinc-200)] dark:active:bg-[var(--zinc-800)]"
              :disabled="composerLocked"
              @click="onMenuInsertTemplate(q.text)"
            >
              {{ q.label }}
            </button>
          </div>

          <div class="mt-1 border-t border-[var(--zinc-200)] pt-1.5 dark:border-[var(--zinc-700)]/80">
            <div
              class="px-3 py-1.5 text-[10px] font-semibold uppercase tracking-wide text-[var(--zinc-500)]"
            >
              Кнопки (ссылки)
            </div>
            <template v-if="quickLinkPresets.length > 0">
              <button
                v-for="(preset, idx) in quickLinkPresets"
                :key="`${preset.url}-${idx}`"
                type="button"
                class="flex w-full items-center gap-2 px-3 py-2.5 text-left text-sm leading-snug text-[var(--zinc-700)] transition-colors active:bg-[var(--zinc-100)] dark:text-[var(--zinc-200)] dark:active:bg-[var(--zinc-800)]"
                :disabled="composerLocked"
                @click="pickQuickLink(preset)"
              >
                <Zap class="size-3.5 shrink-0 text-[var(--zinc-500)]" aria-hidden="true" />
                <span class="min-w-0 break-words">{{ preset.text }}</span>
              </button>
            </template>
            <p
              v-else
              class="px-3 py-2 text-center text-xs text-[var(--zinc-500)]"
            >
              Нет кнопок-ссылок для этого источника
            </p>
          </div>
        </div>
      </details>
      <div
        class="composer-box-mobile flex min-h-10 min-w-0 max-h-[180px] flex-1 rounded-[20px] border-[1.5px] border-transparent bg-[var(--zinc-100)] px-2.5 transition-[border-color] focus-within:border-[var(--color-brand-200)] dark:bg-[var(--zinc-800)]"
        :class="isMultiline ? 'items-stretch py-1.5' : 'items-center py-0'"
      >
        <textarea
          id="chat-composer-textarea"
          ref="composerTextareaRef"
          :value="composerText"
          rows="1"
          class="composer-textarea-mobile max-h-[168px] min-w-0 flex-1 resize-none border-none bg-transparent text-sm text-[var(--color-dark)] outline-none placeholder:font-normal placeholder:text-[var(--zinc-400)] dark:text-[var(--zinc-100)]"
          :class="[
            isMultiline
              ? 'self-stretch py-1.5 font-medium leading-[1.5]'
              : 'self-center py-0 font-normal leading-10',
          ]"
          placeholder="Написать ответ…"
          autocomplete="off"
          :disabled="composerLocked"
          @input="onComposerInput"
          @keydown="onComposerKeydown"
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
