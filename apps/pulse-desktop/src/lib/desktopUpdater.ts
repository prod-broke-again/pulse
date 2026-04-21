const UPDATE_CHECK_MIN_INTERVAL_MS = 15 * 60 * 1000

export type DesktopUpdateStatus = {
  level: 'info' | 'ready' | 'error'
  code: 'checking' | 'available' | 'not-available' | 'downloaded' | 'error'
  message: string
  version?: string
  releaseNotes?: string
}

let lastUpdateCheckAt = 0

export function canUseDesktopUpdater(): boolean {
  return typeof window !== 'undefined' && typeof window.desktopUpdater !== 'undefined'
}

export function onDesktopUpdaterStatus(listener: (payload: DesktopUpdateStatus) => void): () => void {
  if (!canUseDesktopUpdater()) {
    return () => {}
  }
  return window.desktopUpdater!.onStatus(listener)
}

export async function maybeCheckDesktopUpdate(force = false): Promise<boolean> {
  if (!canUseDesktopUpdater()) {
    return false
  }
  const now = Date.now()
  if (!force && now - lastUpdateCheckAt < UPDATE_CHECK_MIN_INTERVAL_MS) {
    return false
  }
  lastUpdateCheckAt = now
  await window.desktopUpdater!.checkNow()
  return true
}

export async function installDesktopUpdateNow(): Promise<void> {
  if (!canUseDesktopUpdater()) {
    return
  }
  await window.desktopUpdater!.installNow()
}
