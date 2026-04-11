<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { achppIdBaseUrl, achppOAuthClientId, resolveOAuthRedirectUri } from '../lib/oauthConfig'
import { redirectToIdP } from '../lib/redirectToIdP'
import { useAuthStore } from '../stores/authStore'

const router = useRouter()
const auth = useAuthStore()

const manualToken = ref('')
const busy = ref(false)
const error = ref<string | null>(null)

const pkceConfigured = Boolean(
  achppIdBaseUrl() && achppOAuthClientId() && resolveOAuthRedirectUri(),
)

async function startAchppLogin() {
  error.value = null
  const result = await redirectToIdP()
  if (!result.ok) {
    error.value = result.error
  }
}

async function exchangeManual() {
  error.value = null
  const t = manualToken.value.trim()
  if (!t) {
    error.value = 'Вставьте access token ACHPP ID.'
    return
  }
  busy.value = true
  try {
    await auth.exchangeWithPulse(t)
    await router.replace({ name: 'inbox' })
  } catch (e: unknown) {
    const ax = e as { response?: { data?: { message?: string }; status?: number } }
    const msg = ax.response?.data?.message
    const status = ax.response?.status
    if (status === 403) {
      error.value =
        msg ??
        'Нет прав модератора в Pulse. Обратитесь к администратору.'
    } else if (status === 401) {
      error.value = msg ?? 'Недействительный или просроченный токен ACHPP ID.'
    } else {
      error.value = msg ?? 'Не удалось выполнить вход.'
    }
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <div
    class="flex min-h-0 flex-1 flex-col justify-center gap-6 px-6 pb-10 pt-[calc(24px+var(--safe-top))]"
  >
    <div>
      <h1 class="text-xl font-bold text-[var(--color-dark)] dark:text-[var(--zinc-50)]">
        Вход в Pulse
      </h1>
      <p class="mt-2 text-sm text-[var(--zinc-500)]">
        Используйте единый аккаунт АЧПП ID. Управление профилем и паролем — в ID.
      </p>
    </div>

    <button
      type="button"
      class="flex h-12 w-full items-center justify-center rounded-2xl bg-[var(--color-brand)] text-sm font-semibold text-white shadow-sm transition-opacity active:opacity-90 disabled:opacity-50"
      :disabled="busy"
      @click="startAchppLogin"
    >
      Войти через АЧПП ID
    </button>

    <div v-if="!pkceConfigured" class="rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-900 dark:bg-amber-950/40 dark:text-amber-200">
      Задайте переменные окружения (см. <code class="rounded bg-white/50 px-1">.env.example</code>): для localhost redirect
      <code class="rounded bg-white/50 px-1">http://localhost:5174/auth/callback</code> подставляется автоматически.
    </div>

    <div class="border-t border-[var(--color-gray-line)] pt-6 dark:border-[var(--zinc-700)]">
      <p class="mb-2 text-[11px] font-bold uppercase tracking-wide text-[var(--zinc-400)]">
        Ручной обмен (dev)
      </p>
      <textarea
        v-model="manualToken"
        rows="3"
        placeholder="Access token из ACHPP ID"
        class="mb-3 w-full rounded-xl border border-[var(--color-gray-line)] bg-white px-3 py-2 text-xs text-[var(--color-dark)] outline-none dark:border-[var(--zinc-700)] dark:bg-[var(--zinc-800)] dark:text-[var(--zinc-100)]"
      />
      <button
        type="button"
        class="flex h-11 w-full items-center justify-center rounded-xl border border-[var(--color-gray-line)] text-sm font-medium text-[var(--color-dark)] dark:border-[var(--zinc-600)] dark:text-[var(--zinc-100)]"
        :disabled="busy"
        @click="exchangeManual"
      >
        Обменять на токен Pulse
      </button>
    </div>

    <p v-if="error" class="text-center text-sm text-red-600 dark:text-red-400" role="alert">
      {{ error }}
    </p>
  </div>
</template>
