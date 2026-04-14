import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { exchangeSso, exchangeSsoAccessToken, logout as apiLogout, getMe } from '../api/auth'
import { getToken, clearToken } from '../api/client'
import { loadCachedUser, saveCachedUser } from '../lib/authSessionCache'
import { disconnectEcho } from '../lib/realtime'
import { resolveOAuthRedirectUri } from '../lib/oauthConfig'
import type { ApiUser } from '../types/dto/auth.types'
import type { NotificationSoundPrefs } from '../lib/notificationSoundPresets'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<ApiUser | null>(null)
  const isAuthenticated = computed(() => !!user.value)
  const lastError = ref<string | null>(null)
  /**
   * Есть токен, но профиль ещё не подставлен из кэша — показываем загрузку вместо экрана входа.
   */
  const authBootstrapPending = ref(false)

  function hydrateFromCache(): void {
    if (!getToken()) {
      authBootstrapPending.value = false
      return
    }
    const cached = loadCachedUser()
    if (cached) {
      user.value = cached
      authBootstrapPending.value = false
    } else {
      authBootstrapPending.value = true
    }
  }

  function finishAuthBootstrap(): void {
    authBootstrapPending.value = false
  }

  async function exchangeWithAuthorizationCode(params: {
    code: string
    code_verifier: string
    state: string
    deviceName?: string
  }) {
    lastError.value = null
    const redirect_uri = resolveOAuthRedirectUri()
    const data = await exchangeSso({
      code: params.code,
      code_verifier: params.code_verifier,
      state: params.state,
      redirect_uri,
      deviceName: params.deviceName,
    })
    user.value = data.user
    saveCachedUser(data.user)
    authBootstrapPending.value = false
    return data
  }

  async function exchangeWithPulseAccessToken(idAccessToken: string, deviceName?: string) {
    lastError.value = null
    const data = await exchangeSsoAccessToken(idAccessToken, deviceName)
    user.value = data.user
    saveCachedUser(data.user)
    authBootstrapPending.value = false
    return data
  }

  async function logout() {
    disconnectEcho()
    await apiLogout()
    user.value = null
    authBootstrapPending.value = false
  }

  async function fetchMe() {
    const token = getToken()
    if (!token) {
      user.value = null
      return
    }

    try {
      const me = await getMe()
      user.value = me
      saveCachedUser(me)
    } catch (e) {
      clearToken()
      user.value = null
      throw e
    }
  }

  function applyUserProfile(profile: ApiUser): void {
    user.value = profile
    saveCachedUser(profile)
  }

  function applyNotificationSoundPrefs(prefs: NotificationSoundPrefs): void {
    if (!user.value) {
      return
    }
    const next: ApiUser = {
      ...user.value,
      notification_sound_prefs: prefs,
    }
    user.value = next
    saveCachedUser(next)
  }

  return {
    user,
    lastError,
    isAuthenticated,
    authBootstrapPending,
    hydrateFromCache,
    finishAuthBootstrap,
    exchangeWithAuthorizationCode,
    exchangeWithPulseAccessToken,
    logout,
    fetchMe,
    applyUserProfile,
    applyNotificationSoundPrefs,
  }
})
