# Realtime-уведомления о новых чатах на desktop/mobile

> **Для кого:** следующая нейросеть. **Что делает:** закрывает дыру, из-за которой модераторы не получают realtime-уведомлений о неназначенных чатах.

---

## 1. Проблема

События `NewChatMessage` и `ChatTopicGenerated` сейчас рассылаются на каналы:
- `private:chat.{chatId}` — слушает только тот, кто открыл конкретный чат.
- `public:widget-chat.{chatId}` — виджет клиента.
- `private:moderator.{userId}` — только если `$chat->assigned_to === $userId`.

Для **неназначенных** чатов (`assigned_to IS NULL`, вкладки «Свободные»/«Все» в инбоксе) ни один модератор не получает WebSocket-события. В результате desktop (`apps/pulse-desktop`) и mobile (`apps/pulse-mobile`) клиенты узнают о новых обращениях только при ручном рефреше списка или при получении FCM-пуша (который работает только на mobile и только если токен зарегистрирован).

На стороне FCM `ModeratorPushSupport::moderatorUserIdsForChat()` уже корректно выбирает получателей (при `assigned_to = null` — админы + модераторы источника), но для WebSocket-слоя аналогичной логики нет.

## 2. Решение: канал `source-inbox.{sourceId}`

Ввести новый приватный broadcast-канал на уровне источника. Все модераторы с доступом к `source_id` (плюс админы) подписываются. События `NewChatMessage` и `ChatTopicGenerated` вещаются на этот канал **всегда**, независимо от назначения.

Почему на уровне source, а не глобальный «inbox»: в системе multi-source (Telegram, web-виджет и т.д.), модератор одной команды не должен получать уведомления из чужого источника — ровно та политика, что уже есть в `ModeratorPushSupport`.

---

## 3. Бэкенд

### 3.1. Авторизация канала

**Файл:** `routes/channels.php`

Добавить рядом с существующими каналами `chat.{chatId}` и `moderator.{userId}`:

```php
Broadcast::channel('source-inbox.{sourceId}', function ($user, int $sourceId) {
    if (! $user->hasAnyRole(['admin', 'moderator'])) {
        return false;
    }
    if ($user->hasRole('admin')) {
        return true;
    }
    // Модератор — проверяем, что у него есть доступ к source через pivot.
    // Имя relation нужно сверить в app/Models/User.php — обычно sources() belongsToMany.
    return $user->sources()->where('sources.id', $sourceId)->exists();
});
```

> Сверь имя relation: `$user->sources()`. Если relation называется иначе (например, `accessibleSources`), поправь.

### 3.2. Расширить broadcast каналы у событий

**Файл:** `app/Events/NewChatMessage.php`

В методе `broadcastOn()` добавить канал источника. Источник чата нужно определить — вероятно, event уже принимает `$sourceId` или его можно достать из переданного `ChatModel`/`chatId`. Предпочтительно — добавить `sourceId` в конструктор event-а, если ещё не там.

```php
public function broadcastOn(): array
{
    $channels = [
        new PrivateChannel("chat.{$this->chatId}"),
        new Channel("widget-chat.{$this->chatId}"),
        new PrivateChannel("source-inbox.{$this->sourceId}"), // ← новое
    ];
    if ($this->assignedTo !== null) {
        $channels[] = new PrivateChannel("moderator.{$this->assignedTo}");
    }
    return $channels;
}
```

Аналогично в `app/Events/ChatTopicGenerated.php` — добавить `PrivateChannel("source-inbox.{$this->sourceId}")`. Если `sourceId` не передаётся в event — добавить в конструктор и в местах dispatch (`GenerateChatTopicJob`, `CreateMessage`, `SendMessage`, `WidgetApiController`) прокидывать `$chat->source_id`.

### 3.3. Broadcast payload

Проверь, что `broadcastWith()` возвращает в payload то же самое, что и сейчас (чтобы desktop/mobile-хендлеры не меняли код разбора). Поля типа `chatId`, `messageId`, `text`, `sender_type` должны быть.

Дополнительно для нового канала полезно добавить в payload:
- `chat_id`, `source_id`, `department_id` (если уже назначен), `assigned_to` (nullable)
- `is_new_chat` — boolean, был ли это **первое** сообщение в этом чате

`is_new_chat` помогает фронту решить, показывать ли «новый чат пришёл» или «новое сообщение в существующем чате».

### 3.4. Тест

**Файл:** `tests/Feature/Broadcasting/SourceInboxChannelTest.php`

Минимум 3 сценария:
1. Админ — авторизация на канале `source-inbox.{anyId}` проходит.
2. Модератор с доступом к source `X` — проходит для `source-inbox.{X}`, НЕ проходит для `source-inbox.{Y}`.
3. Модератор без ролей — не проходит ни на какой канал.

Реализация в репозитории: HTTP `POST /broadcasting/auth` при `BROADCAST_CONNECTION=null` использует `NullBroadcaster` и **не вызывает** callbacks каналов, поэтому тест напрямую вызывает зарегистрированный closure из драйвера (`Reflection` на свойстве `channels` у текущего `Broadcast::driver()`). Для интеграционной проверки через браузер используй `BROADCAST_CONNECTION=reverb` и DevTools → WS.

**Надёжность:** уведомления в фоне на Android остаются на FCM; событие может продублироваться на `moderator.{uid}` и `source-inbox.{sid}` — на desktop дедуп в `chatStore.bumpChatFromRealtime`, на mobile — в `inboxStore` по `chatId:messageId`.

---

## 4. Desktop (`apps/pulse-desktop`)

### 4.1. Хелпер подписки

**Файл:** `apps/pulse-desktop/src/lib/realtime.ts`

Рядом с `subscribeModeratorChannel()` добавить:

```typescript
export interface SourceInboxHandlers {
    onNewMessage?: (payload: NewChatMessagePayload) => void
    onChatTopicGenerated?: (payload: ChatTopicGeneratedPayload) => void
}

export function subscribeSourceInbox(
    sourceId: number,
    handlers: SourceInboxHandlers
): () => void {
    const client = getEcho()
    if (!client) return () => {}
    const ch = client.private(`source-inbox.${sourceId}`)
    if (handlers.onNewMessage) {
        ch.listen('.App\\Events\\NewChatMessage', handlers.onNewMessage)
    }
    if (handlers.onChatTopicGenerated) {
        ch.listen('.App\\Events\\ChatTopicGenerated', handlers.onChatTopicGenerated)
    }
    return () => {
        try {
            client.leave(`source-inbox.${sourceId}`)
        } catch { /* noop */ }
    }
}
```

### 4.2. Подписка в App.vue

**Файл:** `apps/pulse-desktop/src/App.vue`

После `setupModeratorRealtime()` добавить функцию `setupSourceInboxRealtime()`. Идея:

- Получить список источников пользователя (через существующий store или API `/api/v1/sources`).
- Для каждого `source.id` вызвать `subscribeSourceInbox(id, { ... })`.
- В `onNewMessage`:
  - `chatStore.bumpChatFromRealtime(payload)` — обновить превью.
  - Если `payload.is_new_chat === true` И чат ещё не в store — сделать `chatStore.loadChats()` или `chatStore.prependChatFromRealtime(payload)`.
  - Вызвать `notifyIncomingForModeratorInbox({...})` — звук + системное уведомление.
- В `onChatTopicGenerated`: `chatStore.applyChatTopicFromRealtime(payload.chatId, payload.topic)`.

Дедупликация: используемый сейчас `shouldDedupeIncomingNotify(chatId, messageId)` уже предотвращает двойное уведомление (одно и то же событие может прийти на `moderator.{uid}` + `source-inbox.{sid}`).

### 4.3. Unsubscribe при logout / смене sources

Массив unsubscribe-функций держать в переменной `sourceInboxUnsubs: Array<() => void>`. При смене auth / при logout — вызвать каждую и очистить. Аналогично при смене списка доступных sources.

---

## 5. Mobile (`apps/pulse-mobile`)

### 5.1. Хелпер подписки

**Файл:** `apps/pulse-mobile/src/lib/realtime.ts`

То же самое, что в desktop (код идентичен).

### 5.2. Подписка в `inboxStore.ts`

**Файл:** `apps/pulse-mobile/src/stores/inboxStore.ts`

Рядом с `setupModeratorRealtimeInbox()` добавить `setupSourceInboxRealtime()`:
- Взять список источников из API.
- Для каждого — `subscribeSourceInbox`.
- В `onNewMessage`:
  - `scheduleInboxRefreshFromRealtime()` — уже существует.
  - Если `payload.is_new_chat === true` — можно проиграть чуть более громкий/яркий звук (через `resolveNotificationScenario({ isUrgent: true })`).
  - Дедуплицировать по `(chatId, messageId)`.

### 5.3. Локальные уведомления когда приложение открыто

Сейчас в mobile при foreground play sound + вибрация, но **нет** системного уведомления (в отличие от desktop). Это стоит добавить — особенно когда приложение в фоне, но не закрыто полностью:

```typescript
import { LocalNotifications } from '@capacitor/local-notifications'
// (нужно добавить плагин: npm i @capacitor/local-notifications)

await LocalNotifications.schedule({
    notifications: [{
        id: Math.floor(Math.random() * 10000),
        title: 'Новое обращение',
        body: payload.text?.slice(0, 100) ?? '',
        extra: { chatId: payload.chatId },
    }],
})
```

Но это опционально — FCM-пуш уже покрывает background-сценарий.

---

## 6. Проверка на проде перед рефакторингом

Перед реализацией убедись, что базовый WebSocket вообще работает:

```bash
# На сервере:
grep -E '^REVERB_(APP_KEY|HOST|PORT|SCHEME)' .env
# REVERB_HOST должен быть публичным доменом, SCHEME=https, PORT обычно 443 (через nginx reverse proxy)

grep -E '^VITE_REVERB' .env
# Без этих переменных фронт собран с Echo === null, никакой realtime не работает вообще

# Проверить, что Reverb-сервер запущен:
systemctl status pulse-reverb

# Тестовый коннект:
# Открой DevTools → Network → WS в desktop/mobile. Должен быть handshake к wss://host/app/KEY.
# Если redirects на localhost — значит VITE_REVERB_HOST не подхватился при сборке.
```

Если эти проверки фейлятся — сначала чинь их, потом уже добавляй новый канал.

---

## 7. Last-mile: nginx

Reverb требует HTTP-апгрейда до WebSocket. В nginx конфиге должен быть отдельный location:

```nginx
location /app {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection "Upgrade";
    proxy_set_header Host $host;
    proxy_read_timeout 3600s;
    proxy_send_timeout 3600s;
}
```

Проверь: `curl -I https://pulse.example.com/app/APP_KEY` — должен вернуть заголовки, не 404.

---

## 8. Порядок работ (3 PR)

**PR 1 — бэкенд (малый):**
- Канал `source-inbox.{sourceId}` в `routes/channels.php`.
- `NewChatMessage::broadcastOn()` + `ChatTopicGenerated::broadcastOn()` добавляют новый канал.
- При необходимости — `sourceId` в конструктор event-а и в местах dispatch.
- `is_new_chat` в payload (вычисляется как «первое сообщение в чате» — можно через `Message::where('chat_id', ...)->count() === 1`).
- Тесты авторизации канала.

**PR 2 — desktop:**
- `subscribeSourceInbox` в `realtime.ts`.
- Инициализация в `App.vue` после `setupModeratorRealtime`.
- Дедупликация с уже существующим realtime-потоком.

**PR 3 — mobile:**
- То же, что PR 2, плюс опциональные `@capacitor/local-notifications` для foreground-уведомлений.

После PR 1 — можно вручную проверить подписку через DevTools (`echo.private('source-inbox.1').listen(...)` в консоли).

---

## 9. Промпт для старта

> Ты работаешь над проектом в `C:\laragon\www\pulse`. Открой `docs/REALTIME_NEW_CHAT_NOTIFICATIONS.md` и реализуй **PR 1** из раздела 8 этого документа — чисто бэкенд.
>
> Шаги:
> 1. Прочти документ полностью.
> 2. Проверь перед началом:
>    - актуальное имя relation `User -> sources` (grep в `app/Models/User.php`).
>    - текущие конструкторы `NewChatMessage` и `ChatTopicGenerated` — есть ли там `sourceId` или нужно добавлять.
>    - все места `event(new NewChatMessage(...))` и `event(new ChatTopicGenerated(...))` — нужно ли им прокидывать `sourceId` (проверь grep-ом).
> 3. Составь TodoList.
> 4. Реализуй изменения из разделов 3.1, 3.2, 3.3.
> 5. Напиши тест из 3.4.
> 6. Прогон `php artisan test --filter=SourceInbox`.
> 7. Покажи diff, не коммить.
>
> Критические ограничения:
> - Не удаляй существующие каналы `chat.{id}`, `widget-chat.{id}`, `moderator.{uid}`.
> - Не меняй формат payload существующих полей — только **добавляй** `source_id` и `is_new_chat`.
> - Не трогай пока frontend (`apps/pulse-desktop`, `apps/pulse-mobile`) — это будет PR 2 и PR 3.
