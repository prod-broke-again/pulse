# Pulse — Omnichannel Support Platform

Support platform for multiple channels (Web, VK, Telegram) with department routing, moderator panel (Filament v4), and real-time updates (Laravel Reverb).

## Stack

- **PHP 8.4** — Property hooks, asymmetric visibility, constructor promotion
- **Laravel 12** — Framework
- **PostgreSQL 16+** — Database (jsonb)
- **Redis** — Cache, queue, broadcasting
- **Laravel Reverb** — WebSockets
- **Filament v4** — Admin panel for moderators
- **Spatie Laravel Permission** — Roles: `admin`, `moderator`
- **Laravel Socialite** — VK & Telegram login for moderators
- **DDD** — Domains, Application, Infrastructure, Interfaces

## Requirements

- PHP 8.4 (extensions: pdo_pgsql, redis, intl, zip)
- Composer 2
- Node.js & npm (for frontend)
- PostgreSQL 16+
- Redis (optional but recommended for queue/cache/broadcasting)

## Install (local)

```bash
composer install --ignore-platform-req=ext-zip  # if zip ext missing
cp .env.example .env
php artisan key:generate
# Set DB_* (pgsql), REDIS_*, REVERB_* in .env
php artisan migrate
php artisan db:seed
npm install && npm run build
```

## Docker

```bash
docker compose up -d
# App: http://localhost:8080
# Reverb WS: port 8081
```

Services: `app` (PHP-FPM), `nginx`, `postgres`, `redis`, `reverb`. Env is preconfigured in `docker-compose.yml`.

## Environment

See `.env.example`. Main variables:

| Variable | Description |
|----------|-------------|
| `DB_CONNECTION` | `pgsql` (required) |
| `CACHE_STORE` / `QUEUE_CONNECTION` | `redis` recommended |
| `BROADCAST_CONNECTION` | `reverb` |
| `REVERB_*` | Reverb app id/key/secret/host/port |
| `VKONTAKTE_*` | VK OAuth (moderator login) |
| `TELEGRAM_*` | Telegram bot (moderator login) |

## Roles (Spatie)

- **admin** — Full access; can manage roles and users in Filament.
- **moderator** — Access to Filament panel (chats, messages, sources, departments).

Only users with role `admin` or `moderator` can open `/admin`. Assign roles in **Admin → Settings → Users** (or via `php artisan tinker`: `User::find(1)->assignRole('admin')`).

Seed roles: `php artisan db:seed --class=RolesAndPermissionsSeeder`.

## App entry points

| Path | Description |
|------|-------------|
| `/` | Public home |
| `/dashboard` | User dashboard (auth) |
| `/admin` | Filament panel (admin/moderator) |
| `/settings/*` | Profile, password, 2FA |
| `/auth/{provider}/redirect` | Social login redirect (VK, telegram) |
| `/auth/{provider}/callback` | Social login callback |

## Webhooks (inbound)

| Method | Path | Description |
|--------|------|-------------|
| POST | `/webhook/vk/{sourceId}` | VK callback payload |
| POST | `/webhook/telegram/{sourceId}` | Telegram update payload |

`sourceId` is the internal source ID from the `sources` table. Payload is validated by the messenger provider; missing `department_id` falls back to the first department of the source. See [API documentation](docs/api.md) and [OpenAPI 3 spec](docs/openapi.yaml).

## Real-time

- Events: `App\Events\NewChatMessage`, `App\Events\ChatAssigned`
- Channels: `private-chat.{chatId}`, `private-moderator.{userId}`
- Authorization: `chat.*` — user must have role admin or moderator; `moderator.*` — own user id

Run Reverb: `php artisan reverb:start`.

## Project structure (DDD)

```
app/
  Domains/          # Entities, value objects, repository interfaces, messenger interface
  Application/      # Actions, DTOs, use cases
  Infrastructure/   # Eloquent models, repositories, VK/Telegram clients, bindings
  Interfaces/       # (optional) API/UI boundaries
  Filament/         # Filament resources (Sources, Departments, Chats, Messages, Users, Roles)
```

## License

MIT.
