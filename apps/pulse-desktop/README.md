# Pulse Desktop

Десктоп-клиент для модераторов Pulse (АЧПП): инбокс обращений, тред сообщений, назначение и **перехват чатов** (takeover), смена отдела, синхронизация истории с мессенджером, OAuth через ACHPP ID в окне Electron.

Стек: **Vue 3**, **TypeScript**, **Vite**, **Electron** (main/preload/renderer), **Pinia**, **Laravel Echo** + Reverb (`private-chat.*`, `private-moderator.*`), опционально **Capacitor** для мобильных оболочек (android/ios в репозитории).

Код живёт в монорепозитории Pulse: `apps/pulse-desktop` — не отдельный submodule.

## Требования

- Node.js 18+
- Для сборки EXE/установщика: зависимости `electron-builder` (см. `electron-builder.json5`)

## Установка и запуск

Из **корня** репозитория Pulse (рекомендуется — workspaces):

```bash
npm install
npm run desktop:dev
```

Или из этой папки:

```bash
cd apps/pulse-desktop
npm install
npm run dev
```

`npm run dev` поднимает Vite (по умолчанию порт из конфига; для Electron см. `vite.config.ts` / `electron`).

Сборка production (typecheck + vite + electron-builder):

```bash
# из корня
npm run desktop:build
```

## Переменные окружения

Скопируйте `.env.example` → `.env`. Все значения с префиксом `VITE_*` попадают в клиентский бандл.

| Переменная | Назначение |
|------------|------------|
| `VITE_API_BASE_URL` | База API Pulse, например `https://pulse.test/api/v1` |
| `VITE_REVERB_*` | Ключ, хост, порт, схема для WebSocket (Echo) |
| При необходимости | URL IdP, redirect для OAuth — по аналогии с мобильным клиентом и `.env.example` |

Секреты сервера в `.env` клиента не кладутся.

## Поведение в сети

- Очередь исходящих при офлайне (Electron + локальное хранилище) — см. `messageStore` / outbox.
- При смене владельца чата по WebSocket обновляются список и шапка треда; поле ввода блокируется, если чат назначен **другому** модератору, пока не нажато «Забрать себе».

## Структура (кратко)

```
electron/          # main, preload (IPC, OAuth callback)
src/               # Vue: App, inbox, chat, stores, api, lib/realtime
android/, ios/     # Capacitor (опционально)
```

## Лицензия

MIT (см. `LICENSE`).
