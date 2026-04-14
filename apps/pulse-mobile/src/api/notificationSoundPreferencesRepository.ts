import { http } from '../lib/http'
import type { NotificationSoundPrefs } from '../lib/notificationSoundPresets'
import type { PulseUser } from '../stores/authStore'

export async function fetchNotificationSoundPreferences(): Promise<{
  notification_sound_prefs: NotificationSoundPrefs
  available_presets: Record<string, { label: string }>
}> {
  const { data } = await http.get<{
    data: {
      notification_sound_prefs: NotificationSoundPrefs
      available_presets: Record<string, { label: string }>
    }
  }>('/user/notification-sound-preferences')
  return data.data
}

export async function patchNotificationSoundPreferences(
  body: Partial<{
    mute: boolean
    volume: number
    presets: Partial<NotificationSoundPrefs['presets']>
  }>,
): Promise<{ notification_sound_prefs: NotificationSoundPrefs; user: PulseUser }> {
  const { data } = await http.patch<{
    data: { notification_sound_prefs: NotificationSoundPrefs; user: PulseUser }
  }>('/user/notification-sound-preferences', body)
  return data.data
}
