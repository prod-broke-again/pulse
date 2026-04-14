export interface ApiUser {
  id: number
  name: string
  email: string
  /** Может отсутствовать в старом кэше localStorage. */
  avatar_url?: string | null
  roles: string[]
  source_ids: number[]
  department_ids: number[]
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
