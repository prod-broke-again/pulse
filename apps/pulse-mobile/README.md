# Pulse Mobile (Vue + Vite + Capacitor)

Mobile client scaffold for Pulse, running as a workspace app in this monorepo.

## Scripts

- `npm run dev -w pulse-mobile` - start local dev server
- `npm run build -w pulse-mobile` - production build
- `npm run preview -w pulse-mobile` - preview build
- `npm run cap:sync -w pulse-mobile` - build + sync Capacitor platforms
- `npm run cap:android -w pulse-mobile` - open Android project
- `npm run cap:ios -w pulse-mobile` - open iOS project

## Environment

Copy `.env.example` to `.env.local` (or create `.env` for CI) and set only public values:

- `VITE_API_BASE_URL`
- `VITE_API_ORIGIN`
- `VITE_REVERB_APP_KEY`
- `VITE_REVERB_HOST`
- `VITE_REVERB_PORT`
- `VITE_REVERB_SCHEME`
- `VITE_ACHPP_ID_BASE_URL`, `VITE_ACHPP_ID_CLIENT_ID`, `VITE_ACHPP_ID_REDIRECT_URI`, `VITE_ACHPP_ID_SCOPE` (OAuth PKCE к ACHPP ID)
- опционально `VITE_REQUIRE_AUTH=true` — без `api-token` редирект на `/login`

## Вход (ACHPP ID → Pulse)

1. Public OAuth2 клиент (Passport + PKCE): для web на `localhost` используется `http://localhost:5174/auth/callback`; для нативных сборок — `pulseapp://auth/callback` (см. `MOBILE_OAUTH_REDIRECT_URI` в `src/lib/oauthConfig.ts`).
2. Кнопка **«Войти через АЧПП ID»** вызывает `redirectToIdP()` — PKCE, `state` (base64 JSON с `platform` и nonce), web: `location.assign`, native: InApp Browser (`@capacitor/browser`).
3. `/auth/callback` вызывает Pulse `POST /api/v1/auth/sso/exchange` с `code`, `code_verifier`, `state`, `redirect_uri` (обмен code→token на стороне Pulse). Токен Sanctum в `localStorage['api-token']`.
4. Ручной ввод access token — только для разработки.

## Security note

All `VITE_*` variables are embedded into the frontend bundle at build time and can be extracted from APK/IPA/web assets.

- Never store secrets in frontend env files.
- Do not put API private keys, server keys, app secrets, signing secrets, or tokens here.
- Keep sensitive credentials on backend only.
