import { Capacitor } from '@capacitor/core'
import { Preferences } from '@capacitor/preferences'

const KEYS = {
  pkceVerifier: 'pulse_oauth_pkce_verifier',
  oauthState: 'pulse_oauth_state_expected',
} as const

async function setItem(key: string, value: string): Promise<void> {
  if (Capacitor.isNativePlatform()) {
    await Preferences.set({ key, value })
    return
  }
  localStorage.setItem(key, value)
}

async function takeItem(key: string): Promise<string | null> {
  if (Capacitor.isNativePlatform()) {
    const { value } = await Preferences.get({ key })
    if (value) {
      await Preferences.remove({ key })
    }
    return value ?? null
  }
  const v = localStorage.getItem(key)
  if (v !== null) {
    localStorage.removeItem(key)
  }
  return v
}

export async function storePkceVerifier(value: string): Promise<void> {
  await setItem(KEYS.pkceVerifier, value)
}

/** Returns and removes one-time verifier. */
export async function takePkceVerifier(): Promise<string | null> {
  return takeItem(KEYS.pkceVerifier)
}

export async function storeExpectedOAuthState(value: string): Promise<void> {
  await setItem(KEYS.oauthState, value)
}

export async function takeExpectedOAuthState(): Promise<string | null> {
  return takeItem(KEYS.oauthState)
}
