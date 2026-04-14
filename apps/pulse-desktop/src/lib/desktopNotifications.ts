import type { NotificationSoundPrefs } from './notificationSoundPresets'
import {
  mergeNotificationSoundPrefs,
  PRESET_PUBLIC_PATH,
  type NotificationPresetId,
} from './notificationSoundPresets'

const DEDUPE_MS = 2000
const lastNotified = new Map<string, number>()

export function shouldDedupeIncomingNotify(chatId: number, messageId: number): boolean {
  const key = `${chatId}:${messageId}`
  const now = Date.now()
  const prev = lastNotified.get(key)
  if (prev !== undefined && now - prev < DEDUPE_MS) {
    return true
  }
  lastNotified.set(key, now)
  if (lastNotified.size > 120) {
    for (const [k, t] of lastNotified) {
      if (now - t > DEDUPE_MS * 3) {
        lastNotified.delete(k)
      }
    }
  }
  return false
}

function originFromApiBase(): string {
  const base = import.meta.env.VITE_API_BASE_URL || ''
  try {
    return new URL(base).origin
  } catch {
    return typeof window !== 'undefined' ? window.location.origin : ''
  }
}

function presetUrl(preset: Exclude<NotificationPresetId, 'none'>): string {
  return `${originFromApiBase()}/${PRESET_PUBLIC_PATH[preset]}`
}

const audioElByUrl = new Map<string, HTMLAudioElement>()

const CUSTOM_KEY = 'pulse-desktop-custom-sound-dataurl'

export function getLocalCustomSoundDataUrl(): string | null {
  if (typeof localStorage === 'undefined') {
    return null
  }
  const v = localStorage.getItem(CUSTOM_KEY)
  return v && v.startsWith('data:') ? v : null
}

export function setLocalCustomSoundDataUrl(dataUrl: string | null): void {
  if (typeof localStorage === 'undefined') {
    return
  }
  if (!dataUrl) {
    localStorage.removeItem(CUSTOM_KEY)
    audioElByUrl.clear()
    return
  }
  localStorage.setItem(CUSTOM_KEY, dataUrl)
  audioElByUrl.clear()
}

/**
 * Plays preset or locally uploaded custom sound (custom overrides preset file).
 */
export function playIncomingToneFromPrefs(
  prefs: NotificationSoundPrefs,
  scenario: 'in_app' | 'background' | 'important',
): void {
  if (prefs.mute || typeof window === 'undefined') {
    return
  }
  const key =
    scenario === 'important'
      ? prefs.presets.important
      : scenario === 'background'
        ? prefs.presets.background
        : prefs.presets.in_app
  if (key === 'none') {
    return
  }

  const custom = getLocalCustomSoundDataUrl()
  const url = custom ?? presetUrl(key as Exclude<NotificationPresetId, 'none'>)

  try {
    let el = audioElByUrl.get(url)
    if (!el) {
      el = new Audio(url)
      audioElByUrl.set(url, el)
    }
    el.volume = prefs.volume
    el.currentTime = 0
    void el.play().catch(() => {
      /* autoplay policy */
    })
  } catch {
    /* ignore */
  }
}

/** @deprecated use playIncomingToneFromPrefs with server prefs */
export function playIncomingTone(_enabled: boolean): void {
  /* legacy no-op: real sound uses prefs */
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

export async function showDesktopMessageNotification(params: {
  title: string
  body: string
  tag?: string
  icon?: string | null
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
      ...(params.icon ? { icon: params.icon } : {}),
    })
    n.onclick = () => {
      window.focus()
      n.close()
    }
  } catch {
    /* ignore */
  }
}

export function resolveNotificationScenario(params: {
  isUrgent: boolean
}): 'in_app' | 'background' | 'important' {
  if (params.isUrgent) {
    return 'important'
  }
  const hidden = typeof document !== 'undefined' && document.visibilityState === 'hidden'
  const focused =
    typeof document !== 'undefined' && typeof document.hasFocus === 'function'
      ? document.hasFocus()
      : true
  if (hidden || !focused) {
    return 'background'
  }
  return 'in_app'
}

export function effectivePrefs(raw: unknown): NotificationSoundPrefs {
  return mergeNotificationSoundPrefs(raw)
}
