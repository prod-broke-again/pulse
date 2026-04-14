import { resolveOAuthPlatform } from './oauthConfig'

export type OAuthStatePayload = {
  /** Hint for IdP / logging */
  platform: 'desktop'
  /** CSRF nonce */
  n: string
}

function base64UrlEncodeUtf8(text: string): string {
  const bytes = new TextEncoder().encode(text)
  let binary = ''
  for (let i = 0; i < bytes.length; i++) {
    binary += String.fromCharCode(bytes[i]!)
  }
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')
}

/**
 * Opaque `state` for OAuth (CSRF + platform), URL-safe base64 of JSON.
 */
export function buildOAuthState(): string {
  const payload: OAuthStatePayload = {
    platform: resolveOAuthPlatform(),
    n: crypto.randomUUID(),
  }
  return base64UrlEncodeUtf8(JSON.stringify(payload))
}

export function parseOAuthState(state: string): OAuthStatePayload | null {
  try {
    const pad = state.length % 4 === 0 ? '' : '='.repeat(4 - (state.length % 4))
    const b64 = state.replace(/-/g, '+').replace(/_/g, '/') + pad
    const json = atob(b64)
    const data = JSON.parse(json) as OAuthStatePayload
    if (data && typeof data.n === 'string' && data.platform) {
      return data
    }
  } catch {
    // ignore
  }
  return null
}
