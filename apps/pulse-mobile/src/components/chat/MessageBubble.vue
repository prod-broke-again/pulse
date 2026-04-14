<script setup lang="ts">
import { computed } from 'vue'
import { Bot, Check, FileText, Reply } from 'lucide-vue-next'
import type { ChatMessage } from '../../types/chat'
import ReplyMarkupInline from './ReplyMarkupInline.vue'

const props = defineProps<{
  message: ChatMessage
}>()

const emit = defineEmits<{
  reply: [messageId: string]
}>()

const canReply = computed(() => {
  if (props.message.kind === 'system') return false
  const id = props.message.id
  if (typeof id === 'string' && id.startsWith('temp-')) return false
  const n = Number(id)
  return Number.isFinite(n) && n > 0
})

function onReplyClick() {
  emit('reply', props.message.id)
}
</script>

<template>
  <div
    class="flex max-w-[82%] flex-col"
    :class="{
      'self-start': message.kind === 'incoming',
      'self-end': message.kind === 'outgoing',
      'max-w-[90%] self-center': message.kind === 'system',
    }"
  >
    <div
      v-if="message.kind === 'system'"
      class="rounded-[18px] bg-transparent px-3 py-1.5 text-center text-xs text-[var(--zinc-400)]"
    >
      <Bot class="mb-0.5 mr-1 inline size-3.5 align-middle" aria-hidden="true" />
      {{ message.text }}
    </div>
    <template v-else-if="message.kind === 'incoming'">
      <button
        v-if="canReply"
        type="button"
        class="mb-1 flex items-center gap-0.5 self-start text-[10px] font-medium text-[var(--color-brand)] dark:text-[var(--color-brand-200)]"
        @click="onReplyClick"
      >
        <Reply class="size-3 shrink-0" aria-hidden="true" />
        Ответить
      </button>
      <template v-if="message.attachment && !message.text">
        <p
          v-if="message.reply_to"
          class="mb-1 max-w-full border-l-2 border-[var(--color-brand)]/50 pl-2 text-[11px] leading-snug text-[var(--zinc-500)] dark:text-[var(--zinc-400)]"
        >
          {{ message.reply_to.text }}
        </p>
        <div
          class="flex items-center gap-2 rounded-[10px] bg-black/[0.05] px-3 py-2 text-xs text-[var(--zinc-600)] dark:bg-white/[0.06] dark:text-[var(--zinc-300)]"
        >
          <FileText class="size-3.5 shrink-0 text-[var(--color-brand)]" aria-hidden="true" />
          <span>{{ message.attachment.fileName }}</span>
          <span class="ml-auto text-[10px] text-[var(--zinc-400)]">{{
            message.attachment.sizeLabel
          }}</span>
        </div>
        <ReplyMarkupInline
          v-if="message.reply_markup && message.reply_markup.length > 0"
          :buttons="message.reply_markup"
        />
        <span
          v-if="message.isRead"
          class="mt-0.5 flex items-center gap-1 px-1 text-[10px] text-[var(--zinc-400)]"
        >
          <Check class="size-3 shrink-0 text-[var(--color-brand)]" aria-label="Прочитано" />
        </span>
      </template>
      <template v-else>
        <div
          v-if="message.text || message.reply_to"
          class="rounded-[18px] rounded-bl-md bg-white px-3.5 py-2.5 text-sm leading-[1.55] text-[var(--color-dark)] shadow-[0_1px_2px_rgba(0,0,0,0.04)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-100)]"
        >
          <p
            v-if="message.reply_to"
            class="mb-2 border-l-2 border-[var(--color-brand)]/60 pl-2 text-[11px] text-[var(--zinc-500)] dark:text-[var(--zinc-400)]"
          >
            {{ message.reply_to.text }}
          </p>
          <template v-if="message.text">{{ message.text }}</template>
        </div>
        <ReplyMarkupInline
          v-if="message.reply_markup && message.reply_markup.length > 0"
          :buttons="message.reply_markup"
        />
        <span
          v-if="message.time || message.isRead"
          class="mt-0.5 flex items-center gap-1 px-1 text-[10px] text-[var(--zinc-400)]"
        >
          <span v-if="message.time">{{ message.time }}</span>
          <Check
            v-if="message.isRead"
            class="size-3 shrink-0 text-[var(--color-brand)]"
            aria-label="Прочитано"
          />
        </span>
      </template>
    </template>
    <template v-else>
      <button
        v-if="canReply"
        type="button"
        class="mb-1 flex items-center gap-0.5 self-end text-[10px] font-medium text-[var(--color-brand)] dark:text-[var(--color-brand-200)]"
        @click="onReplyClick"
      >
        <Reply class="size-3 shrink-0" aria-hidden="true" />
        Ответить
      </button>
      <div
        class="rounded-[18px] rounded-br-md bg-[var(--color-brand)] px-3.5 py-2.5 text-sm leading-[1.55] text-white"
      >
        <p
          v-if="message.reply_to"
          class="mb-2 border-l-2 border-white/50 pl-2 text-[11px] text-white/90"
        >
          {{ message.reply_to.text }}
        </p>
        {{ message.text }}
      </div>
      <ReplyMarkupInline
        v-if="message.reply_markup && message.reply_markup.length > 0"
        :buttons="message.reply_markup"
      />
      <span
        v-if="message.time"
        class="mt-0.5 self-end px-1 text-right text-[10px] text-[var(--zinc-400)]"
      >
        {{ message.time }}
      </span>
    </template>
  </div>
</template>
