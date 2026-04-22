<script setup lang="ts">
import { Globe, MessageCircle } from 'lucide-vue-next'
import { siTelegram, siVk } from 'simple-icons'
import type { ChannelSource } from '../../types/chat'

defineProps<{
  channel: ChannelSource
  /** px */
  size?: number
}>()
</script>

<template>
  <svg
    v-if="channel === 'tg'"
    role="img"
    viewBox="0 0 24 24"
    xmlns="http://www.w3.org/2000/svg"
    class="shrink-0"
    :style="{ width: `${size ?? 12}px`, height: `${size ?? 12}px` }"
  >
    <title>Telegram</title>
    <path fill="currentColor" :d="siTelegram.path" />
  </svg>
  <svg
    v-else-if="channel === 'vk'"
    role="img"
    viewBox="0 0 24 24"
    xmlns="http://www.w3.org/2000/svg"
    class="shrink-0"
    :style="{ width: `${size ?? 12}px`, height: `${size ?? 12}px` }"
  >
    <title>VK</title>
    <path fill="currentColor" :d="siVk.path" />
  </svg>
  <MessageCircle
    v-else-if="channel === 'max'"
    class="shrink-0 text-[#6b4f7c]"
    :size="size ?? 12"
    aria-hidden="true"
  />
  <Globe
    v-else
    class="shrink-0 text-[#8b6b9a]"
    :size="size ?? 12"
    aria-hidden="true"
  />
</template>
