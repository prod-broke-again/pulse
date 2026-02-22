# REST API v1 (desktop / mobile)

API для нативных desktop- и mobile-клиентов. Авторизация по токену (Laravel Sanctum).

**Базовый URL:** `https://your-app.com/api/v1`  
**Авторизация:** заголовок `Authorization: Bearer <token>`. Токен выдаётся при `POST /auth/login`.  
**Формат:** запросы и ответы — JSON (`Content-Type: application/json`).

---

## Общий формат ответов

- **Успех:** тело в ключе `data`. Пагинированные списки дополнительно содержат `meta` и `links`.
- **Ошибка валидации (422):** `message`, `errors` (по полям), `code: "VALIDATION_ERROR"`.
- **401 Unauthorized:** `message: "Unauthenticated."`, `code: "UNAUTHENTICATED"`.
- **403 Forbidden:** `message`, `code: "FORBIDDEN"`.
- **404 Not Found:** `message: "Resource not found."`, `code: "NOT_FOUND"`.

---

## 1. Auth

### POST /auth/login

Логин. Доступ только пользователям с ролями `admin` или `moderator`.

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
      "roles": ["moderator"],
      "source_ids": [1, 2],
      "department_ids": [1, 3]
    }
  }
}
```

**Ошибки:** 422 — неверный email/пароль или роль не admin/moderator.

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
    "roles": ["moderator"],
    "source_ids": [1, 2],
    "department_ids": [1, 3]
  }
}
```

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
| status      | string | `open` (new+active) \| `closed`                |
| per_page    | int    | Записей на странице (1–100, по умолчанию 20) |
| page        | int    | Номер страницы                                |

**Ответ 200:** пагинированная коллекция чатов.

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
      "source": { "id": 1, "name": "ВКонтакте", "type": "vk" },
      "department": { "id": 1, "name": "Поддержка" },
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
| before_id| int | Вернуть сообщения с id < before_id   |
| limit    | int | Макс. записей (по умолчанию 50)       |
| per_page | int | Синоним limit                         |

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
      "created_at": "2026-02-17T10:00:00.000000Z",
      "updated_at": "2026-02-17T10:00:00.000000Z"
    }
  ]
}
```

`attachments` — массив объектов с полями `id`, `name`, `mime_type`, `size`, `url`.

---

### POST /chats/{id}/send

Отправить сообщение от имени модератора. Требует право `update` на чат.

**Тело (JSON):**

| Поле             | Тип     | Обязательно      | Описание |
|------------------|--------|-------------------|----------|
| text             | string | да*               | Текст сообщения |
| attachments      | array  | нет               | Массив путей из `POST /uploads` |
| client_message_id| string | нет               | UUID для идемпотентности (повтор запроса вернёт то же сообщение) |

\* Обязательно `text` или хотя бы один элемент в `attachments`.

**Ответ 201:** созданное сообщение в `data` (структура как в GET messages).

**Идемпотентность:** при повторной отправке с тем же `client_message_id` возвращается уже созданное сообщение (200).

---

## 4. Загрузка файлов

### POST /uploads

Загрузить файл (для вложений в сообщения). Требует авторизацию.

**Тело:** `multipart/form-data`, поле `file` — файл.

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
