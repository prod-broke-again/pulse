# REST API v1 (desktop / mobile)

API для нативных desktop- и mobile-клиентов. Авторизация по токену (Laravel Sanctum).

**Базовый URL:** `https://your-app.com/api/v1`  
**Авторизация:** заголовок `Authorization: Bearer <token>`. Токен выдаётся после входа через ACHPP ID (`POST /auth/sso/exchange`) или, если не включён режим только SSO, через `POST /auth/login`.  
**Формат:** запросы и ответы — JSON (`Content-Type: application/json`).

Режим **только SSO** (`PULSE_SSO_ONLY=true` в Pulse): парольный `POST /auth/login` возвращает **403**; клиенты должны использовать OAuth2 + PKCE на стороне ACHPP ID, затем обменять access token IdP на Sanctum token через `POST /auth/sso/exchange`.

---

## Общий формат ответов

- **Успех:** тело в ключе `data`. Пагинированные списки дополнительно содержат `meta` и `links`.
- **Ошибка валидации (422):** `message`, `errors` (по полям), `code: "VALIDATION_ERROR"`.
- **401 Unauthorized:** `message: "Unauthenticated."`, `code: "UNAUTHENTICATED"`.
- **403 Forbidden:** `message`, `code: "FORBIDDEN"`.
- **404 Not Found:** `message: "Resource not found."`, `code: "NOT_FOUND"`.

---

## 1. Auth

### POST /auth/sso/exchange

Обмен учётных данных ACHPP ID (Passport) на локальный Sanctum token Pulse. Профиль подтягивается с IdP (`GET /api/v1/user` на стороне ID), пользователь связывается по `id_user_uuid` (email — только fallback при склейке).

Доступ только пользователям с ролями `admin` или `moderator` в Pulse. Если пользователь есть в ID, но в Pulse нет нужной роли — **403**.

**Режим A — уже есть access token IdP (редко, dev):** передайте `access_token`. Не передавайте `code` одновременно.

**Режим B — authorization code + PKCE (рекомендуется для SPA/mobile):** Pulse сам вызывает `POST {ACHPP_ID_BASE_URL}/oauth/token` с `client_id` из `ACHPP_ID_CLIENT_ID`, затем подтягивает профиль. Передайте:

| Поле           | Тип    | Обязательно | Описание |
|----------------|--------|-------------|----------|
| code           | string | да*         | Код из redirect IdP |
| code_verifier  | string | да*         | PKCE verifier |
| redirect_uri   | string | да*         | Должен совпадать с redirect в `/oauth/authorize` |
| state          | string | нет         | Тот же `state`, что при authorize (opaque) |
| access_token   | string | да*         | Альтернатива режиму B |
| device_name    | string | нет         | Имя Sanctum token |

\* Либо `access_token`, либо связка `code` + `code_verifier` + `redirect_uri`.

**Ответ 200:** тот же формат, что и у `POST /auth/login` (`data.token`, `data.user`).

**Ошибки:** **401** — невалидный/просроченный токен IdP или code; **403** — нет роли admin/moderator в Pulse.

---

### POST /auth/login

Логин по email/паролю (legacy). Доступ только пользователям с ролями `admin` или `moderator`. Отключается при `PULSE_SSO_ONLY=true` (**403**).

**Тело (JSON):**

| Поле         | Тип    | Обязательно | Описание                    |
|-------------|--------|-------------|-----------------------------|
| email       | string | да          | Email                       |
| password    | string | да          | Пароль                      |
| device_name | string | нет         | Имя устройства (для токена)|

**Ответ 200:**

```json
{
  "data": {
    "token": "1|abc...",
    "user": {
      "id": 1,
      "name": "Иван Модератор",
      "email": "mod@example.com",
      "avatar_url": null,
      "roles": ["moderator"],
      "source_ids": [1, 2],
      "department_ids": [1, 3]
    }
  }
}
```

**Ошибки:** 422 — неверный email/пароль; **403** — роль не admin/moderator или включён только SSO.

---

### POST /auth/logout

Отзыв текущего токена. Требует авторизацию.

**Ответ 200:**

```json
{
  "data": {
    "message": "Logged out successfully."
  }
}
```

---

### GET /auth/me

Профиль текущего пользователя. Требует авторизацию.

**Ответ 200:**

```json
{
  "data": {
    "id": 1,
    "name": "Иван Модератор",
    "email": "mod@example.com",
    "avatar_url": null,
    "roles": ["moderator"],
    "source_ids": [1, 2],
    "department_ids": [1, 3]
  }
}
```

---

## 1b. Webhooks от ACHPP ID (сервер — сервер)

Не для мобильного клиента. Pulse принимает подписанные запросы от IdP (опционально, `PULSE_ID_WEBHOOKS_ENABLED=true`):

- `POST /api/webhooks/id/user-revoked` — инвалидация всех Sanctum token по `id_user_uuid`.
- `POST /api/webhooks/id/user-updated` — upsert полей профиля по `id_user_uuid`.

Подпись: `X-Pulse-Timestamp`, `X-Pulse-Signature` = hex(HMAC-SHA256(secret, timestamp + '.' + raw_body)), защита от replay по окну времени. Опционально `X-Idempotency-Key` для повторных доставок.

---

## 2. Чаты

### GET /chats

Список чатов с пагинацией. Права: admin видит все, moderator — только чаты своих source (и опционально department).

**Query-параметры:**

| Параметр     | Тип    | Описание                                      |
|-------------|--------|-----------------------------------------------|
| tab         | string | `my` \| `unassigned` \| `all` (по умолчанию) |
| source_id   | int    | Фильтр по источнику                           |
| department_id | int  | Фильтр по отделу                              |
| search      | string | Поиск по тексту/метаданным                    |
| status      | string | `open` (new+active) \| `closed` \| `all` (все статусы) |
| channels[]  | string | Повторяемый параметр: `tg`, `vk`, `web` — фильтр по типу источника |
| per_page    | int    | Записей на странице (1–100, по умолчанию 20) |
| page        | int    | Номер страницы                                |

**Ответ 200:** пагинированная коллекция чатов. Дополнительные поля для мобильных клиентов:

| Поле | Описание |
|------|----------|
| category_code / category_label | Категория из отдела (enum: support, registration, tech, ethics, other) |
| ai_enabled, ai_badge | Флаг AI-подсказок с отдела |
| channel, channel_label | Канал (`tg` \| `vk` \| `web`) и подпись |
| unread_count | Число непрочитанных сообщений клиента для **текущего** модератора (курсор `chat_user_read_states`) |
| last_message_preview, last_message_at | Превью и время последнего сообщения |

```json
{
  "data": [
    {
      "id": 1,
      "source_id": 1,
      "department_id": 1,
      "external_user_id": "vk_123",
      "user_metadata": { "name": "Клиент" },
      "status": "active",
      "assigned_to": 2,
      "category_code": "support",
      "category_label": "Поддержка",
      "ai_enabled": true,
      "ai_badge": true,
      "channel": "vk",
      "channel_label": "VK",
      "unread_count": 2,
      "last_message_preview": "…",
      "last_message_at": "2026-02-17T10:05:00.000000Z",
      "source": { "id": 1, "name": "ВКонтакте", "type": "vk" },
      "department": { "id": 1, "name": "Поддержка", "category": "support", "ai_enabled": true },
      "assignee": { "id": 2, "name": "Модератор" },
      "latest_message": { "id": 10, "text": "...", "sender_type": "client", "created_at": "..." },
      "is_urgent": false,
      "created_at": "2026-02-17T10:00:00.000000Z",
      "updated_at": "2026-02-17T10:05:00.000000Z"
    }
  ],
  "meta": { "current_page": 1, "per_page": 20, "total": 42 },
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." }
}
```

---

### GET /chats/tab-counts

Число чатов по вкладкам (`my`, `unassigned`, `all`) с теми же фильтрами, что и `GET /chats`, но **без** параметра `tab` (и без пагинации).

**Ответ 200:**

```json
{
  "data": {
    "my": 3,
    "unassigned": 2,
    "all": 12
  }
}
```

---

### GET /chats/{id}

Один чат (как в списке, с `unread_count` для текущего пользователя). Требует право `view`.

---

### POST /chats/{id}/read

Отметить прочитанным до указанного сообщения (курсор для **текущего** модератора). Требует право `view`.

**Тело (JSON):**

| Поле | Тип | Описание |
|------|-----|----------|
| last_message_id | int | id сообщения в этом чате; помечаются клиентские сообщения с id ≤ этого значения |

**Ответ 200:** `{ "data": { "ok": true } }`

---

### POST /chats/{id}/assign-me

Назначить чат на текущего пользователя. Требует право `update` на чат.

**Ответ 200:** объект чата в `data` (как в списке).

---

### POST /chats/{id}/close

Закрыть чат (status → closed). Требует право `update` на чат.

**Ответ 200:** объект чата в `data`.

**Ошибки:** 404 — чат не найден или нет доступа.

---

### POST /chats/{id}/typing

Сообщить, что текущий модератор печатает (для индикатора «печатает» у других подписчиков канала). Требует право `view` на чат.

**Ответ 200:**

```json
{
  "data": { "message": "OK" }
}
```

Событие `UserTyping` транслируется в канал `chat.{chatId}` (см. Realtime ниже).

---

## 3. Сообщения

### GET /chats/{id}/messages

Сообщения чата. Курсорная пагинация: сначала последние, «старые» — по `before_id`. Требует право `view` на чат.

**Query-параметры:**

| Параметр  | Тип | Описание                              |
|----------|-----|---------------------------------------|
| before_id| int | Вернуть сообщения с id **меньше** `before_id` (порядок: от старых к новым в блоке) |
| after_id | int | Вернуть сообщения с id **больше** `after_id` (для догрузки полного объекта после realtime/W-события; не смешивать с `before_id` в одном запросе) |
| limit    | int | Макс. записей (по умолчанию 50)       |
| per_page | int | Синоним limit                         |

Если переданы и `before_id`, и `after_id`, приоритет у **`after_id`** (сервер игнорирует `before_id`).

**Ответ 200:**

```json
{
  "data": [
    {
      "id": 5,
      "chat_id": 1,
      "sender_id": null,
      "sender_type": "client",
      "text": "Здравствуйте",
      "payload": {},
      "attachments": [],
      "is_read": true,
      "reply_to": { "id": 4, "text": "Короткое превью…", "sender_type": "client" },
      "created_at": "2026-02-17T10:00:00.000000Z",
      "updated_at": "2026-02-17T10:00:00.000000Z"
    }
  ]
}
```

`attachments` — массив объектов с полями `id`, `name`, `mime_type`, `size`, `url`. Поле `reply_markup` — плоский список кнопок-ссылок `{ text, url }` или `null`. Поле `reply_to` присутствует, если сообщение — ответ на другое.

Входящие сообщения от клиентов со всех каналов (`vk`, `max`, `tg`, `web` и др.) приходят в том же JSON-формате: текст, `payload`, вложения и `reply_markup` (если есть) формируются на стороне сервера при парсинге webhook/виджета.

---

### POST /chats/{id}/send

Отправить сообщение от имени модератора. Требует право `update` на чат.

**Тело (JSON):**

| Поле             | Тип     | Обязательно      | Описание |
|------------------|--------|-------------------|----------|
| text             | string | да*               | Текст сообщения (может быть пустой строкой, если есть вложения или кнопки) |
| attachments      | array  | нет               | Массив путей из `POST /uploads` |
| reply_markup     | array  | нет               | Плоский массив `{ text, url }` — быстрые ссылки-кнопки (до 40 символов на `text`) |
| client_message_id| string | нет               | UUID для идемпотентности (повтор запроса вернёт то же сообщение) |
| reply_to_message_id | int | нет            | Ответ на сообщение в этом же чате |

\* Обязательно **хотя бы одно** из: непустой `text`, непустой `attachments`, непустой `reply_markup`. Разрешена отправка **только** `reply_markup` без текста и без вложений (кнопки без сообщения).

**Ответ 201:** созданное сообщение в `data` (структура как в GET messages).

**Идемпотентность:** при повторной отправке с тем же `client_message_id` возвращается уже созданное сообщение без дублирования в БД (**HTTP 200**, тело как у успешного GET одного сообщения).

---

### GET /chats/{id}/ai/summary

Краткое AI-резюме и метка интента по последним сообщениям чата. Требует право `view`.

**Ответ 200:**

```json
{
  "data": {
    "summary": "…",
    "intent_tag": "Регистрация"
  }
}
```

---

### GET /chats/{id}/ai/suggestions

Варианты быстрых ответов (массив `replies` с `id` и `text`). Требует право `view`.

---

## 4. Загрузка файлов

### POST /uploads

Загрузить файл (для вложений в сообщения). Требует авторизацию.

**Тело:** `multipart/form-data`, поле `file` — файл. Допустимые MIME включают изображения, PDF, типичные аудио (`audio/aac`, `audio/webm`, `audio/mpeg`, и т.д.) — см. валидацию в `UploadFileRequest`.

**Ответ 201:**

```json
{
  "data": {
    "path": "uploads/pending/1/uuid.png",
    "original_name": "screenshot.png",
    "mime_type": "image/png",
    "size": 12345
  }
}
```

Значение `path` передавать в массиве `attachments` при `POST /chats/{id}/send`.

---

## 5. Устройства (FCM)

### POST /devices/register-token

Зарегистрировать или обновить FCM-токен для push-уведомлений.

**Тело (JSON):**

| Поле    | Тип   | Обязательно | Описание |
|---------|-------|-------------|----------|
| token   | string| да          | FCM token (до 500 символов) |
| platform| string| да          | `ios` \| `android` \| `desktop` \| `web` |

**Ответ 201:**

```json
{
  "data": {
    "id": 1,
    "token": "fcm_...",
    "platform": "android"
  }
}
```

---

### DELETE /devices/{token}

Удалить регистрацию устройства по токену. Удаляются только токены текущего пользователя.

**Ответ 200:**

```json
{
  "data": {
    "message": "Device token removed."
  }
}
```

---

## 6. Шаблоны ответов (canned responses)

### GET /canned-responses

Список активных шаблонов. Требует авторизацию.

**Query-параметры:**

| Параметр   | Тип   | Описание |
|-----------|-------|----------|
| source_id | int   | Фильтр: шаблоны для источника или общие (source_id = null) |
| q         | string| Поиск по code, title, text |

**Ответ 200:**

```json
{
  "data": [
    {
      "id": 1,
      "source_id": 1,
      "code": "greet",
      "title": "Приветствие",
      "text": "Здравствуйте! Чем могу помочь?",
      "is_active": true
    }
  ]
}
```

---

## Realtime (Reverb)

Для typing indicators и новых сообщений подключайтесь к Reverb (Laravel Echo).

- **Канал:** `chat.{chatId}` (private) — авторизация через `channels.php` (роль admin/moderator и право view на чат).
- **События:** `NewChatMessage`, `UserTyping` (при вызове POST /chats/{id}/typing или по вебхуку).

См. также раздел «Broadcast channels» в [api.md](api.md).
