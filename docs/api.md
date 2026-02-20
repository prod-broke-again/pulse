# Pulse API Documentation

Machine-readable spec: [openapi.yaml](openapi.yaml) (OpenAPI 3.0).

## Overview

- **Base URL:** Your app URL (e.g. `https://pulse.example.com`).
- **Format:** Request/response JSON where applicable.

**Два контура API:**

1. **REST API v1** — для desktop/mobile клиентов: авторизация по токену (Sanctum), чаты, сообщения, загрузки, FCM, шаблоны ответов. Полное описание: **[api-v1.md](api-v1.md)**.
2. **Webhooks и виджет** — входящие вебхуки (VK, Telegram), виджет для сайта; auth по payload/секрету или по токену сессии виджета.

---

## Webhooks (inbound)

Used to receive messages from VK and Telegram. CSRF is disabled for `webhook/*`.

### POST /webhook/vk/{sourceId}

Accepts VK Callback API payload for the given source.

**Path parameters:**

| Name      | Type | Description                    |
|-----------|------|--------------------------------|
| `sourceId`| int  | ID of the source (table `sources`) |

**Request body:** JSON object from VK (e.g. `type`, `object`, etc.).  
Required for processing: user identifier (e.g. `object.user_id` or `from.id`), message text. If `department_id` is missing, the first department of the source is used.

**Response:**

- `200`: `{"ok": true}` — payload accepted and queued for processing. Processing is asynchronous (job).
- `500`: `{"ok": false, "error": "Server error"}` — e.g. queue unavailable.

**Example:**

```http
POST /webhook/vk/1 HTTP/1.1
Content-Type: application/json

{"type":"message_new","object":{"user_id":123,"body":"Hello"}}
```

---

### POST /webhook/telegram/{sourceId}

Accepts Telegram Update payload for the given source.

**Path parameters:**

| Name      | Type | Description                    |
|-----------|------|--------------------------------|
| `sourceId`| int  | ID of the source (table `sources`) |

**Request body:** JSON object from Telegram (e.g. `update_id`, `message` with `from`, `text`).  
User id is taken from `message.from.id`, text from `message.text`. If `department_id` is missing, the first department of the source is used.

**Response:**

- `200`: `{"ok": true}` — payload accepted and queued for processing. Processing is asynchronous (job).
- `500`: `{"ok": false, "error": "Server error"}` — e.g. queue unavailable.

**Example:**

```http
POST /webhook/telegram/1 HTTP/1.1
Content-Type: application/json

{"update_id":1,"message":{"from":{"id":123,"first_name":"User"},"text":"Hello"}}
```

---

## Widget API (web source)

These endpoints are used by the embeddable widget script.

### POST /api/widget/session

Create or resume a chat session for website visitor.

Required JSON fields:
- `source_identifier` (string) - must point to a `web` source.
- `visitor_id` (string) - stable client-side id (stored in localStorage by widget).

Optional:
- `name`, `email`, `department_slug`, `meta`.

Response:
- `200`: `{ "ok": true, "chat_token": "...", "chat": { ... } }`

### GET /api/widget/messages?chat_token=...

Return latest messages for chat token.

### POST /api/widget/messages

Send visitor message:
- `chat_token` (string)
- `text` (string)
- `payload` (optional object)

---

## Auth (social login)

Used by the Filament login page for moderators.

### GET /auth/{provider}/redirect

Redirects the user to the OAuth provider. Supported: `vkontakte`, `telegram`.

**Path parameters:**

| Name      | Type  | Description     |
|-----------|-------|-----------------|
| `provider`| string| `vkontakte` or `telegram` |

**Response:** 302 redirect to the provider.

---

### GET /auth/{provider}/callback

OAuth callback. Exchanges code for token, finds or creates user and social account, logs in and redirects to `/admin`.

**Path parameters:**

| Name      | Type  | Description     |
|-----------|-------|-----------------|
| `provider`| string| `vkontakte` or `telegram` |

**Response:** 302 redirect to `/admin` or intended URL.

---

## Broadcast channels (Reverb)

For real-time updates, subscribe to private channels (e.g. with Laravel Echo). Authorisation is done via `routes/channels.php`.

| Channel            | Auth rule                          |
|--------------------|------------------------------------|
| `private-chat.{chatId}`   | User has role `admin` or `moderator` |
| `private-moderator.{userId}` | Authenticated user id equals `userId` |

**Events:**

- `App\Events\NewChatMessage` — payload: `chatId`, `messageId`, `text`
- `App\Events\ChatAssigned` — payload: `chatId`, `assignedToUserId`

---

## Data model (reference)

- **sources** — id, name, type (web|vk|tg), identifier, secret_key, settings (json)
- **departments** — id, source_id, name, slug, is_active
- **chats** — id, source_id, department_id, external_user_id, user_metadata (json), status (new|active|closed), assigned_to (user id)
- **messages** — id, chat_id, external_message_id (nullable, unique with chat_id for idempotency), sender_id, sender_type (client|moderator|system), text, payload (json), is_read
- **roles** — Spatie: admin, moderator (guard web)
