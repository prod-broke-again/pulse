<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import { Camera, LogOut, RefreshCw } from 'lucide-vue-next'
import { useAuthStore } from '../../stores/authStore'
import { uploadAvatar } from '../../api/auth'
import { redirectToIdP } from '../../lib/redirectToIdP'
import { achppIdBaseUrl, achppOAuthClientId, resolveOAuthRedirectUri } from '../../lib/oauthConfig'

defineProps<{
  isDark: boolean
  soundEnabled: boolean
}>()

const emit = defineEmits<{
  (e: 'toggle-theme'): void
  (e: 'toggle-sound'): void
}>()

const authStore = useAuthStore()
const user = computed(() => authStore.user)

const initials = computed(() => {
  if (!user.value?.name) return '??'
  return user.value.name
    .split(' ')
    .map((n) => n[0]!.toUpperCase())
    .slice(0, 2)
    .join('')
})

const roleName = computed(() => {
  if (!user.value?.roles) return 'Пользователь'
  if (user.value.roles.includes('admin')) return 'Администратор'
  if (user.value.roles.includes('moderator')) return 'Модератор'
  return 'Пользователь'
})

const avatarInput = ref<HTMLInputElement | null>(null)
const avatarBroken = ref(false)
const avatarBusy = ref(false)
const idSyncBusy = ref(false)
const avatarError = ref<string | null>(null)
const idSyncError = ref<string | null>(null)

const pkceConfigured = computed(() =>
  Boolean(achppIdBaseUrl() && achppOAuthClientId() && resolveOAuthRedirectUri()),
)

watch(
  () => user.value?.avatar_url,
  () => {
    avatarBroken.value = false
  },
)

function triggerAvatarPick(): void {
  avatarError.value = null
  avatarInput.value?.click()
}

async function onAvatarFileChange(event: Event): Promise<void> {
  const target = event.target as HTMLInputElement
  const files = target.files
  if (!files?.length) {
    return
  }
  const file = files[0]!
  avatarBusy.value = true
  avatarError.value = null
  try {
    const updated = await uploadAvatar(file)
    authStore.applyUserProfile(updated)
  } catch (e: unknown) {
    const msg = e instanceof Error ? e.message : 'Не удалось загрузить аватар.'
    avatarError.value = msg
  } finally {
    avatarBusy.value = false
    target.value = ''
  }
}

async function startSyncFromId(): Promise<void> {
  idSyncError.value = null
  if (!pkceConfigured.value) {
    idSyncError.value =
      'Задайте VITE_ACHPP_ID_BASE_URL и VITE_ACHPP_ID_CLIENT_ID (redirect: pulse-desktop://auth/callback).'
    return
  }
  idSyncBusy.value = true
  try {
    const result = await redirectToIdP()
    if (!result.ok) {
      idSyncError.value = result.error
    }
  } finally {
    idSyncBusy.value = false
  }
}
</script>

<template>
  <section class="custom-scroll flex min-h-0 flex-1 flex-col overflow-y-auto p-10" style="background: var(--bg-thread)">
    <div class="mb-6">
      <h2 class="text-2xl font-bold" style="color: var(--text-primary)">
        Настройки
      </h2>
      <p class="mt-1 text-sm" style="color: var(--text-secondary)">
        Профиль и поведение интерфейса
      </p>
    </div>

    <div v-if="user" class="mb-10 flex flex-wrap items-center gap-6">
      <div class="relative">
        <div
          class="relative flex h-24 w-24 items-center justify-center overflow-hidden rounded-[32px] text-3xl font-bold text-white shadow-lg"
          style="background: linear-gradient(135deg, var(--color-brand) 0%, var(--color-brand-200) 100%)"
        >
          <img
            v-if="user.avatar_url && !avatarBroken"
            :src="user.avatar_url"
            alt=""
            class="h-full w-full object-cover"
            @error="avatarBroken = true"
          >
          <span v-if="!user.avatar_url || avatarBroken">{{ initials }}</span>
        </div>
        <input
          ref="avatarInput"
          type="file"
          accept="image/jpeg,image/png,image/gif,image/webp"
          class="hidden"
          :disabled="avatarBusy"
          @change="onAvatarFileChange"
        >
        <button
          type="button"
          class="absolute -bottom-2 -right-2 flex h-8 w-8 items-center justify-center rounded-full shadow transition hover:scale-110 disabled:opacity-50"
          style="background: var(--bg-inbox); color: var(--text-primary)"
          :disabled="avatarBusy"
          :title="avatarBusy ? 'Загрузка…' : 'Сменить фото'"
          @click="triggerAvatarPick"
        >
          <Camera class="h-4 w-4" />
        </button>
      </div>
      <div class="min-w-0 flex-1">
        <h3 class="text-xl font-bold" style="color: var(--text-primary)">
          {{ user.name }}
        </h3>
        <p class="text-sm font-medium" style="color: var(--text-secondary)">
          {{ roleName }} • <span style="color: var(--status-open)">Online</span>
        </p>
        <div class="mt-3 flex flex-wrap items-center gap-2">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-[var(--radius-md)] border px-3 py-1.5 text-xs font-semibold transition hover:opacity-90 disabled:opacity-50"
            style="border-color: var(--border-light); color: var(--text-primary); background: var(--bg-inbox)"
            :disabled="idSyncBusy"
            @click="startSyncFromId"
          >
            <RefreshCw class="h-3.5 w-3.5 shrink-0" :class="{ 'animate-spin': idSyncBusy }" />
            Обновить из АЧПП ID
          </button>
        </div>
        <p v-if="avatarError" class="mt-2 text-xs font-medium text-red-500" role="alert">
          {{ avatarError }}
        </p>
        <p v-if="idSyncError" class="mt-2 text-xs font-medium text-red-500" role="alert">
          {{ idSyncError }}
        </p>
        <p v-if="!pkceConfigured" class="mt-2 max-w-md text-xs" style="color: var(--text-muted)">
          Для синхронизации с ID задайте переменные окружения клиента (см. экран входа).
        </p>
      </div>
    </div>

    <div v-if="user" class="max-w-2xl space-y-8">
      <div>
        <label class="mb-2 ml-1 block text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary)">Полное имя</label>
        <input
          type="text"
          :value="user.name"
          readonly
          class="h-12 w-full rounded-[var(--radius-md)] border px-4 font-medium outline-none"
          style="border-color: var(--border-light); background: var(--bg-inbox); color: var(--text-primary)"
        >
      </div>

      <div>
        <label class="mb-2 ml-1 block text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary)">Email (служебный)</label>
        <input
          type="email"
          :value="user.email"
          readonly
          class="h-12 w-full cursor-not-allowed rounded-[var(--radius-md)] border border-transparent px-4 font-medium"
          style="background: var(--bg-inbox); color: var(--text-secondary)"
        >
      </div>

      <div class="my-8 h-px" style="background: var(--border-light)" />

      <div class="space-y-6">
        <div class="flex items-center justify-between">
          <div>
            <h4 class="font-bold" style="color: var(--text-primary)">
              Тёмная тема
            </h4>
            <p class="text-xs" style="color: var(--text-secondary)">
              Оформление для работы вечером
            </p>
          </div>
          <button
            type="button"
            class="relative h-7 w-12 rounded-full border-2 transition"
            :style="isDark
              ? { borderColor: 'var(--color-brand)', background: 'var(--color-brand)' }
              : { borderColor: 'var(--border-light)', background: 'var(--bg-inbox)' }"
            @click="emit('toggle-theme')"
          >
            <span
              class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition-transform"
              :class="isDark ? 'translate-x-5' : ''"
            />
          </button>
        </div>

        <div class="flex items-center justify-between">
          <div>
            <h4 class="font-bold" style="color: var(--text-primary)">
              Звуковые уведомления
            </h4>
            <p class="text-xs" style="color: var(--text-secondary)">
              Звук при новом сообщении
            </p>
          </div>
          <button
            type="button"
            class="relative h-7 w-12 rounded-full border-2 transition"
            :style="soundEnabled
              ? { borderColor: 'var(--color-brand)', background: 'var(--color-brand)' }
              : { borderColor: 'var(--border-light)', background: 'var(--bg-inbox)' }"
            @click="emit('toggle-sound')"
          >
            <span
              class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition-transform"
              :class="soundEnabled ? 'translate-x-5' : ''"
            />
          </button>
        </div>
      </div>

      <p class="text-xs" style="color: var(--text-secondary)">
        Тема и звук сохраняются на этом устройстве автоматически.
      </p>

      <div class="flex items-center gap-4 pt-6">
        <button
          type="button"
          class="flex items-center gap-2 rounded-[var(--radius-md)] border border-red-500/40 bg-red-500/10 px-6 py-3 font-bold text-red-500 transition hover:bg-red-500/20"
          @click="authStore.logout()"
        >
          <LogOut class="h-4 w-4" />
          Выйти из аккаунта
        </button>
      </div>
    </div>
  </section>
</template>
