<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { ChevronLeft, ChevronRight, Download, FileText, X } from 'lucide-vue-next'
import type { MessageMediaItem } from '../../types/chat'

const props = defineProps<{
  items: MessageMediaItem[]
  variant?: 'incoming' | 'outgoing'
}>()

const variant = computed(() => props.variant ?? 'incoming')

const imageItems = computed(() =>
  props.items.filter((f) => typeof f.mime_type === 'string' && f.mime_type.startsWith('image/')),
)
const audioItems = computed(() =>
  props.items.filter((f) => typeof f.mime_type === 'string' && f.mime_type.startsWith('audio/')),
)
const otherItems = computed(() =>
  props.items.filter(
    (f) =>
      typeof f.mime_type === 'string'
      && !f.mime_type.startsWith('image/')
      && !f.mime_type.startsWith('audio/'),
  ),
)

const lightboxOpen = ref(false)
const lightboxIndex = ref(0)

function lockBody(lock: boolean): void {
  document.body.style.overflow = lock ? 'hidden' : ''
}

function openLightbox(index: number): void {
  lightboxIndex.value = index
  lightboxOpen.value = true
  lockBody(true)
}

function closeLightbox(): void {
  lightboxOpen.value = false
  lockBody(false)
}

function prev(): void {
  const n = imageItems.value.length
  if (n <= 0) return
  lightboxIndex.value = (lightboxIndex.value - 1 + n) % n
}

function next(): void {
  const n = imageItems.value.length
  if (n <= 0) return
  lightboxIndex.value = (lightboxIndex.value + 1) % n
}

let touchStartX = 0

function onTouchStart(e: TouchEvent): void {
  touchStartX = e.touches[0]?.clientX ?? 0
}

function onTouchEnd(e: TouchEvent): void {
  const x = e.changedTouches[0]?.clientX ?? touchStartX
  const d = x - touchStartX
  if (d < -48) next()
  if (d > 48) prev()
}

function onKeydown(ev: KeyboardEvent): void {
  if (!lightboxOpen.value) return
  if (ev.key === 'Escape') {
    ev.preventDefault()
    closeLightbox()
  } else if (ev.key === 'ArrowLeft') {
    ev.preventDefault()
    prev()
  } else if (ev.key === 'ArrowRight') {
    ev.preventDefault()
    next()
  }
}

watch(lightboxOpen, (open) => {
  if (open) {
    window.addEventListener('keydown', onKeydown)
  } else {
    window.removeEventListener('keydown', onKeydown)
  }
})

onUnmounted(() => {
  window.removeEventListener('keydown', onKeydown)
  lockBody(false)
})

const currentImage = computed(() => imageItems.value[lightboxIndex.value] ?? null)
</script>

<template>
  <div>
    <div
      v-if="imageItems.length > 0"
      class="grid gap-1"
      :class="[
        imageItems.length === 1 ? 'grid-cols-1' : 'grid-cols-2',
        variant === 'incoming'
          ? 'rounded-[14px] bg-black/[0.04] p-1 dark:bg-white/[0.06]'
          : 'rounded-[14px] bg-white/15 p-1',
      ]"
    >
      <button
        v-for="(img, idx) in imageItems"
        :key="`${img.url}-${idx}`"
        type="button"
        class="relative block overflow-hidden rounded-lg border-0 p-0 text-left"
        @click.stop="openLightbox(idx)"
      >
        <img
          :src="img.url"
          :alt="img.name"
          class="max-h-48 w-full object-cover"
          loading="lazy"
        />
      </button>
    </div>

    <div v-for="(a, idx) in audioItems" :key="`${a.url}-a-${idx}`" class="mt-2">
      <audio :src="a.url" controls class="w-full max-w-full" />
    </div>

    <div
      v-for="(file, idx) in otherItems"
      :key="`${file.url}-f-${idx}`"
      class="mt-2 flex items-center gap-2 rounded-[10px] px-3 py-2 text-xs"
      :class="
        variant === 'incoming'
          ? 'bg-black/[0.05] text-[var(--zinc-600)] dark:bg-white/[0.06] dark:text-[var(--zinc-300)]'
          : 'bg-white/15 text-white'
      "
    >
      <FileText class="size-3.5 shrink-0" aria-hidden="true" />
      <span class="min-w-0 flex-1 truncate">{{ file.name }}</span>
      <span v-if="file.sizeLabel" class="shrink-0 text-[10px] opacity-80">{{ file.sizeLabel }}</span>
      <a :href="file.url" target="_blank" rel="noopener noreferrer" class="shrink-0 p-0.5" @click.stop>
        <Download class="size-3.5" />
      </a>
    </div>

    <Teleport to="body">
      <div
        v-if="lightboxOpen && currentImage"
        class="fixed inset-0 z-[500] flex flex-col bg-black/92"
        role="dialog"
        aria-modal="true"
        aria-label="Просмотр изображения"
        @click.self="closeLightbox"
      >
        <div class="flex shrink-0 items-center justify-between px-3 py-2 text-white/90">
          <span class="truncate text-sm">{{ currentImage.name }}</span>
          <div class="flex items-center gap-1">
            <a
              :href="currentImage.url"
              target="_blank"
              rel="noopener noreferrer"
              class="rounded p-2 hover:bg-white/10"
              @click.stop
            >
              <Download class="h-5 w-5" />
            </a>
            <button type="button" class="rounded p-2 hover:bg-white/10" @click="closeLightbox">
              <X class="h-5 w-5" />
            </button>
          </div>
        </div>
        <div
          class="relative flex min-h-0 flex-1 touch-pan-y items-center justify-center px-2 pb-4"
          @touchstart.passive="onTouchStart"
          @touchend="onTouchEnd"
        >
          <button
            v-if="imageItems.length > 1"
            type="button"
            class="absolute left-1 z-10 rounded-full bg-white/10 p-2 text-white"
            @click.stop="prev"
          >
            <ChevronLeft class="h-8 w-8" />
          </button>
          <img
            :src="currentImage.url"
            :alt="currentImage.name"
            class="max-h-full max-w-full object-contain"
            @click.stop
          />
          <button
            v-if="imageItems.length > 1"
            type="button"
            class="absolute right-1 z-10 rounded-full bg-white/10 p-2 text-white"
            @click.stop="next"
          >
            <ChevronRight class="h-8 w-8" />
          </button>
        </div>
        <p v-if="imageItems.length > 1" class="pb-3 text-center text-xs text-white/60">
          {{ lightboxIndex + 1 }} / {{ imageItems.length }}
        </p>
      </div>
    </Teleport>
  </div>
</template>
