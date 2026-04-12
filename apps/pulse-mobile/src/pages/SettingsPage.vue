<script setup lang="ts">
import { storeToRefs } from 'pinia'
import { computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  Bell,
  ChevronRight,
  FileText,
  LogOut,
  Moon,
  Sparkles,
  Sun,
  Volume2,
  Smartphone,
} from 'lucide-vue-next'
import BottomNav from '../components/layout/BottomNav.vue'
import { useAuthStore } from '../stores/authStore'
import { useInboxStore } from '../stores/inboxStore'
import { useSettingsStore } from '../stores/settingsStore'
import { useUiStore } from '../stores/uiStore'

const router = useRouter()
const auth = useAuthStore()
const ui = useUiStore()
const inbox = useInboxStore()
const settings = useSettingsStore()
const { isDark } = storeToRefs(ui)
const { inboxBadge } = storeToRefs(inbox)
const { push, sound, vibration, aiHints, aiSummary, pushSyncing } = storeToRefs(settings)

const themeDesc = computed(() => (isDark.value ? 'Включена' : 'Выключена'))

const displayName = computed(() => auth.user?.name ?? 'Модератор')
const displaySubtitle = computed(() => {
  const roles = auth.user?.roles?.length ? auth.user.roles.join(', ') : 'Поддержка'
  return `${roles}`
})

onMounted(() => {
  inbox.setBottomNav('settings')
  if (auth.isAuthenticated && !auth.user) {
    void auth.fetchMe().catch(() => {})
  }
})

function onThemeRowClick() {
  ui.toggleTheme()
}

function onThemeSwitchClick(e: MouseEvent) {
  e.stopPropagation()
  ui.toggleTheme()
}

async function onLogout() {
  await auth.logout()
  await router.push({ name: 'login' })
}
</script>

<template>
  <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
    <div
      class="flex min-h-14 shrink-0 items-center justify-between border-b border-[var(--color-gray-line)] bg-white px-4 pb-3 pt-[calc(12px+var(--safe-top))] dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-850)]"
    >
      <div class="text-lg font-bold text-[var(--color-dark)] dark:text-[var(--zinc-50)]">Настройки</div>
      <div class="flex gap-2">
        <button
          type="button"
          class="flex size-10 cursor-pointer items-center justify-center rounded-xl border-none bg-[var(--color-brand-50)] text-[var(--color-brand)] transition-all active:scale-95 dark:bg-[var(--zinc-800)] dark:text-[var(--color-brand-200)]"
          aria-label="Переключить тему"
          @click="ui.toggleTheme()"
        >
          <Moon v-if="!isDark" class="size-4" aria-hidden="true" />
          <Sun v-else class="size-4" aria-hidden="true" />
        </button>
      </div>
    </div>

    <div class="min-h-0 flex-1 overflow-y-auto px-4 py-4">
      <div
        class="mb-6 flex items-center gap-3.5 rounded-2xl border border-[var(--color-gray-line)] bg-white p-4 dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)]"
      >
        <div
          class="flex size-[52px] shrink-0 items-center justify-center rounded-2xl bg-[var(--color-brand)] text-xl font-bold text-white"
        >
          АИ
        </div>
        <div class="min-w-0 flex-1">
          <div class="text-base font-semibold text-[var(--color-dark)] dark:text-[var(--zinc-100)]">
            {{ displayName }}
          </div>
          <div class="mt-0.5 text-xs text-[var(--zinc-500)]">{{ displaySubtitle }}</div>
        </div>
        <ChevronRight class="ml-auto size-4 shrink-0 text-[var(--zinc-300)] dark:text-[var(--zinc-600)]" />
      </div>

      <div class="mb-6">
        <div
          class="px-1 pb-2 text-[11px] font-bold uppercase tracking-wide text-[var(--zinc-400)]"
        >
          Уведомления
        </div>
        <div
          class="overflow-hidden rounded-2xl border border-[var(--color-gray-line)] bg-white dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)]"
        >
          <div
            class="flex cursor-pointer items-center gap-3 border-b border-[var(--color-gray-line)] px-4 py-3.5 dark:border-[var(--zinc-700)]"
          >
            <div
              class="flex size-9 shrink-0 items-center justify-center rounded-[10px] bg-[var(--color-brand-50)] text-[var(--color-brand)] dark:bg-[rgba(85,23,94,0.2)] dark:text-[var(--color-brand-200)]"
            >
              <Bell class="size-[15px]" />
            </div>
            <div class="min-w-0 flex-1">
              <div class="text-sm font-medium text-[var(--color-dark)] dark:text-[var(--zinc-100)]">
                Push-уведомления
              </div>
              <div class="mt-px text-xs text-[var(--zinc-400)]">Новые сообщения и назначения</div>
            </div>
            <button
              type="button"
              class="si-toggle"
              :class="{ on: push }"
              aria-label="Push-уведомления"
              :disabled="pushSyncing"
              @click="settings.togglePush()"
            />
          </div>
          <div
            class="flex cursor-pointer items-center gap-3 border-b border-[var(--color-gray-line)] px-4 py-3.5 dark:border-[var(--zinc-700)]"
          >
            <div
              class="flex size-9 shrink-0 items-center justify-center rounded-[10px] bg-[#dbeafe] text-[#2563eb] dark:bg-[rgba(37,99,235,0.15)] dark:text-[#60a5fa]"
            >
              <Volume2 class="size-[15px]" />
            </div>
            <div class="min-w-0 flex-1">
              <div class="text-sm font-medium text-[var(--color-dark)] dark:text-[var(--zinc-100)]">
                Звук уведомлений
              </div>
              <div class="mt-px text-xs text-[var(--zinc-400)]">Звуковой сигнал при новом чате</div>
            </div>
            <button
              type="button"
              class="si-toggle"
              :class="{ on: sound }"
              aria-label="Звук"
              @click="settings.toggleSound()"
            />
          </div>
          <div class="flex cursor-pointer items-center gap-3 px-4 py-3.5">
            <div
              class="flex size-9 shrink-0 items-center justify-center rounded-[10px] bg-[#dcfce7] text-[#16a34a] dark:bg-[rgba(22,163,106,0.15)] dark:text-[#4ade80]"
            >
              <Smartphone class="size-[15px]" />
            </div>
            <div class="min-w-0 flex-1">
              <div class="text-sm font-medium text-[var(--color-dark)] dark:text-[var(--zinc-100)]">
                Вибрация
              </div>
              <div class="mt-px text-xs text-[var(--zinc-400)]">Тактильная обратная связь</div>
            </div>
            <button
              type="button"
              class="si-toggle"
              :class="{ on: vibration }"
              aria-label="Вибрация"
              @click="settings.toggleVibration()"
            />
          </div>
        </div>
      </div>

      <div class="mb-6">
        <div
          class="px-1 pb-2 text-[11px] font-bold uppercase tracking-wide text-[var(--zinc-400)]"
        >
          Внешний вид
        </div>
        <div
          class="overflow-hidden rounded-2xl border border-[var(--color-gray-line)] bg-white dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)]"
        >
          <div
            class="flex cursor-pointer items-center gap-3 px-4 py-3.5"
            role="button"
            tabindex="0"
            @click="onThemeRowClick"
            @keydown.enter.prevent="onThemeRowClick"
          >
            <div
              class="flex size-9 shrink-0 items-center justify-center rounded-[10px] bg-[var(--color-brand-50)] text-[var(--color-brand)] dark:bg-[rgba(85,23,94,0.2)] dark:text-[var(--color-brand-200)]"
            >
              <Moon class="size-[15px]" />
            </div>
            <div class="min-w-0 flex-1">
              <div class="text-sm font-medium text-[var(--color-dark)] dark:text-[var(--zinc-100)]">
                Тёмная тема
              </div>
              <div class="mt-px text-xs text-[var(--zinc-400)]">{{ themeDesc }}</div>
            </div>
            <button
              type="button"
              class="si-toggle"
              :class="{ on: isDark }"
              aria-label="Тёмная тема"
              @click="onThemeSwitchClick"
            />
          </div>
        </div>
      </div>

      <div class="mb-6">
        <div
          class="px-1 pb-2 text-[11px] font-bold uppercase tracking-wide text-[var(--zinc-400)]"
        >
          AI-ассистент
        </div>
        <div
          class="overflow-hidden rounded-2xl border border-[var(--color-gray-line)] bg-white dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)]"
        >
          <div
            class="flex cursor-pointer items-center gap-3 border-b border-[var(--color-gray-line)] px-4 py-3.5 dark:border-[var(--zinc-700)]"
          >
            <div
              class="flex size-9 shrink-0 items-center justify-center rounded-[10px] bg-[#ffedd5] text-[#ea580c] dark:bg-[rgba(234,88,12,0.15)] dark:text-[#fb923c]"
            >
              <Sparkles class="size-[15px]" />
            </div>
            <div class="min-w-0 flex-1">
              <div class="text-sm font-medium text-[var(--color-dark)] dark:text-[var(--zinc-100)]">
                AI-подсказки
              </div>
              <div class="mt-px text-xs text-[var(--zinc-400)]">Автоматические предложения ответов</div>
            </div>
            <button
              type="button"
              class="si-toggle"
              :class="{ on: aiHints }"
              aria-label="AI-подсказки"
              @click="settings.toggleAiHints()"
            />
          </div>
          <div class="flex cursor-pointer items-center gap-3 px-4 py-3.5">
            <div
              class="flex size-9 shrink-0 items-center justify-center rounded-[10px] bg-[#ffedd5] text-[#ea580c] dark:bg-[rgba(234,88,12,0.15)] dark:text-[#fb923c]"
            >
              <FileText class="size-[15px]" />
            </div>
            <div class="min-w-0 flex-1">
              <div class="text-sm font-medium text-[var(--color-dark)] dark:text-[var(--zinc-100)]">
                AI-резюме
              </div>
              <div class="mt-px text-xs text-[var(--zinc-400)]">Краткое описание темы обращения</div>
            </div>
            <button
              type="button"
              class="si-toggle"
              :class="{ on: aiSummary }"
              aria-label="AI-резюме"
              @click="settings.toggleAiSummary()"
            />
          </div>
        </div>
      </div>

      <div class="mb-6">
        <div
          class="px-1 pb-2 text-[11px] font-bold uppercase tracking-wide text-[var(--zinc-400)]"
        >
          Аккаунт
        </div>
        <div
          class="overflow-hidden rounded-2xl border border-[var(--color-gray-line)] bg-white dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)]"
        >
          <button
            type="button"
            class="flex w-full cursor-pointer items-center gap-3 px-4 py-3.5 text-left"
            @click="onLogout"
          >
            <div
              class="flex size-9 shrink-0 items-center justify-center rounded-[10px] bg-[#fee2e2] text-[#dc2626] dark:bg-[rgba(220,38,38,0.15)] dark:text-[#f87171]"
            >
              <LogOut class="size-[15px]" />
            </div>
            <div class="min-w-0 flex-1">
              <div class="text-sm font-medium text-[#dc2626]">Выйти из аккаунта</div>
            </div>
            <ChevronRight class="size-3.5 shrink-0 text-[var(--zinc-300)] dark:text-[var(--zinc-600)]" />
          </button>
        </div>
      </div>

      <div class="pb-10 pt-5 text-center text-[11px] text-[var(--zinc-400)]">Pulse v1.0.0 · АЧПП</div>
    </div>

    <BottomNav :inbox-badge="inboxBadge" />
  </div>
</template>
