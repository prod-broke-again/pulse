# Pulse API Documentation

Machine-readable spec: [openapi.yaml](openapi.yaml) (OpenAPI 3.0).

## Overview

- **Base URL:** Your app URL (e.g. `https://pulse.example.com`).
- **Auth:** Webhooks do not use HTTP auth; validation is done by payload structure and optional secret in source settings.
- **Format:** Request/response JSON where applicable.

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

- `200`: `{"ok": true}`
- `422`: `{"ok": false, "error": "message"}`

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

- `200`: `{"ok": true}`
- `422`: `{"ok": false, "error": "message"}`

**Example:**

```http
POST /webhook/telegram/1 HTTP/1.1
Content-Type: application/json

{"update_id":1,"message":{"from":{"id":123,"first_name":"User"},"text":"Hello"}}
```

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
- **messages** — id, chat_id, sender_id, sender_type (client|moderator|system), text, payload (json), is_read
- **roles** — Spatie: admin, moderator (guard web)
