import type { NotificationSoundPrefs } from './notificationSoundPresets'
import {
  PRESET_PUBLIC_PATH,
  type NotificationPresetId,
} from './notificationSoundPresets'

const CUSTOM_KEY = 'pulse-mobile-custom-sound-dataurl'

function originFromApiBase(): string {
  const base = import.meta.env.VITE_API_BASE_URL ?? ''
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
    void el.play().catch(() => {})
  } catch {
    /* ignore */
  }
}

/** @deprecated — используйте playIncomingToneFromPrefs; оставлено для совместимости */
export function playIncomingTone(_enabled: boolean): void {}

export function vibrateIncoming(enabled: boolean): void {
  if (!enabled || typeof navigator === 'undefined' || typeof navigator.vibrate !== 'function') {
    return
  }
  try {
    navigator.vibrate(45)
  } catch {
    /* ignore */
  }
}
