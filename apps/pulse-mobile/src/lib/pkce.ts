/**
 * PKCE helpers (RFC 7636) for public OAuth clients in the browser.
 */

function base64UrlEncode(bytes: Uint8Array): string {
  let binary = ''
  for (let i = 0; i < bytes.byteLength; i++) {
    binary += String.fromCharCode(bytes[i]!)
  }
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '')
}

function randomVerifier(): string {
  const buf = new Uint8Array(32)
  crypto.getRandomValues(buf)
  return base64UrlEncode(buf)
}

export async function createPkcePair(): Promise<{ verifier: string; challenge: string }> {
  const verifier = randomVerifier()
  const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(verifier))
  const challenge = base64UrlEncode(new Uint8Array(digest))
  return { verifier, challenge }
}

export function randomState(): string {
  const buf = new Uint8Array(16)
  crypto.getRandomValues(buf)
  return base64UrlEncode(buf)
}
