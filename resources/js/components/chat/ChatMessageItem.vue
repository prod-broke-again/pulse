<script setup lang="ts">
import { computed } from 'vue';
import { Loader2, Check, CheckCheck, Reply } from 'lucide-vue-next';
import type { ApiMessage } from '@/lib/api';
import MessageAttachmentsGallery from '@/components/chat/MessageAttachmentsGallery.vue';

const props = defineProps<{
    message: ApiMessage;
}>();

const emit = defineEmits<{
    reply: [messageId: number];
}>();

const isModerator = computed(() => props.message.sender_type === 'moderator');
const isSystem = computed(() => props.message.sender_type === 'system');
const attachments = computed(() => {
    const list = props.message.attachments ?? [];
    const unique: typeof list = [];
    const seen = new Set<string>();

    for (const att of list) {
        const key = `${att.url ?? ''}|${String(att.id ?? '')}|${att.name ?? ''}`;
        if (seen.has(key)) continue;
        seen.add(key);
        unique.push(att);
    }

    return unique;
});

const galleryFiles = computed(() => {
    const mid = props.message.id;
    const base =
        typeof mid === 'number' && Number.isFinite(mid) ? mid : Number(String(mid).replace(/\D/g, '')) || 0;
    return attachments.value.map((att, idx) => ({
        id: typeof att.id === 'number' ? att.id : base * 1000 + idx + 1,
        url: att.url ?? '',
        name: att.name ?? 'file',
        mime_type: att.mime_type ?? 'application/octet-stream',
        size: typeof att.size === 'number' ? att.size : 0,
    }));
});

const status = computed(() => {
    if (props.message.sender_type !== 'moderator') return null;
    const id = props.message.id;
    const isTemp = typeof id === 'string' && String(id).startsWith('temp-');
    if (isTemp) return 'sending';
    if (props.message.is_read) return 'read';
    return 'sent';
});

const canReply = computed(() => {
    if (isSystem.value) return false;
    const id = props.message.id;
    const n = typeof id === 'number' ? id : Number(id);
    return Number.isFinite(n) && n > 0;
});

function onReplyClick() {
    const id = props.message.id;
    const n = typeof id === 'number' ? id : Number(id);
    if (Number.isFinite(n) && n > 0) emit('reply', n);
}
</script>

<template>
    <div
        v-if="isSystem"
        class="max-w-[90%] self-center rounded-lg px-3 py-1.5 text-center text-xs text-muted-foreground"
    >
        <p class="whitespace-pre-wrap break-words">{{ message.text }}</p>
    </div>
    <div
        v-else
        :class="[
            'group relative max-w-[75%] rounded-2xl px-4 py-2 text-sm',
            isModerator
                ? 'ml-auto rounded-br-md bg-primary text-primary-foreground'
                : 'rounded-bl-md bg-muted',
        ]"
    >
        <button
            v-if="canReply"
            type="button"
            class="absolute -right-1 -top-2 flex size-7 items-center justify-center rounded-full border border-border bg-background text-muted-foreground opacity-100 shadow-sm transition-opacity hover:bg-muted sm:opacity-0 sm:group-hover:opacity-100"
            :title="'Reply'"
            @click.stop="onReplyClick"
        >
            <Reply class="size-3.5" aria-hidden="true" />
            <span class="sr-only">Reply</span>
        </button>
        <p
            v-if="message.reply_to"
            class="mb-2 border-l-2 border-primary/50 pl-2 text-[11px] opacity-90"
        >
            {{ message.reply_to.text }}
        </p>
        <p class="whitespace-pre-wrap break-words">{{ message.text }}</p>
        <MessageAttachmentsGallery
            v-if="attachments.length"
            :files="galleryFiles"
            :variant="isModerator ? 'moderator' : 'default'"
        />
        <div class="mt-1 flex items-center justify-end gap-1">
            <span class="text-xs opacity-80">
                {{ new Date(message.created_at).toLocaleString() }}
            </span>
            <template v-if="status === 'sending'">
                <Loader2 class="size-3.5 shrink-0 animate-spin opacity-80" />
            </template>
            <template v-else-if="status === 'sent'">
                <Check class="size-3.5 shrink-0 opacity-80" />
            </template>
            <template v-else-if="status === 'read'">
                <CheckCheck class="size-3.5 shrink-0 opacity-80" />
            </template>
        </div>
    </div>
</template>
