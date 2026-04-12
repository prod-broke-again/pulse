import { Capacitor } from '@capacitor/core'
import { PushNotifications } from '@capacitor/push-notifications'
import * as deviceApi from '../api/deviceRepository'

const DEVICE_TOKEN_PREF = 'pulse:devicePushToken'

function mapPlatform(): deviceApi.DevicePlatform {
  const p = Capacitor.getPlatform()
  if (p === 'ios') return 'ios'
  if (p === 'android') return 'android'
  return 'web'
}

/** Persist token for unregister (web + native). */
function rememberToken(token: string) {
  try {
    localStorage.setItem(DEVICE_TOKEN_PREF, token)
  } catch {
    /* ignore */
  }
}

export function getStoredDeviceToken(): string | null {
  try {
    return localStorage.getItem(DEVICE_TOKEN_PREF)
  } catch {
    return null
  }
}

export function clearStoredDeviceToken() {
  try {
    localStorage.removeItem(DEVICE_TOKEN_PREF)
  } catch {
    /* ignore */
  }
}

/**
 * Registers for push and POSTs the token to Pulse. Resolves when registration payload is sent.
 */
export async function registerPushWithBackend(): Promise<void> {
  const perm = await PushNotifications.requestPermissions()
  if (perm.receive !== 'granted') {
    throw new Error('Разрешение на уведомления не выдано')
  }

  await PushNotifications.removeAllListeners()

  await new Promise<void>((resolve, reject) => {
    let settled = false

    void PushNotifications.addListener('registration', (ev) => {
      const token = ev.value
      if (!token || settled) return
      settled = true
      void deviceApi
        .registerDeviceToken({ token, platform: mapPlatform() })
        .then(() => {
          rememberToken(token)
          resolve()
        })
        .catch(reject)
    })

    void PushNotifications.addListener('registrationError', (err) => {
      if (settled) return
      settled = true
      reject(new Error(err.error ?? 'Ошибка регистрации push'))
    })

    void PushNotifications.register().catch((e: unknown) => {
      if (settled) return
      settled = true
      reject(e instanceof Error ? e : new Error(String(e)))
    })

    window.setTimeout(() => {
      if (settled) return
      settled = true
      reject(new Error('Таймаут регистрации push'))
    }, 25_000)
  })
}

export async function unregisterPushFromBackend(): Promise<void> {
  const token = getStoredDeviceToken()
  if (!token) return
  try {
    await deviceApi.unregisterDeviceToken(token)
  } finally {
    clearStoredDeviceToken()
  }
}
