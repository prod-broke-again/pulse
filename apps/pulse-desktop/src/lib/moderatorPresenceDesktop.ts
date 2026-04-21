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
let heartbeatTickHook: (() => void | Promise<void>) | null = null

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
export function startModeratorPresenceForDesktop(opts?: {
  onHeartbeatTick?: () => void | Promise<void>
}): void {
  heartbeatTickHook = opts?.onHeartbeatTick ?? null
  if (heartbeatTimer !== null || initPromise !== null) {
    return
  }
  cancelled = false
  initPromise = (async () => {
    try {
      await fetchModeratorPresenceMe()
      await toggleModeratorPresence(true)
      await sendModeratorPresenceHeartbeat()
      await heartbeatTickHook?.()
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
      void heartbeatTickHook?.()
    }, HEARTBEAT_MS)
  })()
  void initPromise.finally(() => {
    initPromise = null
  })
}

export function stopModeratorPresenceForDesktop(): void {
  cancelled = true
  heartbeatTickHook = null
  if (heartbeatTimer !== null) {
    clearInterval(heartbeatTimer)
    heartbeatTimer = null
  }
}
