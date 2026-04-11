/// <reference types="vite/client" />

interface ImportMetaEnv {
  readonly VITE_API_BASE_URL?: string
  readonly VITE_API_ORIGIN?: string
  readonly VITE_REVERB_APP_KEY?: string
  readonly VITE_REVERB_HOST?: string
  readonly VITE_REVERB_PORT?: string
  readonly VITE_REVERB_SCHEME?: 'http' | 'https'
  /** When true, routes require api-token (localStorage) except /login and /auth/callback */
  readonly VITE_REQUIRE_AUTH?: string
  readonly VITE_ACHPP_ID_BASE_URL?: string
  readonly VITE_ACHPP_ID_CLIENT_ID?: string
  readonly VITE_ACHPP_ID_REDIRECT_URI?: string
  /** OAuth scopes for IdP authorize (default `*` in app; IdP may map to allowed scopes) */
  readonly VITE_ACHPP_ID_SCOPE?: string
}

interface ImportMeta {
  readonly env: ImportMetaEnv
}
