import { defineStore } from 'pinia'
import { computed, ref } from 'vue'
import type { NotificationSoundPrefs } from '../lib/notificationSoundPresets'
import { disconnectEcho } from '../lib/realtime'
import { http } from '../lib/http'
import { resolveOAuthRedirectUri } from '../lib/oauthConfig'

const STORAGE_KEY = 'api-token'

export type PulseUser = {
  id: number
  name: string
  email: string
  avatar_url?: string | null
  roles: string[]
  is_admin?: boolean
  source_ids: number[]
  department_ids: number[]
  notification_sound_prefs?: NotificationSoundPrefs | null
}

export const useAuthStore = defineStore('auth', () => {
  const token = ref<string | null>(
    typeof localStorage !== 'undefined' ? localStorage.getItem(STORAGE_KEY) : null,
  )
  const user = ref<PulseUser | null>(null)
  const lastError = ref<string | null>(null)

  const isAuthenticated = computed(() => Boolean(token.value))

  function setPulseToken(plain: string, profile?: PulseUser) {
    token.value = plain
    localStorage.setItem(STORAGE_KEY, plain)
    if (profile) user.value = profile
    disconnectEcho()
  }

  function clearSession() {
    token.value = null
    user.value = null
    localStorage.removeItem(STORAGE_KEY)
    disconnectEcho()
  }

  /** Exchange ACHPP ID access token for Pulse Sanctum token (legacy / dev). */
  async function exchangeWithPulse(idAccessToken: string, deviceName?: string) {
    lastError.value = null
    const { data } = await http.post<{ data: { token: string; user: PulseUser } }>(
      '/auth/sso/exchange',
      {
        access_token: idAccessToken,
        device_name: deviceName ?? 'pulse-mobile',
      },
    )
    setPulseToken(data.data.token, data.data.user)
  }

  /**
   * Authorization-code + PKCE completed on the Pulse API (code → IdP token → Sanctum).
   */
  async function exchangeWithAuthorizationCode(params: {
    code: string
    code_verifier: string
    state: string
    deviceName?: string
  }) {
    lastError.value = null
    const redirect_uri = resolveOAuthRedirectUri()
    const { data } = await http.post<{ data: { token: string; user: PulseUser } }>(
      '/auth/sso/exchange',
      {
        code: params.code,
        code_verifier: params.code_verifier,
        state: params.state,
        redirect_uri,
        device_name: params.deviceName ?? 'pulse-mobile',
      },
    )
    setPulseToken(data.data.token, data.data.user)
  }

  async function fetchMe() {
    const { data } = await http.get<{ data: PulseUser }>('/auth/me')
    user.value = data.data
  }

  async function logout() {
    try {
      await http.post('/auth/logout')
    } catch {
      // ignore
    } finally {
      clearSession()
    }
  }

  return {
    token,
    user,
    lastError,
    isAuthenticated,
    setPulseToken,
    clearSession,
    exchangeWithPulse,
    exchangeWithAuthorizationCode,
    fetchMe,
    logout,
  }
})
