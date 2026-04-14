import { api } from './client'
import type { ApiUser } from '../types/dto/auth.types'
import type { NotificationSoundPrefs } from '../lib/notificationSoundPresets'

type PrefsResponse = {
  data: {
    notification_sound_prefs: NotificationSoundPrefs
    available_presets: Record<string, { label: string }>
  }
}

export async function fetchNotificationSoundPreferences(): Promise<{
  notification_sound_prefs: NotificationSoundPrefs
  available_presets: Record<string, { label: string }>
}> {
  const res = await api.get<PrefsResponse>('/user/notification-sound-preferences')
  return res.data
}

export async function patchNotificationSoundPreferences(
  body: Partial<{
    mute: boolean
    volume: number
    presets: Partial<NotificationSoundPrefs['presets']>
  }>,
): Promise<{ notification_sound_prefs: NotificationSoundPrefs; user: ApiUser }> {
  const res = await api.patch<{
    data: { notification_sound_prefs: NotificationSoundPrefs; user: ApiUser }
  }>('/user/notification-sound-preferences', body)
  return res.data
}
