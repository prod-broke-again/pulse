import type { ApiUser } from '../types/dto/auth.types'
import {
  fetchModeratorPresenceMe,
  sendModeratorPresenceHeartbeat,
  toggleModeratorPresence,
} from '../api/presence'

const HEARTBEAT_MS = 30_000

let heartbeatTimer: ReturnType<typeof setInterval> | null = null
let cancelled = false
let initPromise: Promise<void> | null = null

export function isModeratorStaffUser(user: ApiUser | null): boolean {
  if (!user?.roles?.length) {
    return false
  }
  return user.roles.includes('admin') || user.roles.includes('moderator')
}

/**
 * Пока открыт десктоп, считаем модератора/админа «на смене» и шлём heartbeat — иначе
 * сервер не видит онлайн и шлёт гостям автоответ «модераторы не в сети».
 */
export function startModeratorPresenceForDesktop(): void {
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

export function stopModeratorPresenceForDesktop(): void {
  cancelled = true
  if (heartbeatTimer !== null) {
    clearInterval(heartbeatTimer)
    heartbeatTimer = null
  }
}
