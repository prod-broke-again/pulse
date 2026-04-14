<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Paperclip, Zap, Sparkles, Type, SendHorizontal, Loader2, X, FileIcon, Save, Link2 } from 'lucide-vue-next'
import { sendTypingIndicator } from '../../api/chats'
import { uploadFile } from '../../api/uploads'
import { useChatStore } from '../../stores/chatStore'
import { useCannedResponseStore } from '../../stores/cannedResponseStore'
import { useQuickLinkStore } from '../../stores/quickLinkStore'
import type { ApiQuickLink } from '../../types/dto/quick-link.types'

const props = defineProps<{
  isSending?: boolean
  /** Ответ на сообщение (превью в композере). */
  replyToPreview?: { id: number; text: string } | null
  /** Чат назначен другому модератору — ввод недоступен до перехвата. */
  composerLocked?: boolean
  composerLockHint?: string
}>()

const emit = defineEmits<{
  (
    e: 'send',
    text: string,
    attachments: string[],
    replyMarkup?: { text: string; url: string }[],
    replyToMessageId?: number,
  ): void
  (e: 'clear-reply'): void
}>()

const chatStore = useChatStore()
const cannedStore = useCannedResponseStore()
const quickLinkStore = useQuickLinkStore()

const messageText = ref('')
const attachments = ref<{ path: string; name: string; isUploading: boolean }[]>([])
const fileInput = ref<HTMLInputElement | null>(null)
const showCanned = ref(false)
const showQuickLinks = ref(false)
/** Кнопки reply_markup, добавленные из быстрых ссылок (отправляются вместе с текстом). */
const pendingReplyMarkup = ref<{ text: string; url: string }[]>([])

const showDraftHint = computed(() => messageText.value.trim().length > 0)

watch(() => chatStore.selectedChatId, (chatId) => {
  showCanned.value = false
  showQuickLinks.value = false
  pendingReplyMarkup.value = []
  if (chatId) {
    const chat = chatStore.selectedChat
    cannedStore.loadResponses({ source_id: chat?.source_id })
    void quickLinkStore.loadLinks({ source_id: chat?.source_id })
  }
}, { immediate: true })

let typingDebounce: ReturnType<typeof setTimeout> | null = null
watch(messageText, () => {
  const chatId = chatStore.selectedChatId
  if (!chatId || messageText.value.trim().length === 0) {
    return
  }
  if (typingDebounce != null) {
    clearTimeout(typingDebounce)
  }
  typingDebounce = setTimeout(() => {
    typingDebounce = null
    void sendTypingIndicator(chatId).catch(() => {})
  }, 1000)
})

function insertCanned(text: string): void {
  messageText.value = messageText.value ? `${messageText.value}\n${text}` : text
  showCanned.value = false
}

function toggleCanned(): void {
  showQuickLinks.value = false
  showCanned.value = !showCanned.value
}

function toggleQuickLinks(): void {
  showCanned.value = false
  showQuickLinks.value = !showQuickLinks.value
}

function addQuickLinkToPending(link: ApiQuickLink): void {
  pendingReplyMarkup.value = [
    ...pendingReplyMarkup.value,
    { text: link.title.slice(0, 40), url: link.url },
  ]
  showQuickLinks.value = false
}

function removeReplyMarkupChip(index: number): void {
  pendingReplyMarkup.value = pendingReplyMarkup.value.filter((_, i) => i !== index)
}

async function handleFileSelect(event: Event): Promise<void> {
  const target = event.target as HTMLInputElement
  const files = target.files
  if (!files || files.length === 0) return

  for (let i = 0; i < files.length; i++) {
    const file = files[i]!
    attachments.value.push({ path: '', name: file.name, isUploading: true })
    const index = attachments.value.length - 1

    try {
      const result = await uploadFile(file)
      attachments.value[index] = {
        path: result.path,
        name: result.original_name,
        isUploading: false,
      }
    } catch (e) {
      console.error('Failed to upload file:', e)
      attachments.value.splice(index, 1)
    }
  }
  target.value = ''
}

function removeAttachment(index: number): void {
  attachments.value.splice(index, 1)
}

function handleSend(): void {
  if (props.composerLocked) {
    return
  }
  const text = messageText.value.trim()
  const currentAttachments = attachments.value
    .filter((a) => !a.isUploading && a.path)
    .map((a) => a.path)

  const markup = pendingReplyMarkup.value.length > 0 ? [...pendingReplyMarkup.value] : undefined

  if (!text && currentAttachments.length === 0 && !markup?.length) return

  const replyId = props.replyToPreview?.id
  emit('send', text, currentAttachments, markup, replyId)
  emit('clear-reply')
  messageText.value = ''
  attachments.value = []
  pendingReplyMarkup.value = []
}

function clearReplyPreview(): void {
  emit('clear-reply')
}

function handleKeydown(event: KeyboardEvent): void {
  if (event.key === 'Enter' && !event.shiftKey) {
    event.preventDefault()
    handleSend()
  }
}

function triggerFileSelect(): void {
  fileInput.value?.click()
}
</script>

<template>
  <div class="composer">
    <div class="composer-wrapper">
      <div
        v-show="showQuickLinks"
        class="canned-dropdown"
      >
        <div v-if="quickLinkStore.isLoading" class="px-4 py-6 text-center">
          <Loader2 class="mx-auto h-5 w-5 animate-spin" style="color: var(--color-brand)" />
        </div>
        <div v-else-if="quickLinkStore.links.length === 0" class="px-4 py-3 text-[12.5px]" style="color: var(--text-secondary)">
          Нет быстрых ссылок
        </div>
        <template v-else>
          <button
            v-for="l in quickLinkStore.links"
            :key="l.id"
            type="button"
            class="canned-item"
            @click="addQuickLinkToPending(l)"
          >
            <div class="canned-item-title">
              {{ l.title }}
            </div>
            <div class="canned-item-text">
              {{ l.url }}
            </div>
          </button>
        </template>
      </div>

      <div
        v-show="showCanned"
        class="canned-dropdown"
      >
        <div v-if="cannedStore.isLoading" class="px-4 py-6 text-center">
          <Loader2 class="mx-auto h-5 w-5 animate-spin" style="color: var(--color-brand)" />
        </div>
        <div v-else-if="cannedStore.responses.length === 0" class="px-4 py-3 text-[12.5px]" style="color: var(--text-secondary)">
          Нет шаблонов
        </div>
        <template v-else>
          <button
            v-for="r in cannedStore.responses"
            :key="r.id"
            type="button"
            class="canned-item"
            @click="insertCanned(r.text)"
          >
            <div class="canned-item-title">
              {{ r.title }}
            </div>
            <div class="canned-item-text">
              {{ r.text }}
            </div>
          </button>
        </template>
      </div>

      <div class="composer-box">
        <div
          v-if="props.replyToPreview"
          class="flex items-start gap-2 border-b px-3 py-2 text-[12px]"
          style="border-color: var(--border-light); background: var(--bg-thread)"
        >
          <div class="min-w-0 flex-1">
            <div class="text-[10px] font-semibold uppercase tracking-wide opacity-70">
              Ответ на
            </div>
            <div class="line-clamp-2 opacity-90">
              {{ props.replyToPreview.text }}
            </div>
          </div>
          <button
            type="button"
            class="shrink-0 rounded p-1 opacity-70 hover:opacity-100"
            aria-label="Отменить ответ"
            @click="clearReplyPreview"
          >
            <X class="h-3.5 w-3.5" />
          </button>
        </div>
        <div
          v-if="pendingReplyMarkup.length > 0"
          class="flex flex-wrap gap-2 border-b px-3 py-2"
          style="border-color: var(--border-light)"
        >
          <span
            v-for="(chip, i) in pendingReplyMarkup"
            :key="`${chip.url}-${i}`"
            class="inline-flex max-w-full items-center gap-1 rounded-full border px-2.5 py-1 text-[11px] font-medium"
            style="border-color: var(--border-light); background: var(--bg-thread); color: var(--text-secondary)"
          >
            <span class="truncate">{{ chip.text }}</span>
            <button
              type="button"
              class="flex size-5 shrink-0 items-center justify-center rounded-full opacity-70 hover:opacity-100"
              :aria-label="`Удалить кнопку «${chip.text}»`"
              @click="removeReplyMarkupChip(i)"
            >
              <X class="h-3 w-3" />
            </button>
          </span>
        </div>

        <div
          v-if="attachments.length > 0"
          class="flex flex-wrap gap-2 border-b px-3 py-2"
          style="border-color: var(--border-light)"
        >
          <div
            v-for="(attachment, index) in attachments"
            :key="index"
            class="relative flex items-center gap-2 rounded-[var(--radius-sm)] px-2 py-1 pr-7 text-xs"
            style="background: var(--bg-thread); color: var(--text-secondary)"
          >
            <Loader2 v-if="attachment.isUploading" class="h-3 w-3 animate-spin" style="color: var(--color-brand)" />
            <FileIcon v-else class="h-3 w-3" />
            <span class="max-w-[140px] truncate">{{ attachment.name }}</span>
            <button
              type="button"
              class="absolute right-0.5 top-1/2 -translate-y-1/2 rounded p-0.5"
              @click="removeAttachment(index)"
            >
              <X class="h-3 w-3" />
            </button>
          </div>
        </div>

        <div
          v-if="props.composerLocked"
          class="border-b px-3 py-2 text-[12.5px] leading-snug"
          style="border-color: var(--border-light); background: var(--bg-thread); color: var(--text-secondary)"
          role="status"
        >
          {{ props.composerLockHint ?? 'Чат в работе у другого модератора. Заберите чат себе, чтобы ответить.' }}
        </div>
        <textarea
          v-model="messageText"
          class="composer-textarea"
          placeholder="Введите ответ..."
          :disabled="props.composerLocked"
          @keydown="handleKeydown"
        />

        <div class="composer-toolbar">
          <div class="composer-tools">
            <input
              ref="fileInput"
              type="file"
              multiple
              class="hidden"
              @change="handleFileSelect"
            >
            <button
              type="button"
              class="composer-tool"
              title="Прикрепить файл"
              :disabled="props.composerLocked"
              @click="triggerFileSelect"
            >
              <Paperclip class="h-[14px] w-[14px]" />
            </button>
            <button
              type="button"
              class="composer-tool"
              title="Шаблоны ответов"
              :disabled="!chatStore.selectedChatId || props.composerLocked"
              @click="toggleCanned"
            >
              <Zap class="h-[14px] w-[14px]" />
            </button>
            <button
              type="button"
              class="composer-tool"
              title="Быстрые ссылки (кнопки)"
              :disabled="!chatStore.selectedChatId || props.composerLocked"
              @click="toggleQuickLinks"
            >
              <Link2 class="h-[14px] w-[14px]" />
            </button>
            <button
              type="button"
              class="composer-tool"
              title="AI-черновик"
              disabled
            >
              <Sparkles class="h-[14px] w-[14px]" />
            </button>
            <button
              type="button"
              class="composer-tool"
              title="Форматирование"
              disabled
            >
              <Type class="h-[14px] w-[14px]" />
            </button>
          </div>
          <div class="composer-send">
            <div v-show="showDraftHint" class="draft-indicator">
              <Save class="h-3 w-3" />
              Черновик сохранен
            </div>
            <button
              type="button"
              class="btn btn-primary"
              :disabled="props.composerLocked || props.isSending || (!messageText.trim() && attachments.length === 0 && pendingReplyMarkup.length === 0) || attachments.some(a => a.isUploading)"
              @click="handleSend"
            >
              <template v-if="props.isSending">
                <Loader2 class="h-3.5 w-3.5 animate-spin" />
                Отправка...
              </template>
              <template v-else>
                <SendHorizontal class="h-3.5 w-3.5" />
                Отправить
              </template>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
