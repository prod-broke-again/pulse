import { Browser } from '@capacitor/browser'
import { Capacitor } from '@capacitor/core'
import { createPkcePair } from './pkce'
import {
  achppIdBaseUrl,
  achppOAuthClientId,
  resolveOAuthRedirectUri,
  resolveOAuthScope,
} from './oauthConfig'
import { buildOAuthState } from './oauthState'
import { storeExpectedOAuthState, storePkceVerifier } from './oauthStorage'
import { buildAuthorizeUrl } from './oauthUrl'

export type RedirectToIdPResult =
  | { ok: true }
  | { ok: false; error: string }

/**
 * Starts OAuth2 authorization-code + PKCE against ACHPP ID.
 * - Web: window.location.assign
 * - Native: InApp Browser (return via pulseapp:// deep link → appUrlOpen)
 */
export async function redirectToIdP(): Promise<RedirectToIdPResult> {
  const idBase = achppIdBaseUrl()
  const clientId = achppOAuthClientId()
  const redirectUri = resolveOAuthRedirectUri()

  if (!idBase || !clientId || !redirectUri) {
    return {
      ok: false,
      error:
        'Задайте VITE_ACHPP_ID_BASE_URL, VITE_ACHPP_ID_CLIENT_ID и (для не-localhost web) VITE_ACHPP_ID_REDIRECT_URI.',
    }
  }

  const { verifier, challenge } = await createPkcePair()
  const state = buildOAuthState()

  await storePkceVerifier(verifier)
  await storeExpectedOAuthState(state)

  const url = buildAuthorizeUrl({
    idBaseUrl: idBase,
    clientId,
    redirectUri,
    scope: resolveOAuthScope(),
    codeChallenge: challenge,
    state,
  })

  if (Capacitor.isNativePlatform()) {
    await Browser.open({ url })
    return { ok: true }
  }

  window.location.assign(url)
  return { ok: true }
}
