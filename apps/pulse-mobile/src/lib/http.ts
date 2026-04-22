import axios from 'axios'

/** SSO exchange = Pulse → IdP (token + profile); local/dev can take 30–40s+ */
const HTTP_TIMEOUT_MS = (() => {
  const n = Number(import.meta.env.VITE_HTTP_TIMEOUT_MS)
  return Number.isFinite(n) && n >= 5000 ? n : 90_000
})()

/** Laravel-friendly array params: `channels[]=tg&channels[]=vk` (matches desktop `client.ts`). */
function paramsSerializer(params: Record<string, unknown>): string {
  const search = new URLSearchParams()
  for (const [key, value] of Object.entries(params)) {
    if (value === undefined || value === null) {
      continue
    }
    if (Array.isArray(value)) {
      for (const item of value) {
        if (item === undefined || item === null || item === '') {
          continue
        }
        search.append(`${key}[]`, String(item))
      }
      continue
    }
    if (value !== '') {
      search.append(key, String(value))
    }
  }
  return search.toString()
}

export const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? 'https://pulse.test/api/v1',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  timeout: HTTP_TIMEOUT_MS,
  // eslint-disable-next-line @typescript-eslint/no-explicit-any -- axios passes plain params object
  paramsSerializer: (params: any) => paramsSerializer(params as Record<string, unknown>),
})

http.interceptors.request.use((config) => {
  const token = localStorage.getItem('api-token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

http.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error?.response?.status === 401) {
      localStorage.removeItem('api-token')
    }
    return Promise.reject(error)
  },
)
