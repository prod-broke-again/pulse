<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { Camera, Loader2, LogOut, RefreshCw, Volume2 } from 'lucide-vue-next'
import { useAuthStore } from '../../stores/authStore'
import { uploadAvatar } from '../../api/auth'
import { patchNotificationSoundPreferences } from '../../api/notificationSoundPreferences'
import { redirectToIdP } from '../../lib/redirectToIdP'
import { achppIdBaseUrl, achppOAuthClientId, resolveOAuthRedirectUri } from '../../lib/oauthConfig'
import {
  PRESET_LABELS,
  type NotificationPresetId,
  type NotificationSoundPrefs,
  mergeNotificationSoundPrefs,
} from '../../lib/notificationSoundPresets'
import { playIncomingToneFromPrefs, setLocalCustomSoundDataUrl, getLocalCustomSoundDataUrl } from '../../lib/desktopNotifications'
import { fetchNotificationSoundPreferences } from '../../api/notificationSoundPreferences'
import { useInboxFilterPrefsForm, INBOX_CHANNEL_LABELS } from '../../lib/useInboxFilterPrefsForm'

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

const isStaff = computed(
  () =>
    !!user.value?.roles?.some((r) => r === 'admin' || r === 'moderator'),
)

const {
  userSources: inboxUserSources,
  channelTypesInSources: inboxChannelTypes,
  departments: inboxDepartments,
  departmentsLoading: inboxDeptsLoading,
  prefEnabledSources,
  prefEnabledChannels,
  prefEnabledDepartments,
  prefsSaving: inboxPrefsSaving,
  prefsSaveError: inboxPrefsSaveError,
  ensureDepartmentsLoaded,
  saveInboxPrefsDefaults,
  togglePrefId: inboxToggleId,
  togglePrefStr: inboxToggleStr,
} = useInboxFilterPrefsForm()

function inboxChannelLabel(t: string): string {
  return INBOX_CHANNEL_LABELS[t] ?? t
}

const localPrefs = ref(mergeNotificationSoundPrefs(null))
const soundSaveBusy = ref(false)
const hasCustomSound = ref(!!getLocalCustomSoundDataUrl())
const customSoundInput = ref<HTMLInputElement | null>(null)

watch(
  () => user.value?.notification_sound_prefs,
  (p) => {
    localPrefs.value = mergeNotificationSoundPrefs(p ?? null)
  },
  { immediate: true, deep: true },
)

const hasElectronWindowPrefs = typeof window !== 'undefined' && Boolean(window.pulseWindowSettings)
const closeButtonBehavior = ref<'ask' | 'quit' | 'hide-to-tray'>('ask')
const closePrefsReady = ref(!hasElectronWindowPrefs)

async function persistCloseButtonBehavior(): Promise<void> {
  try {
    await window.pulseWindowSettings?.setPrefs({ closeButtonBehavior: closeButtonBehavior.value })
  } catch {
    /* ignore */
  }
}

onMounted(async () => {
  if (window.pulseWindowSettings) {
    try {
      const p = await window.pulseWindowSettings.getPrefs()
      closeButtonBehavior.value = p.closeButtonBehavior
    } catch {
      /* ignore */
    } finally {
      closePrefsReady.value = true
    }
  }

  if (!user.value || !isStaff.value) {
    return
  }
  try {
    const data = await fetchNotificationSoundPreferences()
    localPrefs.value = mergeNotificationSoundPrefs(data.notification_sound_prefs)
    authStore.applyNotificationSoundPrefs(data.notification_sound_prefs)
  } catch {
    /* offline / old API */
  }

  if (isStaff.value) {
    void ensureDepartmentsLoaded()
  }
})

const presetIds = computed(() => Object.keys(PRESET_LABELS) as NotificationPresetId[])

let volumeDebounce: number | null = null
function scheduleVolumeSave(): void {
  if (volumeDebounce != null) {
    window.clearTimeout(volumeDebounce)
  }
  volumeDebounce = window.setTimeout(() => {
    volumeDebounce = null
    void persistSoundPrefs({ volume: localPrefs.value.volume })
  }, 400)
}

async function persistSoundPrefs(
  partial: Partial<{
    mute: boolean
    volume: number
    presets: Partial<NotificationSoundPrefs['presets']>
  }>,
): Promise<void> {
  if (!isStaff.value) {
    return
  }
  soundSaveBusy.value = true
  try {
    const data = await patchNotificationSoundPreferences(partial)
    authStore.applyUserProfile(data.user)
    localPrefs.value = mergeNotificationSoundPrefs(data.notification_sound_prefs)
  } catch {
    /* ignore */
  } finally {
    soundSaveBusy.value = false
  }
}

function onMuteToggle(): void {
  const next = !localPrefs.value.mute
  localPrefs.value = { ...localPrefs.value, mute: next }
  void persistSoundPrefs({ mute: next })
}

function onVolumeInput(e: Event): void {
  const t = e.target as HTMLInputElement
  const v = Number(t.value) / 100
  localPrefs.value = { ...localPrefs.value, volume: v }
  scheduleVolumeSave()
}

function onPresetChange(
  key: keyof NotificationSoundPrefs['presets'],
  ev: Event,
): void {
  const id = (ev.target as HTMLSelectElement).value as NotificationPresetId
  localPrefs.value.presets[key] = id
  void persistSoundPrefs({ presets: { [key]: id } })
}

function testPlayScenario(which: 'in_app' | 'background' | 'important'): void {
  const p = localPrefs.value
  playIncomingToneFromPrefs(p, which === 'important' ? 'important' : which)
}

function triggerCustomSoundPick(): void {
  customSoundInput.value?.click()
}

async function onCustomSoundFile(ev: Event): Promise<void> {
  const t = ev.target as HTMLInputElement
  const file = t.files?.[0]
  t.value = ''
  if (!file) {
    return
  }
  if (file.size > 1_500_000) {
    return
  }
  const reader = new FileReader()
  reader.onload = () => {
    const dataUrl = typeof reader.result === 'string' ? reader.result : null
    if (dataUrl) {
      setLocalCustomSoundDataUrl(dataUrl)
      hasCustomSound.value = true
    }
  }
  reader.readAsDataURL(file)
}

function clearCustomSound(): void {
  setLocalCustomSoundDataUrl(null)
  hasCustomSound.value = false
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

        <div v-if="hasElectronWindowPrefs" class="rounded-[var(--radius-md)] border p-5" style="border-color: var(--border-light); background: var(--bg-inbox)">
          <h4 class="font-bold" style="color: var(--text-primary)">
            Кнопка «Закрыть» в окне
          </h4>
          <p class="mt-1 text-xs leading-relaxed" style="color: var(--text-secondary)">
            Только на этом компьютере. В фоне приложение остаётся в трее и получает чаты по сети.
          </p>
          <fieldset v-if="closePrefsReady" class="mt-4 space-y-3">
            <label class="flex cursor-pointer items-start gap-3 text-sm" style="color: var(--text-primary)">
              <input v-model="closeButtonBehavior" class="mt-1" type="radio" value="ask" @change="persistCloseButtonBehavior">
              <span>
                <span class="font-medium">Спрашивать каждый раз</span>
                <span class="mt-0.5 block text-xs" style="color: var(--text-muted)">Показать выбор: в фоне или полный выход</span>
              </span>
            </label>
            <label class="flex cursor-pointer items-start gap-3 text-sm" style="color: var(--text-primary)">
              <input v-model="closeButtonBehavior" class="mt-1" type="radio" value="hide-to-tray" @change="persistCloseButtonBehavior">
              <span>
                <span class="font-medium">Сворачивать в трей</span>
                <span class="mt-0.5 block text-xs" style="color: var(--text-muted)">Окно скрывается, иконка остаётся возле часов</span>
              </span>
            </label>
            <label class="flex cursor-pointer items-start gap-3 text-sm" style="color: var(--text-primary)">
              <input v-model="closeButtonBehavior" class="mt-1" type="radio" value="quit" @change="persistCloseButtonBehavior">
              <span>
                <span class="font-medium">Полностью закрывать приложение</span>
                <span class="mt-0.5 block text-xs" style="color: var(--text-muted)">Завершить процесс, как «Выйти» в трее</span>
              </span>
            </label>
          </fieldset>
          <p v-else class="mt-3 text-xs" style="color: var(--text-muted)">
            Загрузка…
          </p>
        </div>

        <div v-if="!isStaff" class="flex items-center justify-between">
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

        <div v-else class="space-y-5 rounded-[var(--radius-md)] border p-5" style="border-color: var(--border-light); background: var(--bg-inbox)">
          <div class="flex items-center justify-between gap-4">
            <div>
              <h4 class="font-bold" style="color: var(--text-primary)">
                Звук уведомлений
              </h4>
              <p class="text-xs" style="color: var(--text-secondary)">
                Синхронизируется с аккаунтом; свой файл — только на этом устройстве
              </p>
            </div>
            <button
              type="button"
              class="relative h-7 w-12 shrink-0 rounded-full border-2 transition disabled:opacity-50"
              :disabled="soundSaveBusy"
              :style="!localPrefs.mute
                ? { borderColor: 'var(--color-brand)', background: 'var(--color-brand)' }
                : { borderColor: 'var(--border-light)', background: 'var(--bg-inbox)' }"
              @click="onMuteToggle"
            >
              <span
                class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition-transform"
                :class="!localPrefs.mute ? 'translate-x-5' : ''"
              />
            </button>
          </div>

          <div>
            <label class="mb-1 block text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary)">Громкость</label>
            <input
              type="range"
              min="0"
              max="100"
              :value="Math.round(localPrefs.volume * 100)"
              class="w-full accent-[var(--color-brand)]"
              :disabled="soundSaveBusy || localPrefs.mute"
              @input="onVolumeInput"
            >
          </div>

          <div class="grid gap-3 sm:grid-cols-2">
            <div v-for="key in (['in_app', 'background', 'important', 'in_chat'] as const)" :key="key">
              <label class="mb-1 block text-xs font-semibold capitalize" style="color: var(--text-secondary)">
                {{ key === 'in_app' ? 'В приложении (список чатов)' : key === 'background' ? 'Фон / не в фокусе' : key === 'important' ? 'Важное (срочный чат)' : 'В открытом чате' }}
              </label>
              <select
                class="h-10 w-full rounded-[var(--radius-md)] border px-2 text-sm font-medium outline-none"
                style="border-color: var(--border-light); background: var(--bg-thread); color: var(--text-primary)"
                :value="localPrefs.presets[key]"
                :disabled="soundSaveBusy"
                @change="onPresetChange(key, $event)"
              >
                <option v-for="pid in presetIds" :key="pid" :value="pid">
                  {{ PRESET_LABELS[pid] }}
                </option>
              </select>
            </div>
          </div>

          <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold" style="color: var(--text-secondary)">Проверка:</span>
            <button
              type="button"
              class="rounded-md border px-2 py-1 text-xs font-semibold"
              style="border-color: var(--border-light); color: var(--text-primary)"
              @click="testPlayScenario('in_app')"
            >
              В приложении
            </button>
            <button
              type="button"
              class="rounded-md border px-2 py-1 text-xs font-semibold"
              style="border-color: var(--border-light); color: var(--text-primary)"
              @click="testPlayScenario('background')"
            >
              Фон
            </button>
            <button
              type="button"
              class="rounded-md border px-2 py-1 text-xs font-semibold"
              style="border-color: var(--border-light); color: var(--text-primary)"
              @click="testPlayScenario('important')"
            >
              Важное
            </button>
          </div>

          <div class="border-t pt-4" style="border-color: var(--border-light)">
            <p class="mb-2 text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary)">
              Свой звук (локально)
            </p>
            <p class="mb-2 text-xs" style="color: var(--text-muted)">
              До ~1.5 МБ, .wav / .mp3. На другом устройстве будет пресет с сервера, пока не загрузите файл там.
            </p>
            <input
              ref="customSoundInput"
              type="file"
              accept="audio/*,.wav,.mp3"
              class="hidden"
              @change="onCustomSoundFile"
            >
            <div class="flex flex-wrap gap-2">
              <button
                type="button"
                class="inline-flex items-center gap-2 rounded-md border px-3 py-2 text-xs font-semibold"
                style="border-color: var(--border-light); color: var(--text-primary)"
                @click="triggerCustomSoundPick"
              >
                <Volume2 class="h-3.5 w-3.5" />
                Загрузить файл
              </button>
              <button
                v-if="hasCustomSound"
                type="button"
                class="rounded-md border border-red-500/40 px-3 py-2 text-xs font-semibold text-red-500"
                @click="clearCustomSound"
              >
                Сбросить
              </button>
            </div>
          </div>
        </div>

        <div
          v-if="isStaff"
          class="space-y-4 rounded-[var(--radius-md)] border p-5"
          style="border-color: var(--border-light); background: var(--bg-inbox)"
        >
          <div>
            <h4 class="font-bold" style="color: var(--text-primary)">
              Фильтры инбокса по умолчанию
            </h4>
            <p class="mt-1 text-xs leading-relaxed" style="color: var(--text-secondary)">
              Какие источники, площадки (тип: Telegram, VK, сайт…) и рубрики учитывать в списке обращений при входе. Быстрый фильтр по статусу, источнику и площадке — в кнопке с иконкой у строки поиска; без сохранения он действует только в этой сессии.
            </p>
          </div>
          <p v-if="inboxPrefsSaveError" class="text-xs font-medium text-red-500" role="alert">
            {{ inboxPrefsSaveError }}
          </p>
          <div v-if="inboxDeptsLoading" class="flex items-center gap-2 text-xs" style="color: var(--text-muted)">
            <Loader2 class="h-4 w-4 animate-spin shrink-0" aria-hidden="true" />
            Загрузка рубрик…
          </div>
          <div class="space-y-3 text-sm">
            <p class="text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary)">
              Источники
            </p>
            <ul v-if="inboxUserSources.length" class="space-y-2">
              <li v-for="s in inboxUserSources" :key="'set-src-' + s.id" class="flex items-start gap-2">
                <input
                  :id="'set-src-' + s.id"
                  type="checkbox"
                  class="mt-1 rounded border"
                  style="border-color: var(--border-light)"
                  :checked="prefEnabledSources.includes(s.id)"
                  @change="prefEnabledSources = inboxToggleId(prefEnabledSources, s.id)"
                >
                <label class="cursor-pointer" style="color: var(--text-secondary)" :for="'set-src-' + s.id">{{ s.name }}</label>
              </li>
            </ul>
            <p v-else class="text-xs" style="color: var(--text-muted)">
              Нет доступных источников.
            </p>
            <p class="text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary)">
              Площадки
            </p>
            <ul v-if="inboxChannelTypes.length" class="space-y-2">
              <li v-for="t in inboxChannelTypes" :key="'set-ch-' + t" class="flex items-start gap-2">
                <input
                  :id="'set-ch-' + t"
                  type="checkbox"
                  class="mt-1 rounded border"
                  style="border-color: var(--border-light)"
                  :checked="prefEnabledChannels.includes(t)"
                  @change="prefEnabledChannels = inboxToggleStr(prefEnabledChannels, t)"
                >
                <label class="cursor-pointer" style="color: var(--text-secondary)" :for="'set-ch-' + t">
                  {{ inboxChannelLabel(t) }}
                </label>
              </li>
            </ul>
            <p v-else class="text-xs" style="color: var(--text-muted)">
              —
            </p>
            <p class="text-xs font-bold uppercase tracking-wide" style="color: var(--text-secondary)">
              Рубрики
            </p>
            <ul v-if="inboxDepartments.length" class="thin-scroll max-h-48 space-y-2 overflow-y-auto">
              <li v-for="d in inboxDepartments" :key="'set-dep-' + d.id" class="flex items-start gap-2">
                <input
                  :id="'set-dep-' + d.id"
                  type="checkbox"
                  class="mt-1 rounded border"
                  style="border-color: var(--border-light)"
                  :checked="prefEnabledDepartments.includes(d.id)"
                  @change="prefEnabledDepartments = inboxToggleId(prefEnabledDepartments, d.id)"
                >
                <label class="cursor-pointer" style="color: var(--text-secondary)" :for="'set-dep-' + d.id">{{ d.name }}</label>
              </li>
            </ul>
            <p v-else class="text-xs" style="color: var(--text-muted)">
              Нет рубрик или ещё загружаются.
            </p>
          </div>
          <button
            type="button"
            class="inline-flex w-full items-center justify-center gap-2 rounded-[var(--radius-md)] py-2.5 text-sm font-semibold text-white transition disabled:opacity-50"
            style="background: var(--color-brand-200)"
            :disabled="inboxPrefsSaving"
            @click="saveInboxPrefsDefaults()"
          >
            <Loader2 v-if="inboxPrefsSaving" class="h-4 w-4 animate-spin" aria-hidden="true" />
            Сохранить фильтры инбокса
          </button>
        </div>
      </div>

      <p class="text-xs" style="color: var(--text-secondary)">
        Тема и поведение кнопки «Закрыть» сохраняются на этом устройстве. Настройки звука модератора — в аккаунте на сервере.
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
