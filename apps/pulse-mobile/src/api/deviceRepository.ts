import { http } from '../lib/http'

export type DevicePlatform = 'ios' | 'android' | 'desktop' | 'web'

export async function registerDeviceToken(body: {
  token: string
  platform: DevicePlatform
}): Promise<{ id: number; token: string; platform: string }> {
  const res = await http.post<{ data: { id: number; token: string; platform: string } }>(
    '/devices/register-token',
    body,
  )
  return res.data.data
}

export async function unregisterDeviceToken(token: string): Promise<void> {
  await http.delete(`/devices/${encodeURIComponent(token)}`)
}
