<script setup lang="ts">
import { computed, onUnmounted, ref, watch } from 'vue'
import { ChevronLeft, ChevronRight, Download, FileIcon, X } from 'lucide-vue-next'

export type GalleryFile = {
  id: number
  url: string
  name: string
  mime_type: string
  size: number
}

export type PendingSlot = {
  type: string
}

const props = withDefaults(
  defineProps<{
    files: GalleryFile[]
    /** Placeholders while inbound files are downloading. */
    pendingSlots?: PendingSlot[]
  }>(),
  { pendingSlots: () => [] },
)

const imageFiles = computed(() =>
  props.files.filter((f) => typeof f.mime_type === 'string' && f.mime_type.startsWith('image/')),
)
const audioFiles = computed(() =>
  props.files.filter((f) => typeof f.mime_type === 'string' && f.mime_type.startsWith('audio/')),
)
const otherFiles = computed(() =>
  props.files.filter(
    (f) =>
      typeof f.mime_type === 'string'
      && !f.mime_type.startsWith('image/')
      && !f.mime_type.startsWith('audio/'),
  ),
)

const pendingImageSlots = computed(() => props.pendingSlots.filter((s) => s.type === 'image'))
const pendingVideoSlots = computed(() => props.pendingSlots.filter((s) => s.type === 'video'))
const pendingAudioSlots = computed(() => props.pendingSlots.filter((s) => s.type === 'audio'))
const pendingFileSlots = computed(() => props.pendingSlots.filter((s) => s.type === 'file'))

const imageGridCellCount = computed(() => imageFiles.value.length + pendingImageSlots.value.length)

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
  const n = imageFiles.value.length
  if (n <= 0) return
  lightboxIndex.value = (lightboxIndex.value - 1 + n) % n
}

function next(): void {
  const n = imageFiles.value.length
  if (n <= 0) return
  lightboxIndex.value = (lightboxIndex.value + 1) % n
}

function onKeydown(e: KeyboardEvent): void {
  if (!lightboxOpen.value) return
  if (e.key === 'Escape') {
    e.preventDefault()
    closeLightbox()
  } else if (e.key === 'ArrowLeft') {
    e.preventDefault()
    prev()
  } else if (e.key === 'ArrowRight') {
    e.preventDefault()
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

function formatSize(bytes: number): string {
  if (bytes === 0) return '0 B'
  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))
  return `${parseFloat((bytes / k ** i).toFixed(2))} ${sizes[i]}`
}

const currentImage = computed(() => imageFiles.value[lightboxIndex.value] ?? null)
</script>

<template>
  <div>
    <div
      v-if="imageGridCellCount > 0"
      class="mt-1 grid gap-1"
      :class="imageGridCellCount === 1 ? 'grid-cols-1' : 'grid-cols-2'"
    >
      <button
        v-for="(img, idx) in imageFiles"
        :key="`f-${img.url}-${idx}`"
        type="button"
        class="relative block overflow-hidden rounded-[var(--radius-sm)] border-0 bg-black/5 p-0 text-left outline-none ring-offset-2 focus-visible:ring-2 focus-visible:ring-[var(--color-brand)]"
        @click.stop="openLightbox(idx)"
      >
        <Transition name="fade-attach" appear>
          <img
            :src="img.url"
            :alt="img.name"
            class="max-h-52 w-full object-cover"
            loading="lazy"
            decoding="async"
            fetchpriority="low"
          />
        </Transition>
      </button>
      <div
        v-for="(s, i) in pendingImageSlots"
        :key="`p-img-${i}-${s.type}`"
        class="overflow-hidden rounded-[var(--radius-sm)] bg-black/[0.06] dark:bg-white/[0.06]"
        aria-hidden="true"
      >
        <Transition name="fade-attach" appear>
          <div
            class="min-h-[120px] w-full max-h-52 animate-pulse bg-gradient-to-br from-zinc-300/70 to-zinc-400/40 dark:from-zinc-600/50 dark:to-zinc-700/30"
          />
        </Transition>
      </div>
    </div>

    <div
      v-for="(_s, i) in pendingVideoSlots"
      :key="`p-vid-${i}`"
      class="mt-2 overflow-hidden rounded-[var(--radius-sm)]"
      aria-hidden="true"
    >
      <Transition name="fade-attach" appear>
        <div
          class="aspect-video w-full max-w-sm animate-pulse bg-gradient-to-br from-zinc-300/70 to-zinc-400/40 dark:from-zinc-600/50 dark:to-zinc-700/30"
        />
      </Transition>
    </div>

    <audio
      v-for="a in audioFiles"
      :key="a.id"
      :src="a.url"
      controls
      class="mt-2 max-w-full"
    />

    <div v-for="(_s, i) in pendingAudioSlots" :key="`p-aud-${i}`" class="mt-2" aria-hidden="true">
      <Transition name="fade-attach" appear>
        <div class="h-10 w-full max-w-md animate-pulse rounded-md bg-zinc-300/60 dark:bg-zinc-600/40" />
      </Transition>
    </div>

    <div v-for="file in otherFiles" :key="file.id" class="msg-attachment mt-2">
      <FileIcon class="h-3.5 w-3.5 shrink-0" />
      <span class="min-w-0 truncate">{{ file.name }}</span>
      <span class="shrink-0 text-[10px] opacity-80">{{ formatSize(file.size) }}</span>
      <a :href="file.url" target="_blank" class="ml-auto shrink-0 p-0.5" @click.stop>
        <Download class="h-3.5 w-3.5" />
      </a>
    </div>

    <div
      v-for="(_s, i) in pendingFileSlots"
      :key="`p-file-${i}`"
      class="msg-attachment mt-2 opacity-90"
      aria-hidden="true"
    >
      <Transition name="fade-attach" appear>
        <div class="flex w-full min-w-0 items-center gap-2">
          <div class="h-3.5 w-3.5 shrink-0 animate-pulse rounded-sm bg-zinc-300/80 dark:bg-zinc-600/60" />
          <div class="h-3 min-w-0 flex-1 animate-pulse rounded bg-zinc-300/60 dark:bg-zinc-600/40" />
          <div class="h-3 w-10 shrink-0 animate-pulse rounded bg-zinc-300/50 dark:bg-zinc-600/35" />
        </div>
      </Transition>
    </div>

    <Teleport to="body">
      <div
        v-if="lightboxOpen && currentImage"
        class="fixed inset-0 z-[200] flex flex-col bg-black/90"
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
              title="Скачать"
              @click.stop
            >
              <Download class="h-5 w-5" />
            </a>
            <button
              type="button"
              class="rounded p-2 hover:bg-white/10"
              title="Закрыть"
              @click="closeLightbox"
            >
              <X class="h-5 w-5" />
            </button>
          </div>
        </div>
        <div class="relative flex min-h-0 flex-1 items-center justify-center px-2 pb-4">
          <button
            v-if="imageFiles.length > 1"
            type="button"
            class="absolute left-1 z-10 rounded-full bg-white/10 p-2 text-white hover:bg-white/20"
            title="Назад"
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
            v-if="imageFiles.length > 1"
            type="button"
            class="absolute right-1 z-10 rounded-full bg-white/10 p-2 text-white hover:bg-white/20"
            title="Вперёд"
            @click.stop="next"
          >
            <ChevronRight class="h-8 w-8" />
          </button>
        </div>
        <p v-if="imageFiles.length > 1" class="pb-3 text-center text-xs text-white/60">
          {{ lightboxIndex + 1 }} / {{ imageFiles.length }}
        </p>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
.fade-attach-enter-active,
.fade-attach-leave-active {
  transition: opacity 0.28s ease;
}
.fade-attach-enter-from,
.fade-attach-leave-to {
  opacity: 0;
}
</style>
