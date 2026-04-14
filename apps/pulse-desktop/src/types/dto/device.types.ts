export interface DeviceToken {
  id: number
  token: string
  platform: 'ios' | 'android' | 'desktop' | 'web'
}

export interface DeviceTokenResponse {
  data: DeviceToken
}
