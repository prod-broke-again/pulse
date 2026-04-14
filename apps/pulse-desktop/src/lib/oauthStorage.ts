const KEYS = {
  pkceVerifier: 'pulse_oauth_pkce_verifier',
  oauthState: 'pulse_oauth_state_expected',
} as const

function setItem(key: string, value: string): void {
  localStorage.setItem(key, value)
}

function takeItem(key: string): string | null {
  const v = localStorage.getItem(key)
  if (v !== null) {
    localStorage.removeItem(key)
  }
  return v
}

export async function storePkceVerifier(value: string): Promise<void> {
  setItem(KEYS.pkceVerifier, value)
}

/** Returns and removes one-time verifier. */
export async function takePkceVerifier(): Promise<string | null> {
  return takeItem(KEYS.pkceVerifier)
}

export async function storeExpectedOAuthState(value: string): Promise<void> {
  setItem(KEYS.oauthState, value)
}

export async function takeExpectedOAuthState(): Promise<string | null> {
  return takeItem(KEYS.oauthState)
}
