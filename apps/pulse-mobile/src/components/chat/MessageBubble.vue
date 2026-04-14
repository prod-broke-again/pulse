<script setup lang="ts">
import { computed } from 'vue'
import { Bot, Check, Reply } from 'lucide-vue-next'
import type { ChatMessage } from '../../types/chat'
import MessageMediaGallery from './MessageMediaGallery.vue'
import ReplyMarkupInline from './ReplyMarkupInline.vue'

const props = defineProps<{
  message: ChatMessage
  highlighted?: boolean
}>()

const emit = defineEmits<{
  reply: [messageId: string]
  jumpReply: [messageId: number]
}>()

const canReply = computed(() => {
  if (props.message.kind === 'system') {
    return false
  }
  const id = props.message.id
  if (typeof id === 'string' && id.startsWith('temp-')) {
    return false
  }
  const n = Number(id)
  return Number.isFinite(n) && n > 0
})

function onReplyClick() {
  emit('reply', props.message.id)
}

function replyQuoteTargetId(): number | null {
  const id = props.message.reply_to?.id
  if (id == null || id <= 0) {
    return null
  }
  return id
}

function onReplyQuoteClick() {
  const id = replyQuoteTargetId()
  if (id != null) {
    emit('jumpReply', id)
  }
}

const hasText = computed(() => !!(props.message.text ?? '').trim())
const hasMedia = computed(() => (props.message.mediaAttachments?.length ?? 0) > 0)
</script>

<template>
  <div
    class="flex max-w-[82%] flex-col"
    :data-message-id="message.kind !== 'system' ? message.id : undefined"
    :class="{
      'self-start': message.kind === 'incoming',
      'self-end': message.kind === 'outgoing',
      'max-w-[90%] self-center': message.kind === 'system',
      'msg-jump-highlight': highlighted && message.kind !== 'system',
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
      <button
        v-if="message.reply_to && replyQuoteTargetId() != null"
        type="button"
        class="mb-1 max-w-full border-l-2 border-[var(--color-brand)]/50 pl-2 text-left text-[11px] leading-snug text-[var(--zinc-500)] dark:text-[var(--zinc-400)]"
        @click="onReplyQuoteClick"
      >
        {{ message.reply_to.text }}
      </button>
      <p
        v-else-if="message.reply_to"
        class="mb-1 max-w-full border-l-2 border-[var(--color-brand)]/50 pl-2 text-[11px] leading-snug text-[var(--zinc-500)] dark:text-[var(--zinc-400)]"
      >
        {{ message.reply_to.text }}
      </p>
      <div
        v-if="hasText"
        class="rounded-[18px] rounded-bl-md bg-white px-3.5 py-2.5 text-sm leading-[1.55] text-[var(--color-dark)] shadow-[0_1px_2px_rgba(0,0,0,0.04)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-100)]"
      >
        {{ message.text }}
      </div>
      <MessageMediaGallery
        v-if="hasMedia"
        class="mt-1.5"
        :items="message.mediaAttachments ?? []"
        variant="incoming"
      />
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
        <button
          v-if="message.reply_to && replyQuoteTargetId() != null"
          type="button"
          class="mb-2 w-full border-l-2 border-white/50 pl-2 text-left text-[11px] text-white/90"
          @click="onReplyQuoteClick"
        >
          {{ message.reply_to.text }}
        </button>
        <p
          v-else-if="message.reply_to"
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

<style scoped>
.msg-jump-highlight {
  animation: msg-jump-fade 1.5s ease-out forwards;
}

@keyframes msg-jump-fade {
  0% {
    box-shadow: 0 0 0 3px rgba(154, 95, 168, 0.45);
    border-radius: 18px;
  }
  100% {
    box-shadow: 0 0 0 0 transparent;
  }
}
</style>
