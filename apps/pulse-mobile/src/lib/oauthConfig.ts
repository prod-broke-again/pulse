import { Capacitor } from '@capacitor/core'

/** Custom scheme redirect for native builds (register in Android/iOS + IdP client). */
export const MOBILE_OAUTH_REDIRECT_URI = 'pulseapp://auth/callback'

/**
 * Redirect URI must match the Passport client exactly (authorize + token exchange).
 * - Web dev on localhost → fixed port 5174 per project convention.
 * - Native → custom scheme.
 * - Other web → explicit env (staging/prod).
 */
export function resolveOAuthRedirectUri(): string {
  if (Capacitor.isNativePlatform()) {
    return MOBILE_OAUTH_REDIRECT_URI
  }
  if (typeof window !== 'undefined' && window.location.hostname === 'localhost') {
    return 'http://localhost:5174/auth/callback'
  }
  const fromEnv = import.meta.env.VITE_ACHPP_ID_REDIRECT_URI?.trim()
  if (fromEnv) {
    return fromEnv
  }
  return ''
}

export function resolveOAuthPlatform(): 'web' | 'ios' | 'android' {
  if (!Capacitor.isNativePlatform()) {
    return 'web'
  }
  return Capacitor.getPlatform() === 'ios' ? 'ios' : 'android'
}

/** Default `*` if unset; IdP may map to allowed scopes (e.g. basic). */
export function resolveOAuthScope(): string {
  return import.meta.env.VITE_ACHPP_ID_SCOPE?.trim() || '*'
}

export function achppIdBaseUrl(): string {
  return import.meta.env.VITE_ACHPP_ID_BASE_URL?.replace(/\/$/, '') ?? ''
}

export function achppOAuthClientId(): string {
  return import.meta.env.VITE_ACHPP_ID_CLIENT_ID?.trim() ?? ''
}
