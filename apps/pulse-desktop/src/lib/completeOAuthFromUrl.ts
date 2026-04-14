import { parseOAuthCallbackParams } from './oauthUrl'
import { parseOAuthState } from './oauthState'
import { takeExpectedOAuthState, takePkceVerifier } from './oauthStorage'
import { useAuthStore } from '../stores/authStore'

const OAUTH_DONE_PREFIX = 'pulse_oauth_exchanged_'

export type CompleteOAuthResult =
  | { ok: true }
  | { ok: false; message: string }

/**
 * Finishes OAuth from pulse-desktop://auth/callback?... (or full https URL in web).
 */
export async function completeOAuthFromCallbackUrl(fullUrl: string): Promise<CompleteOAuthResult> {
  const p = parseOAuthCallbackParams(fullUrl)
  if (p.error) {
    return { ok: false, message: p.error_description ?? p.error }
  }
  if (!p.code || !p.state) {
    return { ok: false, message: 'Отсутствует код авторизации.' }
  }

  const doneKey = OAUTH_DONE_PREFIX + p.code.slice(0, 96)
  if (sessionStorage.getItem(doneKey)) {
    return { ok: true }
  }

  const expectedState = await takeExpectedOAuthState()
  if (!expectedState || p.state !== expectedState) {
    return { ok: false, message: 'Неверный state OAuth. Повторите вход.' }
  }

  parseOAuthState(p.state)

  const verifier = await takePkceVerifier()
  if (!verifier) {
    return { ok: false, message: 'Сессия PKCE устарела. Откройте вход снова.' }
  }

  const auth = useAuthStore()
  try {
    await auth.exchangeWithAuthorizationCode({
      code: p.code,
      code_verifier: verifier,
      state: p.state,
    })
    sessionStorage.setItem(doneKey, '1')
    return { ok: true }
  } catch (e: unknown) {
    const ax = e as { message?: string }
    return { ok: false, message: ax.message ?? 'Не удалось завершить вход.' }
  }
}
