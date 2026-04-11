<script setup lang="ts">
import { Browser } from '@capacitor/browser'
import { Capacitor } from '@capacitor/core'
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { parseOAuthState } from '../lib/oauthState'
import { takeExpectedOAuthState, takePkceVerifier } from '../lib/oauthStorage'
import { useAuthStore } from '../stores/authStore'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()

const message = ref('Завершение входа…')
const failed = ref(false)

/** Avoid double exchange: dev Strict Mode remount or duplicate onMounted — OAuth code is single-use. */
const OAUTH_DONE_PREFIX = 'pulse_oauth_exchanged_'
let inflightExchange: Promise<void> | null = null

onMounted(async () => {
  if (inflightExchange) {
    await inflightExchange
    return
  }

  inflightExchange = (async () => {
    const err =
      typeof route.query.error === 'string' ? route.query.error : null
    const desc =
      typeof route.query.error_description === 'string'
        ? route.query.error_description
        : null
    if (err) {
      failed.value = true
      message.value = desc ?? err
      if (Capacitor.isNativePlatform()) {
        await Browser.close().catch(() => {})
      }
      return
    }

    const code = typeof route.query.code === 'string' ? route.query.code : null
    const state = typeof route.query.state === 'string' ? route.query.state : null

    if (!code) {
      failed.value = true
      message.value = 'Отсутствует код авторизации.'
      if (Capacitor.isNativePlatform()) {
        await Browser.close().catch(() => {})
      }
      return
    }

    const doneKey = OAUTH_DONE_PREFIX + code.slice(0, 96)
    if (sessionStorage.getItem(doneKey)) {
      await router.replace({ name: 'inbox' })
      return
    }

    const expectedState = await takeExpectedOAuthState()
    if (!state || !expectedState || state !== expectedState) {
      failed.value = true
      message.value = 'Неверный state OAuth. Повторите вход.'
      if (Capacitor.isNativePlatform()) {
        await Browser.close().catch(() => {})
      }
      return
    }

    parseOAuthState(state)

    const verifier = await takePkceVerifier()
    if (!verifier) {
      failed.value = true
      message.value = 'Сессия PKCE устарела. Откройте вход снова.'
      if (Capacitor.isNativePlatform()) {
        await Browser.close().catch(() => {})
      }
      return
    }

    try {
      await auth.exchangeWithAuthorizationCode({
        code,
        code_verifier: verifier,
        state,
      })
      sessionStorage.setItem(doneKey, '1')
      if (Capacitor.isNativePlatform()) {
        await Browser.close().catch(() => {})
      }
      await router.replace({ name: 'inbox' })
    } catch (e: unknown) {
      failed.value = true
      const ax = e as {
        response?: { data?: { message?: string }; status?: number }
        message?: string
      }
      const status = ax.response?.status
      const msg = ax.response?.data?.message ?? ax.message
      if (status === 403) {
        message.value =
          msg ?? 'Нет прав модератора в Pulse. Обратитесь к администратору.'
      } else if (status === 401) {
        message.value = msg ?? 'Не удалось обменять код авторизации.'
      } else if (status === 503) {
        message.value =
          msg ??
          'Сервер не смог связаться с ACHPP ID. Проверьте сеть и настройки ACHPP_ID_* на Pulse.'
      } else {
        message.value = msg ?? 'Не удалось завершить вход.'
      }
      if (Capacitor.isNativePlatform()) {
        await Browser.close().catch(() => {})
      }
    }
  })()

  try {
    await inflightExchange
  } finally {
    inflightExchange = null
  }
})

function goLogin() {
  void router.replace({ name: 'login' })
}
</script>

<template>
  <div
    class="flex min-h-0 flex-1 flex-col items-center justify-center gap-4 px-6 text-center"
  >
    <p
      class="text-sm"
      :class="
        failed
          ? 'text-red-600 dark:text-red-400'
          : 'text-[var(--zinc-600)] dark:text-[var(--zinc-300)]'
      "
    >
      {{ message }}
    </p>
    <button
      v-if="failed"
      type="button"
      class="rounded-xl bg-[var(--color-brand)] px-4 py-2 text-sm font-medium text-white"
      @click="goLogin"
    >
      На экран входа
    </button>
  </div>
</template>
