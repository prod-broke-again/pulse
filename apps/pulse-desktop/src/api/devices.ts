import { api } from './client'
import type { DeviceTokenResponse, DeviceToken } from '../types/dto/device.types'

export async function registerDeviceToken(token: string, platform: DeviceToken['platform']): Promise<DeviceToken> {
  const response = await api.post<DeviceTokenResponse>('/devices/register-token', {
    token,
    platform,
  })
  return response.data
}

export async function removeDeviceToken(token: string): Promise<void> {
  await api.delete(`/devices/${token}`)
}
