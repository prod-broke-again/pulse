/** Must match config/notification_sounds.php preset keys. */
export type NotificationPresetId =
  | 'none'
  | 'notification_simple_01'
  | 'notification_simple_02'
  | 'notification_high_intensity'
  | 'notification_decorative_01'
  | 'alert_simple'

export type NotificationSoundPrefs = {
  mute: boolean
  volume: number
  presets: {
    in_chat: NotificationPresetId
    in_app: NotificationPresetId
    background: NotificationPresetId
    important: NotificationPresetId
  }
}

export const PRESET_PUBLIC_PATH: Record<Exclude<NotificationPresetId, 'none'>, string> = {
  notification_simple_01: 'sounds/notifications/notification_simple-01.wav',
  notification_simple_02: 'sounds/notifications/notification_simple-02.wav',
  notification_high_intensity: 'sounds/notifications/notification_high-intensity.wav',
  notification_decorative_01: 'sounds/notifications/notification_decorative-01.wav',
  alert_simple: 'sounds/notifications/alert_simple.wav',
}

export const PRESET_LABELS: Record<NotificationPresetId, string> = {
  none: 'Без звука',
  notification_simple_01: 'Simple 01 (в приложении)',
  notification_simple_02: 'Simple 02 (фон)',
  notification_high_intensity: 'High intensity (важное)',
  notification_decorative_01: 'Decorative 01',
  alert_simple: 'Alert simple',
}

export const DEFAULT_NOTIFICATION_SOUND_PREFS: NotificationSoundPrefs = {
  mute: false,
  volume: 1,
  presets: {
    in_chat: 'none',
    in_app: 'notification_simple_01',
    background: 'notification_simple_02',
    important: 'notification_high_intensity',
  },
}

export function mergeNotificationSoundPrefs(raw: unknown): NotificationSoundPrefs {
  const d = DEFAULT_NOTIFICATION_SOUND_PREFS
  if (!raw || typeof raw !== 'object') {
    return { ...d, presets: { ...d.presets } }
  }
  const o = raw as Record<string, unknown>
  const validIds = new Set(Object.keys(PRESET_PUBLIC_PATH).concat(['none']))
  const pick = (v: unknown, fallback: NotificationPresetId): NotificationPresetId =>
    typeof v === 'string' && validIds.has(v) ? (v as NotificationPresetId) : fallback

  const presetsIn = (o.presets && typeof o.presets === 'object' ? o.presets : {}) as Record<
    string,
    unknown
  >
  return {
    mute: typeof o.mute === 'boolean' ? o.mute : d.mute,
    volume:
      typeof o.volume === 'number' && o.volume >= 0 && o.volume <= 1 ? o.volume : d.volume,
    presets: {
      in_chat: pick(presetsIn.in_chat, d.presets.in_chat),
      in_app: pick(presetsIn.in_app, d.presets.in_app),
      background: pick(presetsIn.background, d.presets.background),
      important: pick(presetsIn.important, d.presets.important),
    },
  }
}
