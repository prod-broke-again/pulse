/**
 * Incoming message tone (WebAudio), same idea as mobile `notificationFeedback`.
 */
export function playIncomingTone(enabled: boolean): void {
  if (!enabled || typeof window === 'undefined') {
    return
  }
  try {
    const ctx = new AudioContext()
    const osc = ctx.createOscillator()
    const gain = ctx.createGain()
    osc.connect(gain)
    gain.connect(ctx.destination)
    osc.frequency.value = 880
    osc.type = 'sine'
    gain.gain.value = 0.07
    const now = ctx.currentTime
    osc.start(now)
    osc.stop(now + 0.1)
  } catch {
    /* WebAudio may be blocked until user gesture */
  }
}

let notificationPermissionRequested = false

async function ensureNotificationPermission(): Promise<boolean> {
  if (typeof window === 'undefined' || typeof Notification === 'undefined') {
    return false
  }
  if (Notification.permission === 'granted') {
    return true
  }
  if (Notification.permission === 'denied') {
    return false
  }
  if (notificationPermissionRequested) {
    return false
  }
  notificationPermissionRequested = true
  const permission = await Notification.requestPermission()
  return permission === 'granted'
}

export function desktopSoundEnabled(): boolean {
  if (typeof localStorage === 'undefined') {
    return true
  }
  return localStorage.getItem('pulse-desktop-sound') !== '0'
}

export function setDesktopSoundEnabled(value: boolean): void {
  if (typeof localStorage === 'undefined') {
    return
  }
  localStorage.setItem('pulse-desktop-sound', value ? '1' : '0')
}

export async function showDesktopMessageNotification(params: {
  title: string
  body: string
  tag?: string
}): Promise<void> {
  const ok = await ensureNotificationPermission()
  if (!ok) {
    return
  }
  try {
    const n = new Notification(params.title, {
      body: params.body,
      tag: params.tag ?? 'pulse-message',
      silent: false,
    })
    n.onclick = () => {
      window.focus()
      n.close()
    }
  } catch {
    /* ignore */
  }
}
