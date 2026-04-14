import type { PulseUser } from '../stores/authStore'
import {
  fetchModeratorPresenceMe,
  sendModeratorPresenceHeartbeat,
  toggleModeratorPresence,
} from '../api/presenceRepository'

const HEARTBEAT_MS = 30_000

let heartbeatTimer: ReturnType<typeof setInterval> | null = null
let cancelled = false
let initPromise: Promise<void> | null = null

export function isModeratorStaffUser(user: PulseUser | null): boolean {
  if (!user?.roles?.length) {
    return false
  }
  return user.roles.includes('admin') || user.roles.includes('moderator')
}

/**
 * Пока приложение открыто, считаем модератора/админа «на смене» и шлём heartbeat —
 * иначе сервер не видит онлайн и уходит в автоответ «модераторы не в сети».
 */
export function startModeratorPresenceForMobile(): void {
  if (heartbeatTimer !== null || initPromise !== null) {
    return
  }
  cancelled = false
  initPromise = (async () => {
    try {
      await fetchModeratorPresenceMe()
      await toggleModeratorPresence(true)
      await sendModeratorPresenceHeartbeat()
    } catch (e) {
      console.warn('moderator presence: start failed', e)
      return
    }
    if (cancelled) {
      return
    }
    heartbeatTimer = setInterval(() => {
      void sendModeratorPresenceHeartbeat().catch(() => {
        /* best-effort */
      })
    }, HEARTBEAT_MS)
  })()
  void initPromise.finally(() => {
    initPromise = null
  })
}

export function stopModeratorPresenceForMobile(): void {
  cancelled = true
  if (heartbeatTimer !== null) {
    clearInterval(heartbeatTimer)
    heartbeatTimer = null
  }
}

/** После возврата из фона — сразу обновить heartbeat (TTL на сервере ~90 с). */
export function nudgeModeratorPresenceOnForeground(): void {
  if (heartbeatTimer === null) {
    return
  }
  void sendModeratorPresenceHeartbeat().catch(() => {
    /* best-effort */
  })
}
