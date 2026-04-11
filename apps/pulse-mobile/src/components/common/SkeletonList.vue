<template>
  <div class="min-h-0 flex-1 overflow-y-auto overscroll-y-contain">
    <div
      v-for="n in 3"
      :key="n"
      class="flex gap-3 border-b border-[var(--color-gray-line)] px-4 py-3.5 dark:border-[var(--zinc-800)]"
    >
      <div class="skeleton-shimmer size-12 shrink-0 rounded-[14px]" />
      <div class="flex min-w-0 flex-1 flex-col gap-2">
        <div class="skeleton-shimmer h-3 w-[60%] rounded-md" />
        <div class="skeleton-shimmer h-3 w-[80%] rounded-md" />
        <div class="skeleton-shimmer h-3 w-[40%] rounded-md" />
      </div>
    </div>
  </div>
</template>
