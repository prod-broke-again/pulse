<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { BookOpen, X } from 'lucide-vue-next'

const props = withDefaults(
  defineProps<{
    /** Уникальный ключ для localStorage (например pulse.modGuide.cannedResponses) */
    storageKey: string
    title: string
    purpose: string
    tips: string[]
    examples: string[]
    dismissLabel?: string
    showAgainLabel?: string
  }>(),
  {
    dismissLabel: 'Закрыть',
    showAgainLabel: 'Показать инструкцию',
  },
)

const STORAGE_PREFIX = 'pulse.modGuide.hidden.'

function readHidden(): boolean {
  if (typeof localStorage === 'undefined') {
    return false
  }
  try {
    return localStorage.getItem(STORAGE_PREFIX + props.storageKey) === '1'
  } catch {
    return false
  }
}

function writeHidden(value: boolean): void {
  if (typeof localStorage === 'undefined') {
    return
  }
  try {
    const key = STORAGE_PREFIX + props.storageKey
    if (value) {
      localStorage.setItem(key, '1')
    } else {
      localStorage.removeItem(key)
    }
  } catch {
    // ignore quota / private mode
  }
}

const isHidden = ref(false)

onMounted(() => {
  isHidden.value = readHidden()
})

function dismiss() {
  isHidden.value = true
  writeHidden(true)
}

function showAgain() {
  isHidden.value = false
  writeHidden(false)
}
</script>

<template>
  <div class="mb-6">
    <div
      v-if="!isHidden"
      class="moderator-guide-card rounded-[var(--radius-lg)] border p-5 shadow-sm"
      style="border-color: var(--border-light); background: var(--bg-inbox)"
      role="region"
      :aria-label="title"
    >
      <div class="mb-4 flex items-start justify-between gap-3">
        <div class="flex min-w-0 items-start gap-3">
          <div
            class="moderator-guide-card__icon flex h-10 w-10 shrink-0 items-center justify-center rounded-[var(--radius-md)]"
          >
            <BookOpen class="h-5 w-5" aria-hidden="true" />
          </div>
          <div class="min-w-0">
            <h3 class="text-base font-bold" style="color: var(--text-primary)">
              {{ title }}
            </h3>
            <p class="mt-1 text-sm leading-relaxed" style="color: var(--text-secondary)">
              {{ purpose }}
            </p>
          </div>
        </div>
        <button
          type="button"
          class="no-drag-region shrink-0 rounded-[var(--radius-md)] border p-2 transition hover:bg-white/5"
          style="border-color: var(--border-light); color: var(--text-secondary)"
          :title="dismissLabel"
          :aria-label="dismissLabel"
          @click="dismiss"
        >
          <X class="h-4 w-4" />
        </button>
      </div>

      <div class="mb-4">
        <p
          class="mb-2 text-xs font-bold uppercase tracking-wide"
          style="color: var(--text-muted)"
        >
          Как пользоваться
        </p>
        <ul class="list-inside list-disc space-y-1.5 text-sm" style="color: var(--text-primary)">
          <li v-for="(tip, i) in tips" :key="i">
            {{ tip }}
          </li>
        </ul>
      </div>

      <div>
        <p
          class="mb-2 text-xs font-bold uppercase tracking-wide"
          style="color: var(--text-muted)"
        >
          Примеры
        </p>
        <ul class="space-y-2">
          <li
            v-for="(ex, i) in examples"
            :key="i"
            class="rounded-[var(--radius-md)] border px-3 py-2 text-sm"
            style="border-color: var(--border-light); background: var(--bg-thread); color: var(--text-secondary)"
          >
            {{ ex }}
          </li>
        </ul>
      </div>

      <div class="mt-4 flex justify-end border-t pt-4" style="border-color: var(--border-light)">
        <button
          type="button"
          class="rounded-[var(--radius-md)] border px-4 py-2 text-sm font-semibold transition hover:bg-white/5"
          style="border-color: var(--border-light); color: var(--text-primary)"
          @click="dismiss"
        >
          {{ dismissLabel }}
        </button>
      </div>
    </div>

    <button
      v-else
      type="button"
      class="inline-flex items-center gap-1.5 rounded-[var(--radius-md)] border px-3 py-1.5 text-xs font-semibold transition hover:bg-white/5"
      style="border-color: var(--border-light); color: var(--color-brand)"
      @click="showAgain"
    >
      <BookOpen class="h-3.5 w-3.5" aria-hidden="true" />
      {{ showAgainLabel }}
    </button>
  </div>
</template>
