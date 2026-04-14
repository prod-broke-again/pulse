/**
 * Build ACHPP IdP authorize URL from discovery base (VITE_ACHPP_ID_BASE_URL).
 */
export function buildAuthorizeUrl(params: {
  idBaseUrl: string
  clientId: string
  redirectUri: string
  scope: string
  codeChallenge: string
  state: string
}): string {
  const base = params.idBaseUrl.replace(/\/$/, '')
  const u = new URL(`${base}/oauth/authorize`)
  u.searchParams.set('client_id', params.clientId)
  u.searchParams.set('redirect_uri', params.redirectUri)
  u.searchParams.set('response_type', 'code')
  u.searchParams.set('scope', params.scope)
  u.searchParams.set('code_challenge', params.codeChallenge)
  u.searchParams.set('code_challenge_method', 'S256')
  u.searchParams.set('state', params.state)
  return u.toString()
}

/** Parse code/state/error from full callback URL (https, pulse-desktop://, etc.). */
export function parseOAuthCallbackParams(fullUrl: string): {
  code?: string
  state?: string
  error?: string
  error_description?: string
} {
  const q = fullUrl.includes('?') ? fullUrl.split('?')[1]!.split('#')[0]! : ''
  if (!q) {
    return {}
  }
  const params = new URLSearchParams(q)
  return {
    code: params.get('code') ?? undefined,
    state: params.get('state') ?? undefined,
    error: params.get('error') ?? undefined,
    error_description: params.get('error_description') ?? undefined,
  }
}
