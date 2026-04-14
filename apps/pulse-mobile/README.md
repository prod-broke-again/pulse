# Pulse Mobile

Клиент модератора Pulse для **iOS и Android** на **Vue 3**, **Vite**, **Capacitor 8**. Работает в монорепозитории Pulse (`apps/pulse-mobile`).

## Возможности

- Вкладки инбокса: мои / свободные / все, поиск, открытые/закрытые.
- Тред переписки, ответы, вложения, шаблоны и быстрые ссылки (reply markup).
- **Назначение на себя и перехват чата** у другого модератора; при назначении не вам поле ввода блокируется до перехвата.
- Realtime: `NewChatMessage`, `MessageRead`, `ChatAssigned` (обновление меты треда и инбокса).
- Вход через **ACHPP ID** (OAuth2 PKCE): веб — редирект; нативно — InApp Browser и deep link `pulseapp://auth/callback`.
- Уведомления, звук, вибрация (настройки в приложении).

## Скрипты

Рекомендуется запуск из **корня** репозитория Pulse (`workspaces: apps/*`):

| Команда (из корня) | Действие |
|--------------------|----------|
| `npm install` | Установка зависимостей всех workspace |
| `npm run mobile:dev` | Dev-сервер Vite |
| `npm run mobile:build` | Production-сборка |
| `npm run mobile:preview` | Превью сборки |
| `npm run mobile:assets` | Иконки/сплэш (`capacitor-assets`) |

Локально из `apps/pulse-mobile`:

```bash
npm run dev
npm run build
npm run cap:sync    # build + cap sync
npm run cap:android
npm run cap:ios
```

## Окружение

Скопируйте `.env.example` → `.env` или `.env.local`. Только **публичные** переменные с префиксом `VITE_*`:

- `VITE_API_BASE_URL` — API Pulse (`…/api/v1`)
- `VITE_API_ORIGIN` — origin для `broadcasting/auth` (без `/api/v1`)
- `VITE_REVERB_APP_KEY`, `VITE_REVERB_HOST`, `VITE_REVERB_PORT`, `VITE_REVERB_SCHEME`
- `VITE_ACHPP_ID_BASE_URL`, `VITE_ACHPP_ID_CLIENT_ID`, `VITE_ACHPP_ID_REDIRECT_URI`, `VITE_ACHPP_ID_SCOPE`
- Опционально `VITE_REQUIRE_AUTH=true` — без токена редирект на логин

Секреты IdP и сервера только на бэкенде; в APK/IPA не кладите приватные ключи.

## Вход (ACHPP ID → Pulse)

1. В консоли IdP — OAuth2 public client (PKCE), redirect для web и для нативного клиента (`pulseapp://auth/callback` — см. `src/lib/oauthConfig.ts`).
2. Обмен `code` на сессию Pulse: `POST /api/v1/auth/sso/exchange`; токен хранится как `api-token` (Preferences / localStorage в зависимости от платформы).
3. Ручной ввод access token — только для отладки.

## Структура (кратко)

```
src/
  api/           # HTTP к Pulse
  components/    # UI чата, инбокса
  pages/         # маршруты
  stores/        # Pinia (чат, инбокс, auth)
  lib/           # Echo, OAuth, id генераторы
android/, ios/   # нативные проекты Capacitor
```

## Лицензия

Совпадает с корневым проектом Pulse (см. корневой `README.md`).
