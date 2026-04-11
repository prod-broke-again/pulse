import axios from 'axios'

/** SSO exchange = Pulse → IdP (token + profile); local/dev can take 30–40s+ */
const HTTP_TIMEOUT_MS = (() => {
  const n = Number(import.meta.env.VITE_HTTP_TIMEOUT_MS)
  return Number.isFinite(n) && n >= 5000 ? n : 90_000
})()

export const http = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL ?? 'https://pulse.test/api/v1',
  headers: {
    Accept: 'application/json',
    'Content-Type': 'application/json',
  },
  timeout: HTTP_TIMEOUT_MS,
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
