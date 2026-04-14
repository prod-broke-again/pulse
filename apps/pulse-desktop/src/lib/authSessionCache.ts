import type { ApiUser } from '../types/dto/auth.types'

const USER_KEY = 'pulse-auth-user'

export function loadCachedUser(): ApiUser | null {
  try {
    const raw = localStorage.getItem(USER_KEY)
    if (!raw) {
      return null
    }
    const parsed = JSON.parse(raw) as ApiUser
    if (!parsed || typeof parsed.id !== 'number' || typeof parsed.name !== 'string') {
      return null
    }
    return parsed
  } catch {
    return null
  }
}

export function saveCachedUser(user: ApiUser): void {
  localStorage.setItem(USER_KEY, JSON.stringify(user))
}

export function clearCachedUser(): void {
  localStorage.removeItem(USER_KEY)
}
