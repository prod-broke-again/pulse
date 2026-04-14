import type { NotificationSoundPrefs } from '../../lib/notificationSoundPresets'

export interface ApiUser {
  id: number
  name: string
  email: string
  /** Может отсутствовать в старом кэше localStorage. */
  avatar_url?: string | null
  roles: string[]
  source_ids: number[]
  department_ids: number[]
  /** С сервера; при отсутствии в кэше клиент подставляет дефолты. */
  notification_sound_prefs?: NotificationSoundPrefs | null
}

export interface LoginResponse {
  data: {
    token: string
    user: ApiUser
  }
}

export interface SsoExchangeResponse {
  data: {
    token: string
    user: ApiUser
  }
}

export interface MeResponse {
  data: ApiUser
}
