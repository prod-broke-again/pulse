<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import {
  MessagesSquare,
  BarChart3,
  Zap,
  Link2,
  Settings,
} from 'lucide-vue-next'
import { useAuthStore } from '../../stores/authStore'

export type SidebarView = 'chats' | 'analytics' | 'templates' | 'quickLinks' | 'settings'

const props = defineProps<{
  activeView: SidebarView
}>()

const emit = defineEmits<{
  (e: 'select', view: SidebarView): void
}>()

const authStore = useAuthStore()

const sidebarAvatarBroken = ref(false)

watch(
  () => authStore.user?.avatar_url,
  () => {
    sidebarAvatarBroken.value = false
  },
)

const userInitials = computed(() => {
  const name = authStore.user?.name?.trim()
  if (!name) return '?'
  const parts = name.split(/\s+/).filter(Boolean)
  if (parts.length >= 2) {
    return (parts[0]![0] + parts[parts.length - 1]![0]).toUpperCase()
  }
  return name.slice(0, 2).toUpperCase()
})

function navClass(view: SidebarView): string {
  const isActive = view === props.activeView
  return [
    'relative flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-[var(--radius-md)] transition',
    isActive ? 'bg-[rgba(154,95,168,0.2)]' : 'hover:bg-white/5',
  ].join(' ')
}

function iconColor(view: SidebarView): string {
  return view === props.activeView ? 'var(--text-sidebar-active)' : 'var(--text-sidebar)'
}
</script>

<template>
  <nav
    class="flex w-[62px] shrink-0 flex-col items-center border-r py-4"
    style="background: var(--bg-sidebar); border-color: rgba(255, 255, 255, 0.04)"
  >
    <div class="flex flex-1 flex-col gap-1">
      <button type="button" :class="navClass('chats')" title="Чаты" @click="emit('select', 'chats')">
        <MessagesSquare class="h-[17px] w-[17px]" :style="{ color: iconColor('chats') }" />
      </button>
      <button type="button" :class="navClass('analytics')" title="Аналитика" @click="emit('select', 'analytics')">
        <BarChart3 class="h-[17px] w-[17px]" :style="{ color: iconColor('analytics') }" />
      </button>
      <button type="button" :class="navClass('templates')" title="Шаблоны" @click="emit('select', 'templates')">
        <Zap class="h-[17px] w-[17px]" :style="{ color: iconColor('templates') }" />
      </button>
      <button type="button" :class="navClass('quickLinks')" title="Быстрые ссылки" @click="emit('select', 'quickLinks')">
        <Link2 class="h-[17px] w-[17px]" :style="{ color: iconColor('quickLinks') }" />
      </button>
      <button type="button" :class="navClass('settings')" title="Настройки" @click="emit('select', 'settings')">
        <Settings class="h-[17px] w-[17px]" :style="{ color: iconColor('settings') }" />
      </button>
    </div>

    <div class="mt-auto flex flex-col items-center">
      <button
        type="button"
        class="relative flex h-[34px] w-[34px] items-center justify-center overflow-hidden rounded-full text-[13px] font-semibold text-white transition hover:opacity-85"
        style="background: var(--color-brand-200)"
        :title="authStore.user?.name ?? 'Профиль'"
        @click="emit('select', 'settings')"
      >
        <img
          v-if="authStore.user?.avatar_url && !sidebarAvatarBroken"
          :src="authStore.user.avatar_url"
          alt=""
          class="absolute inset-0 h-full w-full object-cover"
          @error="sidebarAvatarBroken = true"
        >
        <span
          v-show="!authStore.user?.avatar_url || sidebarAvatarBroken"
          class="relative z-[1]"
        >{{ userInitials }}</span>
      </button>
    </div>
  </nav>
</template>
