# Telegram Business Mode для TG-источника

## Контекст

Сейчас `TelegramMessengerProvider` и `WebhookPayloadExtractor` умеют работать только с обычными Bot API событиями: `message`, `edited_message`, `channel_post`, `callback_query`. Когда администратор АЧПП подключает нашего бота к своему личному аккаунту через Telegram Business (`Settings → Business → Chatbots`), Telegram начинает слать на webhook события другого формата:

- `business_connection` — бот был подключён или отключён от аккаунта. Содержит `id` (это и есть `business_connection_id`), `user` (владелец аккаунта), `user_chat_id`, `date`, `can_reply`, `is_enabled`.
- `business_message` — входящее ЛС в подключённом аккаунте. Структура аналогична обычному `message`, плюс поле `business_connection_id` и, иногда, `sender_business_bot`.
- `edited_business_message` — редактирование.
- `deleted_business_messages` — удаление (массовое, список `message_ids`).

Текущий `validateWebhook` отвергает все четыре типа как «Invalid webhook payload». Задача — научить Pulse принимать, обрабатывать и **отвечать от имени владельца аккаунта** через параметр `business_connection_id` в исходящих API-запросах.

**Бизнес-цель:** модератор АЧПП продолжает получать сообщения от юзеров в свой личный Telegram-аккаунт как раньше. Но под капотом — сообщения попадают в Pulse, раскидываются по департаментам, в приложении отвечает любой из модераторов, и ответ уходит юзеру **от имени того же самого живого аккаунта**, а не от бота. Для юзера поведение Telegram неотличимо от прямой переписки с человеком.

## Что не делаем в этом PR

- **Forum-топики** (`message_thread_id`) — отдельная фича для групповых чатов, отдельный PR. В этом PR только Business Mode для ЛС.
- **`edited_business_message`** и **`deleted_business_messages`** — обработчики не реализуем, но webhook не должен падать при их получении (просто игнорировать и возвращать 200).
- **Автоматическая регистрация webhook с `allowed_updates`** — если сейчас `setWebhook` вызывается руками через curl/скрипт, туда нужно добавить `business_connection`, `business_message`, `edited_business_message`, `deleted_business_messages` в `allowed_updates`. Описать в README раздела деплоя, не автоматизировать.
- **Несколько одновременных business-подключений к одному источнику.** В MVP — один TG-источник = один активный business-connection. Если админ переподключит бота заново — новый connection_id заменит старый.
- **UI для подключения в Filament** — админ настраивает подключение через стандартный UX Telegram (в клиенте → Settings → Business → Chatbots), не в нашем админе. Pulse только слушает события `business_connection` и сохраняет id.

## Архитектура

### Режимы работы TG-источника

Вводим enum `TelegramMode` в `App\Domains\Integration\ValueObject`:

```php
enum TelegramMode: string
{
    case Direct = 'direct';     // Обычный бот: юзеры пишут боту напрямую в его ЛС.
    case Business = 'business'; // Business Mode: бот подключён к живому аккаунту, обрабатывает его ЛС.
}
```

Хранится в `SourceModel.settings.telegram_mode` (строка). Дефолт — `direct` (не ломаем существующих).

**Важно:** режим — это «что мы *ожидаем* получать». `direct` источник отвергает business-события (warning в лог), `business` источник отвергает обычные `message`-события, не адресованные нам (предотвращаем кейс, когда кто-то пишет боту напрямую, пока он в Business-режиме привязан к чужому аккаунту). На практике это отсечёт случайные сообщения тестов в самого бота.

### Хранение business_connection_id

Подход: **на уровне источника** + **на уровне чата**.

1. `SourceModel.settings.business_connection_id: ?string` — текущее активное подключение для этого источника. Заполняется при обработке события `business_connection` с `is_enabled: true`. Обнуляется при `is_enabled: false`.
2. `ChatModel.external_business_connection_id: ?string` (новая колонка) — конкретное подключение, через которое пришло первое сообщение этого чата. Нужно на случай, если админ переподключит бота (connection_id сменится) — старые чаты всё равно смогут отвечать через старый id.

При отправке исходящего — берём `external_business_connection_id` из чата. Если null и у источника есть активный `business_connection_id` — используем его как фолбэк.

### Валидация и маршрутизация webhook

Текущий `validateWebhook` расширяется:

```php
public function validateWebhook(array $payload): bool
{
    return isset($payload['message'])
        || isset($payload['callback_query'])
        || isset($payload['edited_message'])
        || isset($payload['channel_post'])
        || isset($payload['business_connection'])
        || isset($payload['business_message'])
        || isset($payload['edited_business_message'])
        || isset($payload['deleted_business_messages']);
}
```

В `ProcessInboundWebhook::run()` после `validateWebhook` добавляется **ранняя маршрутизация**:

1. Если `$payload['business_connection']` — вызвать новый `HandleBusinessConnectionEvent`, `return`.
2. Если `$payload['edited_business_message']` или `$payload['deleted_business_messages']` — логировать info и `return`.
3. Если `$payload['business_message']`:
   - Если `source.settings.telegram_mode !== 'business'` — warning в лог, `return`.
   - Иначе — продолжаем стандартный flow, но `WebhookPayloadExtractor` будет тянуть поля из `business_message`, а не `message`.
4. Если `$payload['message']` и `source.settings.telegram_mode === 'business'` — warning «direct message arrived on business source», `return`.
5. Иначе — как сейчас.

## Пофайловый план

### 1. Миграция — новая колонка на `chats`

Файл: `database/migrations/2026_04_21_000004_add_external_business_connection_id_to_chats_table.php`

```php
Schema::table('chats', function (Blueprint $table) {
    $table->string('external_business_connection_id', 128)->nullable()->after('source_id');
    $table->index(['source_id', 'external_business_connection_id'], 'chats_source_business_idx');
});
```

Reverse — drop колонки и индекса.

### 2. Enum `TelegramMode`

Файл: `app/Domains/Integration/ValueObject/TelegramMode.php` (новый).

```php
enum TelegramMode: string
{
    case Direct = 'direct';
    case Business = 'business';

    public static function fromSettings(array $settings): self
    {
        $raw = $settings['telegram_mode'] ?? self::Direct->value;
        return self::tryFrom(is_string($raw) ? $raw : self::Direct->value) ?? self::Direct;
    }
}
```

### 3. `ChatModel` — cast для новой колонки

Добавить `'external_business_connection_id'` в `$fillable` и в `$casts` (string).

### 4. `WebhookPayloadExtractor` — поддержка `business_message`

Везде, где сейчас читается `$payload['message']`, добавляется поддержка `business_message`:

- `extractExternalUserId`: добавить `$payload['business_message']['from']['id']` в цепочку `??`.
- `extractText`: в определении `$message` добавить `$payload['business_message']`.
- `extractExternalMessageId`: добавить `$payload['business_message']['message_id']`.
- `extractReplyToExternalMessageId`: добавить `$payload['business_message']` в список containers.
- `extractUserMetadata`: тянуть `from` из `business_message` если других нет.
- Новый метод: `extractBusinessConnectionId(array $payload): ?string` — возвращает `$payload['business_message']['business_connection_id'] ?? null`.

### 5. Новый обработчик — `HandleBusinessConnectionEvent`

Файл: `app/Application/Communication/Action/HandleBusinessConnectionEvent.php`.

```php
final readonly class HandleBusinessConnectionEvent
{
    public function __construct(
        private SourceRepositoryInterface $sourceRepository,
    ) {}

    /** @param array<string, mixed> $payload */
    public function run(int $sourceId, array $payload): void;
}
```

Логика:
1. Извлечь `connection_id`, `is_enabled` из `$payload['business_connection']`.
2. Загрузить `SourceModel` по id.
3. Записать в `settings`:
   - Если `is_enabled === true`: `settings.business_connection_id = $connectionId`, `settings.business_connection_user_id = $payload['business_connection']['user']['id']`, `settings.business_connection_activated_at = now()->toIso8601String()`.
   - Если `is_enabled === false` и текущий `settings.business_connection_id === $connectionId`: обнулить эти поля.
4. `save()`.
5. Логировать: info «business connection activated/deactivated», с source_id и hashed connection_id (не полный id — приватная инфа).

### 6. `ProcessInboundWebhook` — ранняя маршрутизация

Вставляется после `validateWebhook` и до извлечения `externalUserId`:

```php
if (isset($payload['business_connection'])) {
    $this->handleBusinessConnectionEvent->run($sourceId, $payload);
    return;
}

if (isset($payload['edited_business_message']) || isset($payload['deleted_business_messages'])) {
    Log::info('Business edit/delete event ignored', ['source_id' => $sourceId]);
    return;
}

$mode = TelegramMode::fromSettings($source->settings);

if (isset($payload['business_message']) && $mode !== TelegramMode::Business) {
    Log::warning('business_message arrived on non-business source', ['source_id' => $sourceId]);
    return;
}

if (isset($payload['message']) && $mode === TelegramMode::Business) {
    Log::warning('direct message arrived on business source', ['source_id' => $sourceId]);
    return;
}
```

После этого — `extractBusinessConnectionId($payload)` и передача его вниз по пайплайну для сохранения на `ChatModel`.

### 7. `InboundChatUpsert` — сохранять `external_business_connection_id`

Добавить в `resolve()` опциональный параметр `?string $businessConnectionId = null`. При создании нового `ChatModel` — писать в колонку. При update существующего — **перезаписывать только если null** (не затираем историю при переподключении).

### 8. `TelegramMessengerProvider::sendMessage` — пробрасывать `business_connection_id`

В `sendMessage` извлекать из `$options`:

```php
$businessConnectionId = isset($options['business_connection_id']) && is_string($options['business_connection_id'])
    ? $options['business_connection_id']
    : null;
unset($params['business_connection_id']);
```

И пробрасывать в `$this->client->sendMessage()` и `sendWithLocalFiles()` через `$params`.

**Валидация:** если `business_connection_id` задан, убедиться, что `externalUserId` — это id юзера (не chat_id бота). По факту это одно и то же — `from.id` из `business_message`, которое и сохраняется в `chat.external_user_id`. Просто не ломать если что.

### 9. `TelegramApiClient::sendMessage` и `sendWithLocalFiles`

Добавить в тело POST:

```php
if (isset($params['business_connection_id']) && is_string($params['business_connection_id']) && $params['business_connection_id'] !== '') {
    $body['business_connection_id'] = $params['business_connection_id'];
}
```

**Важно**: `business_connection_id` также поддерживается в `sendPhoto`, `sendDocument`, `sendMediaGroup` — в `sendWithLocalFiles` пробросить его через multipart во все три вида запросов (в `$multipart[] = ['name' => 'business_connection_id', 'contents' => ...]`).

### 10. `DeliverOutboundMessageToMessenger` — пробрасывать `business_connection_id` из чата

Найти, где формируется `$options` перед вызовом `$messenger->sendMessage(...)`. Добавить:

```php
$chatModel = ChatModel::query()->find($chatId);
if ($chatModel?->external_business_connection_id !== null) {
    $options['business_connection_id'] = $chatModel->external_business_connection_id;
} elseif (!empty($source->settings['business_connection_id'])) {
    $options['business_connection_id'] = $source->settings['business_connection_id'];
}
```

### 11. Filament — селектор режима

В `SourceModelResource.php`, в секции для **TG** (не-VK ветка), перед полями offline/welcome добавить:

```php
Select::make('telegram_mode')
    ->label('Режим Telegram-интеграции')
    ->options([
        'direct' => 'Direct — пользователи пишут боту напрямую',
        'business' => 'Business Mode — бот подключён к личному аккаунту модератора',
    ])
    ->default('direct')
    ->visible(fn (callable $get): bool => $get('type') === 'tg')
    ->helperText('В режиме Business нужен Telegram Premium на аккаунте-хосте и ручное подключение бота в настройках Telegram (Settings → Business → Chatbots).')
    ->required(),
```

Плюс информационный `Placeholder` под ним, который показывает текущий `business_connection_id` (если есть) — чтобы админ видел «подключение активно»:

```php
Placeholder::make('business_connection_status')
    ->label('Статус business-подключения')
    ->visible(fn (callable $get): bool => $get('type') === 'tg' && $get('telegram_mode') === 'business')
    ->content(function ($record) {
        $cid = $record?->settings['business_connection_id'] ?? null;
        return $cid ? 'Активно (id: '.substr($cid, 0, 8).'...)' : 'Не подключено. Добавьте бота в Telegram Business.';
    }),
```

### 12. `MapsSourceConnectionSettings` — маппинг `telegram_mode`

В `mapConnectionSettingsBeforeFill` (ветка не-VK):

```php
$data['telegram_mode'] = (string) ($settings['telegram_mode'] ?? 'direct');
// unset из $conn — telegram_mode не относится к connection_settings
unset($conn['telegram_mode']);
```

В `mapConnectionSettingsBeforePersist`:

```php
if (($data['type'] ?? '') === 'tg') {
    $data['settings']['telegram_mode'] = (string) ($data['telegram_mode'] ?? 'direct');
}
unset($data['telegram_mode']);
```

**Не маппить** `business_connection_id`, `business_connection_user_id`, `business_connection_activated_at` — они управляются только через `HandleBusinessConnectionEvent`, админ их руками не правит. В Filament форме они не должны присутствовать.

## Тесты

### Unit

**`tests/Unit/WebhookPayloadExtractorBusinessTest.php`** — новый:

1. `extractExternalUserId_from_business_message` — payload `{"business_message":{"from":{"id":12345}}}` → `"12345"`.
2. `extractText_from_business_message` — `{"business_message":{"text":"hello"}}` → `"hello"`.
3. `extractExternalMessageId_from_business_message` — `{"business_message":{"message_id":777}}` → `"777"`.
4. `extractBusinessConnectionId` — новое поле, `{"business_message":{"business_connection_id":"abc"}}` → `"abc"`; без него → `null`.
5. `extractUserMetadata_from_business_message_from` — собирает имя из `business_message.from.first_name + last_name`.

**`tests/Unit/TelegramModeFromSettingsTest.php`** — новый, проверяет дефолты и `tryFrom` на мусорных значениях.

### Feature

**`tests/Feature/BusinessConnectionEventTest.php`** — новый:

1. `test_business_connection_enabled_saves_id_to_source_settings` — POST webhook с `business_connection` (`is_enabled: true`). Assert: в БД `source.settings.business_connection_id` равен пришедшему id.
2. `test_business_connection_disabled_clears_id` — сначала активируем, потом disabled-событие → id очищен.
3. `test_business_connection_disabled_with_different_id_is_ignored` — пришёл disabled для старого id, а текущий другой → текущий не трогаем.

**`tests/Feature/BusinessMessageFlowTest.php`** — новый:

1. `test_business_message_on_business_source_creates_chat` — источник с `telegram_mode=business`, webhook с `business_message` → создан чат с `external_business_connection_id`, создано сообщение с правильным текстом и from.id.
2. `test_business_message_on_direct_source_is_rejected` — источник с `telegram_mode=direct`, приходит `business_message` → чат не создан, warning в логах.
3. `test_direct_message_on_business_source_is_rejected` — источник business, приходит обычный `message` → чат не создан, warning.
4. `test_outbound_message_uses_business_connection_id_from_chat` — создан чат с business_connection_id="xyz", отправляем исходящее → мок `TelegramApiClient::sendMessage` получил `business_connection_id=xyz` в params.
5. `test_outbound_fallback_to_source_business_connection_id` — на чате id=null, на source.settings id="fallback" → в запросе используется "fallback".
6. `test_outbound_direct_source_does_not_send_business_connection_id` — обычный direct источник, в params нет `business_connection_id`.
7. `test_edited_business_message_is_ignored_without_error` — webhook `edited_business_message` → 200, info в логах, в БД ничего не меняется.
8. `test_welcome_and_offline_auto_reply_work_in_business_mode` — `/start` через business_message → welcome уходит через `sendMessage` с правильным `business_connection_id` (из source.settings, т.к. чат ещё не создан на этом этапе).

### Существующие тесты

Пробежаться глазами по `WebhookControllerTest`, `TelegramMediaGroupInboundTest`, `WebhookPayloadExtractorTelegramTest` — убедиться, что изменения в `validateWebhook` и `WebhookPayloadExtractor` обратно совместимы, ничего не сломано.

## Ручная проверка на продакшне после деплоя

Prerequisite: у тебя есть Telegram Premium и бот с включённым Business Mode через BotFather.

1. Создать новый TG-источник в Filament (или переключить существующий тестовый) → `telegram_mode = business`.
2. В `setWebhook` бота добавить `allowed_updates`:
   ```
   curl "https://api.telegram.org/bot<TOKEN>/setWebhook" \
     --data-urlencode "url=https://pulse.example.com/webhooks/tg/<SOURCE_ID>" \
     --data-urlencode 'allowed_updates=["message","edited_message","callback_query","channel_post","business_connection","business_message","edited_business_message","deleted_business_messages"]'
   ```
3. В Telegram → Settings → Business → Chatbots → ввести username бота, снять «Exclude existing chats», сохранить.
4. Через 1–2 секунды в логах Pulse должно быть «business connection activated». В БД: `SELECT settings FROM sources WHERE id = <SOURCE_ID>` → есть `business_connection_id`.
5. Попросить тестового юзера (второй аккаунт) написать в ЛС твоего аккаунта произвольное сообщение.
6. В Pulse-приложении: появился новый чат от этого юзера. Открыть его — в БД у `chats.external_business_connection_id` записан тот же id, что в источнике.
7. Ответить из Pulse «Привет, это тест». У тестового юзера в ЛС **твоего аккаунта** (не бота) появилось «Привет, это тест».
8. Юзер пишет `/start` — если у источника `welcome_enabled=true`, приходит welcome от твоего имени. Чат в БД не создаётся.
9. Отключить бота в Telegram Business → в логах «business connection deactivated». В БД `business_connection_id` очищен.

## Риски и откат

- **Одновременная активность direct + business на одном боте.** Мы запретили в коде через warning, но физически Telegram может слать оба типа событий, если бот в BotFather разрешён для Business и параллельно кто-то пишет ему напрямую. Warning будет шуметь в логах — это нормально, значит, кто-то залез не туда. Не критично.
- **Смена `business_connection_id` без уведомления.** Теоретически Telegram может переиздать id при определённых сценариях (редкие баги их стороны). Мы храним id на чате, поэтому ответы на старые чаты не сломаются — Telegram отдаст 400 на устаревший id, поймаем в `catch` в `sendMessage`, залогируем. Фолбэк — дёргать новый id из source.settings.
- **Откат:** выключить `telegram_mode` обратно в `direct` в Filament. Business-события продолжат приходить, но код их отвергнет с warning. Чтобы совсем прекратить — отключить бота в Telegram Business. Миграция — nullable колонка, drop безболезненный (но делать не обязательно).
- **Приватность.** `business_connection_id` — это эффективно ключ от ЛС владельца аккаунта. Не выводить в публичные API, не показывать в UserResource, не светить в frontend payload. Хранить как обычный external_id — на уровне БД.

## Чеклист перед мерджем

- [ ] Миграция создана и прогоняется в обе стороны (`migrate` + `migrate:rollback`).
- [ ] Enum `TelegramMode` с безопасным `fromSettings`.
- [ ] `ChatModel` — cast и fillable для новой колонки.
- [ ] `WebhookPayloadExtractor` — поддержка `business_message` во всех 5 методах + новый `extractBusinessConnectionId`.
- [ ] `HandleBusinessConnectionEvent` с сохранением/очисткой id в source settings.
- [ ] `ProcessInboundWebhook` — ранняя маршрутизация всех 4-х business-типов.
- [ ] `InboundChatUpsert` — опциональный параметр business_connection_id.
- [ ] `TelegramApiClient::sendMessage` + `sendWithLocalFiles` + multipart отправки — проброс `business_connection_id`.
- [ ] `TelegramMessengerProvider::sendMessage` — извлечение и проброс.
- [ ] `DeliverOutboundMessageToMessenger` — fallback логика chat → source.
- [ ] Filament — Select `telegram_mode` + Placeholder статуса.
- [ ] `MapsSourceConnectionSettings` — маппинг `telegram_mode` в обе стороны.
- [ ] 5 unit + 3 + 8 = 11 feature тестов, все зелёные.
- [ ] `./vendor/bin/pint` чисто.
- [ ] Существующие тесты (`WebhookControllerTest`, `TelegramMediaGroupInboundTest`, `WebhookPayloadExtractorTelegramTest`) — проходят.
- [ ] Ручная проверка по runbook выше.
- [ ] В README (или отдельном `docs/TG_BUSINESS_SETUP.md`) — инструкция по `setWebhook` с `allowed_updates` и по подключению бота через клиент.

## Out of Scope follow-ups

- Сохранять историю `business_connection` событий (connection_activated / deactivated) в отдельной таблице для аудита.
- Обрабатывать `edited_business_message` (обновлять текст сообщения в БД) и `deleted_business_messages` (помечать как deleted).
- UI в Filament для просмотра списка активных business-подключений и ручной деактивации.
- Поддержка нескольких одновременных подключений к одному источнику (разные аккаунты под одним ботом).
- Forum-топики — следующий PR.
