import type { ApiError } from '../types/dto/api.types'
import { clearCachedUser } from '../lib/authSessionCache'

const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'https://pulse.test/api/v1'

let authToken: string | null = null

export function setToken(token: string | null): void {
  authToken = token
  if (token) {
    localStorage.setItem('api-token', token)
  } else {
    localStorage.removeItem('api-token')
  }
}

export function getToken(): string | null {
  if (!authToken) {
    authToken = localStorage.getItem('api-token')
  }
  return authToken
}

export function clearToken(): void {
  authToken = null
  localStorage.removeItem('api-token')
  clearCachedUser()
}

export class ApiRequestError extends Error {
  status: number
  errors: Record<string, string[]>
  code: string

  constructor(status: number, data: ApiError) {
    super(data.message || 'Request failed')
    this.status = status
    this.errors = data.errors ?? {}
    this.code = data.code ?? 'UNKNOWN'
  }
}

async function request<T>(
  method: string,
  path: string,
  options: {
    body?: Record<string, unknown> | FormData
    params?: Record<string, string | number | (string | number)[] | undefined | null>
  } = {},
): Promise<T> {
  const url = new URL(`${API_BASE_URL}${path}`)

  if (options.params) {
    for (const [key, value] of Object.entries(options.params)) {
      if (value === undefined || value === null) {
        continue
      }
      if (Array.isArray(value)) {
        for (const item of value) {
          if (item === undefined || item === null || item === '') {
            continue
          }
          url.searchParams.append(`${key}[]`, String(item))
        }
        continue
      }
      if (value !== '') {
        url.searchParams.set(key, String(value))
      }
    }
  }

  const headers: Record<string, string> = {
    Accept: 'application/json',
  }

  const token = getToken()
  if (token) {
    headers.Authorization = `Bearer ${token}`
  }

  let bodyToSend: BodyInit | undefined
  if (options.body instanceof FormData) {
    bodyToSend = options.body
  } else if (options.body) {
    headers['Content-Type'] = 'application/json'
    bodyToSend = JSON.stringify(options.body)
  }

  const response = await fetch(url.toString(), {
    method,
    headers,
    body: bodyToSend,
  })

  if (response.status === 401) {
    clearToken()
    throw new ApiRequestError(401, { message: 'Unauthenticated', code: 'UNAUTHENTICATED' })
  }

  const json = await response.json()

  if (!response.ok) {
    throw new ApiRequestError(response.status, json)
  }

  return json
}

export const api = {
  get: <T>(path: string, params?: Record<string, string | number | (string | number)[] | undefined | null>) =>
    request<T>('GET', path, { params }),

  post: <T>(path: string, body?: Record<string, unknown>) =>
    request<T>('POST', path, { body }),

  patch: <T>(path: string, body?: Record<string, unknown>) =>
    request<T>('PATCH', path, { body }),

  postForm: <T>(path: string, body: FormData) =>
    request<T>('POST', path, { body }),

  delete: <T>(path: string) =>
    request<T>('DELETE', path),
}
