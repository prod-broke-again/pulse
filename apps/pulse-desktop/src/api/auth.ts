import { api, setToken, clearToken } from './client'
import type { ApiUser, MeResponse, SsoExchangeResponse } from '../types/dto/auth.types'

export async function exchangeSso(payload: {
  code: string
  code_verifier: string
  state: string
  redirect_uri: string
  deviceName?: string
}): Promise<{ token: string; user: ApiUser }> {
  const response = await api.post<SsoExchangeResponse>('/auth/sso/exchange', {
    code: payload.code,
    code_verifier: payload.code_verifier,
    state: payload.state,
    redirect_uri: payload.redirect_uri,
    device_name: payload.deviceName ?? 'Pulse Desktop',
  })
  setToken(response.data.token)
  return response.data
}

/** Legacy / dev: exchange ACHPP ID access token for Pulse API token. */
export async function exchangeSsoAccessToken(accessToken: string, deviceName?: string): Promise<{ token: string; user: ApiUser }> {
  const response = await api.post<SsoExchangeResponse>('/auth/sso/exchange', {
    access_token: accessToken,
    device_name: deviceName ?? 'Pulse Desktop',
  })
  setToken(response.data.token)
  return response.data
}

export async function logout(): Promise<void> {
  try {
    await api.post('/auth/logout')
  } finally {
    clearToken()
  }
}

export async function getMe(): Promise<ApiUser> {
  const response = await api.get<MeResponse>('/auth/me')
  return response.data
}

export async function uploadAvatar(file: File): Promise<ApiUser> {
  const formData = new FormData()
  formData.append('avatar', file)
  const response = await api.postForm<MeResponse>('/auth/profile/avatar', formData)
  return response.data
}
