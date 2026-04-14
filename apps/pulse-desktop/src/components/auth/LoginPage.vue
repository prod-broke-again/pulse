<script setup lang="ts">
import { ref } from 'vue'
import { achppIdBaseUrl, achppOAuthClientId, resolveOAuthRedirectUri } from '../../lib/oauthConfig'
import { redirectToIdP } from '../../lib/redirectToIdP'
import { useAuthStore } from '../../stores/authStore'

const emit = defineEmits<{
  (e: 'login-success'): void
}>()

const authStore = useAuthStore()
const manualToken = ref('')
const busy = ref(false)
const error = ref<string | null>(null)

const pkceConfigured = Boolean(
  achppIdBaseUrl() && achppOAuthClientId() && resolveOAuthRedirectUri(),
)

async function startAchppLogin() {
  error.value = null
  busy.value = true
  try {
    const result = await redirectToIdP()
    if (!result.ok) {
      error.value = result.error
    }
  } finally {
    busy.value = false
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
    await authStore.exchangeWithPulseAccessToken(t)
    emit('login-success')
  } catch (e: unknown) {
    const ax = e as { message?: string }
    error.value = ax.message ?? 'Не удалось выполнить вход.'
  } finally {
    busy.value = false
  }
}
</script>

<template>
  <section
    class="flex min-h-0 flex-1 flex-col items-center justify-center px-8 py-10"
    style="background: var(--bg-app)"
  >
    <div class="w-full max-w-md rounded-[var(--radius-xl)] border p-8 shadow-[var(--shadow-lg)]" style="border-color: var(--border-light); background: var(--bg-inbox)">
      <h1 class="text-xl font-bold" style="color: var(--text-primary)">
        Вход в Pulse
      </h1>
      <p class="mt-2 text-sm" style="color: var(--text-secondary)">
        Единый аккаунт АЧПП ID. Пароль и профиль — в ID.
      </p>

      <button
        type="button"
        class="mt-6 flex h-11 w-full items-center justify-center rounded-[var(--radius-md)] text-sm font-semibold text-white transition-opacity disabled:opacity-50"
        style="background: var(--color-brand)"
        :disabled="busy"
        @click="startAchppLogin"
      >
        Войти через АЧПП ID
      </button>

      <p v-if="!pkceConfigured" class="login-config-hint mt-4 rounded-[var(--radius-md)] border px-3 py-2.5 text-xs leading-relaxed">
        Задайте <code class="login-config-code">VITE_ACHPP_ID_BASE_URL</code> и
        <code class="login-config-code">VITE_ACHPP_ID_CLIENT_ID</code>
        (см. <code class="login-config-code">.env</code>).
        Redirect URI для desktop:
        <code class="login-config-code">pulse-desktop://auth/callback</code>
      </p>

      <div class="mt-8 border-t pt-6" style="border-color: var(--border-light)">
        <p class="mb-2 text-[11px] font-bold uppercase tracking-wide" style="color: var(--text-muted)">
          Ручной обмен (dev)
        </p>
        <textarea
          v-model="manualToken"
          rows="3"
          placeholder="Access token из ACHPP ID"
          class="mb-3 w-full rounded-[var(--radius-md)] border px-3 py-2 text-xs outline-none"
          style="border-color: var(--border-light); background: var(--bg-thread); color: var(--text-primary)"
        />
        <button
          type="button"
          class="flex h-10 w-full items-center justify-center rounded-[var(--radius-md)] border text-sm font-medium"
          style="border-color: var(--border-light); color: var(--text-primary)"
          :disabled="busy"
          @click="exchangeManual"
        >
          Обменять на токен Pulse
        </button>
      </div>

      <p v-if="error" class="login-error mt-4 text-center text-sm font-medium" role="alert">
        {{ error }}
      </p>
    </div>
  </section>
</template>

<style scoped>
/* Явный контраст в светлой теме (раньше Tailwind amber почти не читался). */
.login-config-hint {
  border-color: #d97706;
  background: #fffbeb;
  color: #78350f;
}
.login-config-code {
  border-radius: 4px;
  padding: 0.1rem 0.35rem;
  font-family: ui-monospace, monospace;
  font-size: 0.7rem;
  font-weight: 600;
  background: rgba(120, 53, 15, 0.12);
  color: #451a03;
}
.login-error {
  color: #b91c1c;
}
</style>

<style>
/* Тёмная тема: класс и data-theme выставляются в App.vue на <html> */
html.dark .login-config-hint,
html[data-theme='dark'] .login-config-hint {
  border-color: #b45309;
  background: #451a03;
  color: #fef3c7;
}
html.dark .login-config-code,
html[data-theme='dark'] .login-config-code {
  background: rgba(254, 243, 199, 0.15);
  color: #fde68a;
}
html.dark .login-error,
html[data-theme='dark'] .login-error {
  color: #fca5a5;
}
</style>
