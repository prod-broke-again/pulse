import type { NotificationSoundPrefs } from '../../lib/notificationSoundPresets'

export interface ApiUser {
  id: number
  name: string
  email: string
  /** Может отсутствовать в старом кэше localStorage. */
  avatar_url?: string | null
  roles: string[]
  /** С сервера; при отсутствии в кэше — не полагаться на это поле. */
  is_admin?: boolean
  source_ids: number[]
  /** Optional list of accessible sources with names for UI labels. */
  sources?: Array<{ id: number; name: string }>
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
