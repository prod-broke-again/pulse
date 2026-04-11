<script setup lang="ts">
import { Bot, Check, FileText } from 'lucide-vue-next'
import type { ChatMessage } from '../../types/chat'
import ReplyMarkupInline from './ReplyMarkupInline.vue'

defineProps<{
  message: ChatMessage
}>()
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
      <template v-if="message.attachment && !message.text">
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
          v-if="message.text"
          class="rounded-[18px] rounded-bl-md bg-white px-3.5 py-2.5 text-sm leading-[1.55] text-[var(--color-dark)] shadow-[0_1px_2px_rgba(0,0,0,0.04)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-100)]"
        >
          {{ message.text }}
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
      <div
        class="rounded-[18px] rounded-br-md bg-[var(--color-brand)] px-3.5 py-2.5 text-sm leading-[1.55] text-white"
      >
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
