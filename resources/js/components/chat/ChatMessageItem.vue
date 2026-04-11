<script setup lang="ts">
import { computed } from 'vue';
import { Loader2, Check, CheckCheck } from 'lucide-vue-next';
import type { ApiMessage } from '@/lib/api';

const props = defineProps<{
    message: ApiMessage;
}>();

const isModerator = computed(() => props.message.sender_type === 'moderator');

const status = computed(() => {
    if (props.message.sender_type !== 'moderator') return null;
    const id = props.message.id;
    const isTemp = typeof id === 'string' && String(id).startsWith('temp-');
    if (isTemp) return 'sending';
    if (props.message.is_read) return 'read';
    return 'sent';
});
</script>

<template>
    <div
        :class="[
            'max-w-[75%] rounded-2xl px-4 py-2 text-sm',
            isModerator
                ? 'ml-auto rounded-br-md bg-primary text-primary-foreground'
                : 'rounded-bl-md bg-muted',
        ]"
    >
        <p class="whitespace-pre-wrap break-words">{{ message.text }}</p>
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
