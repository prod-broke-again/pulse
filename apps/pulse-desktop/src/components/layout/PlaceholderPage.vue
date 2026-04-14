<script setup lang="ts">
defineProps<{
  title: string
  description: string
}>()
</script>

<template>
  <section
    class="custom-scroll flex min-h-0 flex-1 flex-col overflow-y-auto p-8"
    style="background: var(--bg-thread)"
  >
    <h2 class="text-lg font-bold" style="color: var(--text-primary)">
      {{ title }}
    </h2>
    <p class="mt-2 max-w-xl text-sm" style="color: var(--text-secondary)">
      {{ description }}
    </p>
    <div
      class="mt-8 flex min-h-[200px] flex-1 items-center justify-center rounded-[var(--radius-lg)] border border-dashed p-8 text-center text-sm"
      style="border-color: var(--border-medium); color: var(--text-muted)"
    >
      Раздел в разработке
    </div>
  </section>
</template>
