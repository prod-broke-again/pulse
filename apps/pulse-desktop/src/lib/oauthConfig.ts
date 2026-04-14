/** Custom protocol redirect for Electron desktop (register in ACHPP ID Passport client). */
export const DESKTOP_OAUTH_REDIRECT_URI = 'pulse-desktop://auth/callback'

/**
 * Redirect URI must match the Passport client exactly (authorize + token exchange).
 * Desktop always uses the custom protocol registered in Electron.
 */
export function resolveOAuthRedirectUri(): string {
  return DESKTOP_OAUTH_REDIRECT_URI
}

export function resolveOAuthPlatform(): 'desktop' {
  return 'desktop'
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
