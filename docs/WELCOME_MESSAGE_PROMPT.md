# Welcome-сообщение для ботов (TG + VK + MAX)

## Контекст

Сейчас в системе Pulse, когда пользователь нажимает `/start` в Telegram-боте или жмёт кнопку «Начать» у сообщества VK, бот **ничего не отвечает**: команда летит в `ProcessInboundWebhook` как обычный текст, записывается как первое сообщение чата, чат попадает в инбокс модераторов с телом `"/start"` или `"Начать"`. UX-проблема: юзер не понимает, «работает ли». Админ-проблема: инбокс засорён чатами-пустышками от юзеров, которые ткнули Start из любопытства.

Цель — добавить источнико-настраиваемое welcome-сообщение, которое бот отправляет моментально **без создания чата в БД**.

## Требования

### Функциональные

1. Для каждого источника (`SourceModel`) в админке Filament появляются поля:
   - `welcome_enabled: bool` — по умолчанию `false` (не ломаем существующих).
   - `welcome_text: string|null` — до 2000 символов, многострочный.
   - Хранятся в `source.settings` (JSON-колонка, как `offline_auto_reply_*`).
2. Когда в `ProcessInboundWebhook` приходит «start-триггер» от пользователя:
   - Если `welcome_enabled=true` и `welcome_text` непуст — бот отправляет `welcome_text` напрямую через `MessengerProviderInterface::sendMessage($externalUserId, $text)`.
   - **Чат в БД не создаётся**, сообщение «/start»/«Начать» не сохраняется.
   - Если `welcome_enabled=false` или `welcome_text` пуст — **всё равно НЕ создавать** чат c мусорным "/start"/«Начать»; просто `return` (исправляем побочный баг — сейчас такие чаты попадают в инбокс).
3. Throttle: welcome не чаще 1 раза в 5 минут на пару `(source_id, external_user_id)` — защита от флуда при многократных нажатиях Start.
4. Web-источники (`SourceType::Web`) — игнорируются (у виджета свой UX, welcome не нужен).
5. MAX-источники — обрабатываются по тем же правилам, что TG (команда `/start`).

### Что считать «start-триггером»

| Источник | Триггеры (любой из) |
|----------|---------------------|
| `tg` / `max` | Текст сообщения начинается с `/start` (после trim, case-sensitive — Telegram всегда нижний регистр). Примеры: `/start`, `/start ref_abc123`, `/start@MyBotName`. |
| `vk` | 1) `object.message.payload` JSON-декодится в массив с ключом `command` === `"start"`. 2) ИЛИ `object.message.text` после trim+mb_strtolower ∈ {`"начать"`, `"start"`, `"/start"`}. |

**Важно по VK.** Дефолтная кнопка «Начать» у VK-сообщества при первом диалоге отправляет payload `{"command":"start"}` **и** текст `"Начать"`. Но часть клиентов (мобильный VK, старый веб) иногда шлёт только текст без payload. Поэтому нужен двойной матч: payload ИЛИ текст в whitelist.

**Важно по TG.** Deep-link payload `/start ref_abc` — не игнорировать целиком. В первой итерации достаточно просто среагировать welcome'ом; сохранение payload в `users.metadata.start_payload` — опциональный follow-up (не делать в этом PR, отметить в Out of Scope).

## Архитектура

### Новые/изменённые файлы

#### 1. `app/Application/Communication/Webhook/StartCommandDetector.php` (новый)

```
final readonly class StartCommandDetector
{
    /** @param array<string, mixed> $payload */
    public function isStartCommand(SourceType $sourceType, array $payload): bool;
}
```

- Инкапсулирует всю логику детекции по таблице выше.
- Возвращает `true`/`false`. Без сайд-эффектов.
- Для VK парсит `object.message.payload` через `json_decode` с try/catch (битый JSON → игнор).
- Для TG/MAX матчит `str_starts_with(trim($text), '/start')` **и** проверяет, что это либо чистый `/start`, либо за ним идёт пробел или `@` (иначе матчит, например, `/startup`).
- Юнит-покрыт тестами на все кейсы из таблицы.

#### 2. `app/Services/SendWelcomeMessage.php` (новый)

```
final readonly class SendWelcomeMessage
{
    public function __construct(
        private ResolveMessengerProvider $resolveMessenger,
        private CacheRepository $cache, // Laravel Cache
    ) {}

    public function run(SourceModel $source, string $externalUserId): void;
}
```

- Читает `$source->settings['welcome_enabled']` и `welcome_text`.
- Если не включено / пусто → `return`.
- Throttle: ключ `welcome_sent:{sourceId}:{externalUserId}`, TTL 5 минут. Если ключ есть → `return`. Иначе ставит ключ и шлёт.
- Резолвит messenger provider, вызывает `sendMessage($externalUserId, $text)`.
- Ошибки мессенджера ловятся и логируются через `Log::warning('Welcome message send failed', [...])` — не фейлим webhook handler.

#### 3. `app/Application/Communication/Action/ProcessInboundWebhook.php` (правка)

Сразу после резолва `$source` и извлечения `$externalUserId` (до `extractDepartmentId` и `inboundChatUpsert`):

```php
if ($this->startCommandDetector->isStartCommand($source->type, $payload)) {
    $this->sendWelcomeMessage->run($sourceModel, $externalUserId);
    return;
}
```

Нужно инжектить `StartCommandDetector` и `SendWelcomeMessage` в конструктор. Важно: передавать **Eloquent-модель** `SourceModel`, а не доменную сущность (settings — на модели). Либо получить модель через `SourceModel::query()->find($sourceId)` после `$this->sourceRepository->findById`. Выбрать консистентно с тем, как это сделано сейчас для `MaybeSendOfflineAutoReply` (там отдельный `SourceModel::query()->find($chat->source_id)` — аналогично).

#### 4. `app/Filament/Resources/SourceModels/SourceModelResource.php` (правка)

Рядом с `offline_auto_reply_enabled` / `offline_auto_reply_text` добавить два поля:

```php
Toggle::make('welcome_enabled')
    ->label('Приветственное сообщение на /start')
    ->helperText('Отправляется на /start у TG-ботов и «Начать» у VK. Чат в инбоксе не создаётся. Не чаще 1 раза в 5 минут на одного пользователя.')
    ->default(false),
Textarea::make('welcome_text')
    ->label('Текст приветствия')
    ->rows(4)
    ->maxLength(2000)
    ->columnSpanFull()
    ->nullable(),
```

Добавить **в обе группы**: одна для не-VK (находится около строки 99–107 текущего файла), другая для VK (около 138–146). Смотри как сделано у `offline_auto_reply_*` — та же структура дублирования.

#### 5. `app/Filament/Resources/SourceModels/Concerns/MapsSourceConnectionSettings.php` (правка)

По аналогии с `offline_auto_reply_*` — маппинг в обе стороны:

В `mapConnectionSettingsBeforeFill`:
```php
$data['welcome_enabled'] = (bool) ($settings['welcome_enabled'] ?? false);
$data['welcome_text'] = (string) ($settings['welcome_text'] ?? '');
```
Плюс `unset($conn['welcome_enabled'], $conn['welcome_text']);` рядом с таким же unset'ом для offline_auto_reply.

В `mapConnectionSettingsBeforePersist`:
```php
$data['settings']['welcome_enabled'] = (bool) ($data['welcome_enabled'] ?? false);
$data['settings']['welcome_text'] = trim((string) ($data['welcome_text'] ?? ''));
unset($data['welcome_enabled'], $data['welcome_text']);
```

Для VK — поскольку у VK settings редактируется через отдельный путь (`settings` state path напрямую), нужно проверить, что поля `welcome_enabled`/`welcome_text` корректно сериализуются в JSON. Если VK-блок использует `KeyValue` или `Group::statePath('settings')` — поля с `Toggle`/`Textarea` должны писаться напрямую в `settings.*` без промежуточного маппинга. Важно: проверить поведение вручную после реализации (создать VK-источник, выставить поля, сохранить, открыть повторно — должны подхватиться).

#### 6. Миграции — **не нужны**.

`settings` уже JSON-колонка, новые ключи добавляются без схемных изменений. Ни одной новой таблицы/колонки.

### Не затрагивать

- `MaybeSendOfflineAutoReply` — остаётся как есть, это другой кейс.
- `CreateMessage`, `InboundChatUpsert` — welcome идёт мимо них, ничего не пишет в БД.
- `NewChatMessage` event — не триггерится welcome'ом (чата нет).

## Тесты

### Unit

**`tests/Unit/StartCommandDetectorTest.php`** — новый. Кейсы:

| Кейс | Source | Payload | Ожидание |
|------|--------|---------|----------|
| TG `/start` чистый | `tg` | `{"message":{"text":"/start"}}` | `true` |
| TG `/start` с payload | `tg` | `{"message":{"text":"/start ref_abc"}}` | `true` |
| TG `/start@BotName` | `tg` | `{"message":{"text":"/start@MyBot"}}` | `true` |
| TG `/startup` (не команда) | `tg` | `{"message":{"text":"/startup please"}}` | `false` |
| TG обычный текст | `tg` | `{"message":{"text":"привет"}}` | `false` |
| VK payload команда | `vk` | `{"object":{"message":{"payload":"{\"command\":\"start\"}"}}}` | `true` |
| VK текст «Начать» | `vk` | `{"object":{"message":{"text":"Начать"}}}` | `true` |
| VK текст «начать» (lowercase) | `vk` | `{"object":{"message":{"text":"начать"}}}` | `true` |
| VK текст «Start» | `vk` | `{"object":{"message":{"text":"Start"}}}` | `true` |
| VK обычный текст | `vk` | `{"object":{"message":{"text":"здравствуйте"}}}` | `false` |
| VK битый JSON payload | `vk` | `{"object":{"message":{"payload":"{broken"}}}` | `false` (без исключений) |
| MAX `/start` | `max` | `{"message":{"text":"/start"}}` | `true` |
| Web источник | `web` | что угодно | `false` |

### Feature

**`tests/Feature/WelcomeMessageTest.php`** — новый. Мокаем `MessengerProviderInterface`:

1. `test_welcome_sent_for_tg_start_when_enabled` — settings `welcome_enabled=true`, `welcome_text="Здравствуйте!"`. POST на webhook с `/start`. Ожидание: `sendMessage` вызван один раз с правильными аргументами; `ChatModel::count() === 0`; `MessageModel::count() === 0`.
2. `test_welcome_not_sent_when_disabled` — `welcome_enabled=false`. Webhook `/start`. Ожидание: `sendMessage` не вызван. **Чат тоже НЕ создан** (проверка побочного бага).
3. `test_welcome_not_sent_when_text_empty` — `welcome_enabled=true`, `welcome_text=""`. Аналогично — `sendMessage` не вызван, чат не создан.
4. `test_welcome_throttled_within_5_minutes` — первый `/start` → отправка. Второй `/start` от того же `external_user_id` через 2 минуты → `sendMessage` вызван всего 1 раз. Третий через 6 минут → 2 раза.
5. `test_welcome_for_vk_payload_start` — VK payload `{"command":"start"}`. Отправка + чат не создан.
6. `test_welcome_for_vk_text_nachat` — VK текст «Начать». Отправка + чат не создан.
7. `test_regular_message_after_start_creates_chat` — сначала `/start` (welcome уехал), потом обычное сообщение «Мне нужна помощь». Ожидание: чат создан, в нём 1 сообщение — «Мне нужна помощь», БЕЗ предшествующего «/start».
8. `test_welcome_send_failure_does_not_crash_webhook` — мок провайдера бросает `RuntimeException`. Ожидание: webhook возвращает 200, в логах warning.

### Обновить существующие

`tests/Feature/WebhookControllerTest.php` — проверить, что тесты, которые посылают `/start` или «Начать» как начальное сообщение, не сломаются. Вероятно, там таких нет (тесты явно шлют тексты типа «hello»), но пробежаться глазами.

## Ручная проверка на проде (после деплоя)

1. В Filament у одного TG-источника включить welcome, указать текст.
2. Открыть бота в TG с пустой историей → `/start` → через секунду должен прийти ответ. Проверить в `chats` таблице: новой строки нет.
3. Повторить `/start` через 30 секунд → ответ **не должен** прийти (throttle).
4. Через 6 минут → снова приходит.
5. Написать обычное сообщение → чат создаётся, welcome в него не попадает, модератор видит сразу содержательный первый месседж.
6. Повторить для VK-источника: нажать «Начать» (если кнопка настроена) — ответ приходит. Написать «Начать» текстом — ответ приходит.
7. Написать «Начать» **как часть фразы** («Начать хочу с простого») — welcome **не** должен сработать (триггер только на точный матч whitelist).
8. Выключить флаг `welcome_enabled` → `/start` теперь ничего не присылает, но и чата не создаётся (чистый инбокс).

## Out of Scope (в этом PR не делаем)

- Сохранение deep-link payload из `/start ref_abc` в метаданные пользователя — отдельная задача.
- Welcome на первый контакт без `/start` (опция B из предыдущего обсуждения) — только если после продакшна окажется, что часть юзеров обходит кнопку.
- Кнопки-быстрые-ответы под welcome-сообщением (inline keyboard для TG, keyboard для VK) — перспективное расширение, но требует отдельного UI в Filament для конструктора кнопок.
- Локализация welcome_text по `from.language_code` — пока один язык на источник.

## Чеклист перед мерджем

- [ ] `StartCommandDetector` с юнит-тестами (13+ кейсов).
- [ ] `SendWelcomeMessage` с throttle через Cache.
- [ ] Правка `ProcessInboundWebhook` — ранний выход до `createMessage`.
- [ ] Filament поля для обеих групп настроек (не-VK и VK).
- [ ] `MapsSourceConnectionSettings` — маппинг в обе стороны.
- [ ] Feature-тест `WelcomeMessageTest` на 8 сценариев.
- [ ] `./vendor/bin/pint` без ошибок.
- [ ] `php artisan test --filter=Welcome` — все зелёные.
- [ ] Ручная проверка в Filament: открыть существующий TG-источник, увидеть пустые поля welcome; заполнить, сохранить, переоткрыть — значения подхватились.
- [ ] То же для VK-источника.
- [ ] Защита от побочного бага: в feature-тесте явная проверка, что с `welcome_enabled=false` и текстом `/start` чат **не создаётся** в БД (важно для чистоты инбокса).

## Риски и откат

- Если что-то сломалось — выключить `welcome_enabled` на всех источниках в БД одним UPDATE. Детектор `/start` всё равно продолжит блокировать создание мусорных чатов; если это нежелательно (модератор хочет видеть «/start» в инбоксе) — сделать второй флаг `suppress_start_command_chats` (но лучше не делать, это противоречит UX-цели).
- Откат — revert коммита. Ни схемных, ни нерepeatable миграций не трогаем.
