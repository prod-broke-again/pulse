# Рефакторинг: AI-назначение департамента чатам на основе Kickoff

> **Аудитория документа:** следующая нейросеть (Claude / другой LLM-агент), которая продолжит работу. Диалог, в котором это писалось, недоступен.
>
> **Цель документа:** полное и самодостаточное руководство. Здесь есть всё, что нужно: контекст текущей архитектуры (с путями и номерами строк), список запрещённых действий, пошаговый план из 6 PR с примерами кода, чеклист тестирования и промпт для начала работы.

---

## 1. Цель и философия

Сейчас при получении первых сообщений клиента запускается background job `GenerateChatTopicJob`, который вызывает LLM через `AiProviderInterface::generateKickoffFromClientMessages()`. LLM возвращает **четыре поля**: `topic`, `summary`, `intent_tag`, `replies[]`. В БД сохраняется только `topic` (колонка `chats.topic`). `summary` и `replies` кешируются в Redis на 7 дней и позже отдаются по запросу для правой AI-панели.

При этом у чата уже есть привязка к департаменту (`chats.department_id` — обязательный FK). Получается две параллельные категоризации: «настоящая» (department) и «косметическая» (AI-topic).

**Задача:** использовать тот же kickoff-вызов LLM, чтобы модель ещё и *выбирала подходящий департамент* из предоставленного списка. Назначение происходит автоматически при достаточной уверенности LLM. Старое поле `topic` и все сопутствующие сущности (summary/replies, AI-панель) **остаются нетронутыми** — мы только *расширяем* систему.

**Главные принципы:**

- Ничего не ломать. Все изменения аддитивны. Старый код должен продолжать работать весь переходный период.
- Поведенческое изменение закрыто feature-flag-ом, выключенным по умолчанию.
- LLM **только выбирает** из существующих департаментов. Авто-создание запрещено в этой итерации.
- Порог уверенности (confidence >= 0.7): ниже — не применяем, только логируем.
- Всё решение LLM аудируется (какой отдел предложил, с какой уверенностью, кто потом переназначил).

---

## 2. Контекст: что уже есть в коде

Все пути относительны от корня репозитория `C:\laragon\www\pulse` (в bash: `/sessions/.../mnt/pulse/`).

### 2.1. Бэкенд (Laravel/PHP)

**`app/Support/AiKickoffPrompt.php`**
Класс с константой `SYSTEM` (строки 16–31) — текст промпта. Метод `parse(string $json): AiChatKickoffDto` (строки 33–72) разбирает ответ LLM, защищаясь от ошибок: пустые поля → null, replies обрезается до 2, topic до 255 символов.

Текущий промпт требует JSON вида:
```json
{"topic":"…","summary":"…","intent_tag":"…","replies":[{"id":"r1","text":"…"},{"id":"r2","text":"…"}]}
```

**`app/Application/Ai/Dto/AiChatKickoffDto.php`**
`final readonly class` с четырьмя публичными полями: `?string $topic`, `string $summary = ''`, `?string $intentTag`, `array $replies` (list of `AiSuggestedReplyDto`).

**`app/Jobs/GenerateChatTopicJob.php`** (ShouldQueue, tries=2, timeout=30)
Триггер: вызывается когда приходят первые сообщения клиента. Берёт первые 2 client-сообщения (до 1500 символов каждое, строки 44–49), вызывает `$ai->generateKickoffFromClientMessages()` (строка 60). Результат:
- Кладёт полный DTO в `ChatAiKickoffCache::put()` (строки 63–69).
- Сохраняет **только** `chat.topic` в БД, если непустой (строки 71–85).
- Рассылает `ChatTopicGenerated` event (строки 88–92) на каналы `chat.{chatId}` и `moderator.{userId}`.

**`app/Support/ChatAiKickoffCache.php`**
TTL = 604800 (7 дней). `put()` привязывает данные к `seal_message_id` (максимальный ID сообщения на момент генерации). `getIfFresh()` вернёт данные только если `seal_message_id` не изменился — т.е. кеш автоматически инвалидируется при новых сообщениях.

**`app/Contracts/Ai/AiProviderInterface.php`**
Интерфейс AI-провайдера: `generateKickoffFromClientMessages`, `generateTopic`, `summarizeThread`, `suggestReplies`.

**`app/Services/TimewebAiService.php`** и **`app/Services/Ai/NullAiProvider.php`**
Реализации. Timeweb вызывает HTTP API `gpt-4o-mini`. NullAiProvider — заглушка, возвращает пустой DTO.

**`app/Providers/AppServiceProvider.php`** (строки 28–44)
Биндинг `AiProviderInterface` → `TimewebAiService` (либо `GPTunnelService` по `config('services.ai.default')`).

**`app/Application/Communication/Action/ChangeChatDepartment.php`** (строки 20–63)
Action-класс. Сигнатура: `run(int $chatId, int $departmentId, User $user): Chat`. Валидирует:
- чат существует;
- департамент существует, активен, принадлежит тому же `source_id`;
- у пользователя `$user` есть доступ к этому департаменту (admin или в pivot `department_user`).

**Это действие переиспользуем**, но потребуется либо «системный пользователь», либо новый метод `runAsSystem()` (см. PR 4).

**`app/Infrastructure/Persistence/Eloquent/DepartmentModel.php`**
Eloquent-модель. Уже имеет `category` (enum `DepartmentCategory`), `ai_enabled` (bool), `is_active` (bool), `slug`, `source_id`, `name`. Связи: `source()`, `chats()` (HasMany), `users()` (BelongsToMany через `department_user`).

**`app/Domains/Integration/Entity/Department.php`**
Domain Entity. Поля: `id`, `sourceId`, `name`, `slug`, `isActive`. **Не содержит** `category` и `ai_enabled` — это только в Eloquent-модели. Нам надо будет добавить `icon` и в Entity, и в Model.

**`app/Domains/Integration/Repository/DepartmentRepositoryInterface.php`**
Методы: `findById(int)`, `listBySourceId(int): list<Department>`, `persist(Department): Department`.

**`app/Infrastructure/Persistence/EloquentDepartmentRepository.php`** (строки 21–29)
`listBySourceId` → `DepartmentModel::where('source_id', $sourceId)->orderBy('id')->get()`.

**`app/Http/Resources/Api/V1/ChatResource.php`** (строки 30–79)
Отдаёт поля чата. Nested department (строки 70–79) содержит: `id`, `name`, `category`, `ai_enabled`. Также отдаёт `topic`, `category_code`, `category_label`, `ai_enabled`, `ai_badge`.

**`app/Http/Controllers/Api/V1/DepartmentController.php`** (строки 31–44)
`index(ListDepartmentsRequest)` фильтрует по `source_id`, `is_active`, при отсутствии прав admin — фильтрует по pivot `department_user`. Возвращает `id`, `name`, `slug`.

**`app/Events/ChatTopicGenerated.php`**
Broadcastable event. Поля: `chatId`, `topic`, `assignedModeratorUserId`. Каналы: `chat.{chatId}`, `moderator.{userId}`.

**`app/Http/Controllers/Api/V1/ChatAiController.php`**
Endpoints `/chats/{id}/ai/summary` и `/chats/{id}/ai/suggestions`. Оба проверяют `ChatAiKickoffCache::getIfFresh()` и либо возвращают кеш, либо на лету вызывают `summarizeThread()` / `suggestReplies()`.

**Миграции departments (существующие):**
- `database/migrations/2025_02_17_000002_create_departments_table.php` — базовая таблица.
- `database/migrations/2026_04_12_100000_add_department_category_and_ai_enabled.php` — добавила `category` (enum `support|registration|tech|ethics|other`) и `ai_enabled` (default true).

**Миграции chats:**
- `database/migrations/2025_02_17_000003_create_chats_table.php` — базовая.
- `database/migrations/2026_02_23_052418_add_topic_to_chats_table.php` — добавила nullable `topic`.

### 2.2. Фронтенд (Vue/TypeScript, папка `apps/pulse-desktop/src`)

**`types/dto/chat.types.ts`** — `interface ApiChat` с полями `topic?`, `department?: {id, name}`, `category_code?`, `category_label?`, `ai_enabled?`, `ai_badge?`.

**`stores/chatStore.ts`** — Pinia store.
- `applyChatTopicFromRealtime(chatId, topic)` (строки 251–266) — ставит `topic` на чат при получении WS-события.
- `changeDepartment(chatId, departmentId)` (строки 235–241) — вызов API.
- `bumpChatFromRealtime` (строки 115–125).

**`utils/mappers.ts`** (строки 32–82) — `mapChatToConversation(chat, activeId)` мапит `ApiChat` → внутренний `Conversation`. `topic` берётся из `chat.topic`, `department` из `chat.department?.name ?? ''`.

**`App.vue`** (строки 113–116) — `onChatTopicGenerated(payload)` вызывает `chatStore.applyChatTopicFromRealtime`.

**`components/chat/ChatHeader.vue`** (строки 38, 100–114, 122–141) — department-селектор. Загружает список через `fetchDepartments(sourceId)`, переключает через emit `change-department`.

**`components/chat/InboxPanel.vue`** — список чатов, фильтры. Отображает превью, `topic` пока тут не в заметном месте, но присутствует в Conversation.

**`components/chat/ThreadAiPanel.vue`** — **НЕ ТРОГАТЬ**. Использует `fetchAiSummary()` и `fetchAiSuggestions()` (из `api/ai.ts`). Показывает `summary` и `replies` из kickoff-кеша. Рефактор department никак не должен это задеть.

**`api/chats.ts`** (строки 42–47) — `changeChatDepartment(chatId, departmentId)` → PATCH `/chats/{id}/department`.

**`api/ai.ts`** — `fetchAiSummary(chatId)`, `fetchAiSuggestions(chatId)`.

---

## 3. Критические «НЕ ДЕЛАЙ»

Нарушение этих пунктов сломает работающие фичи. Проверь себя перед каждым изменением.

1. **НЕ удаляй** `AiChatKickoffDto`, его поля `summary`, `intentTag`, `replies`. Они используются `ThreadAiPanel.vue` через `/chats/{id}/ai/summary` и `/chats/{id}/ai/suggestions`.
2. **НЕ удаляй** колонку `chats.topic` в этом рефакторинге. Удаление — отдельный PR после 2–4 недель наблюдений на feature-flag.
3. **НЕ удаляй** `ChatTopicGenerated` event, `applyChatTopicFromRealtime`, `bumpChatFromRealtime` в этом рефакторинге.
4. **НЕ разрешай** LLM создавать новые департаменты. Только выбор из предоставленного списка. Если LLM вернёт что-то нераспознанное — игнорируем, не применяем.
5. **НЕ пиши** новый action для смены департамента. Используй существующий `ChangeChatDepartment`.
6. **НЕ игнорируй** порог уверенности. Если `confidence < 0.7` — сохраняем в audit-поля, но `department_id` не меняем.
7. **НЕ трогай** `ThreadAiPanel.vue`, `ChatAiController.php`, `TimewebAiService::summarizeThread()`, `TimewebAiService::suggestReplies()`.
8. **НЕ забывай** валидировать иконку на бэкенде по whitelist. LLM может галлюцинировать имя иконки; при невалидном — fallback.
9. **НЕ хардкодь** имена иконок по двум местам. Whitelist должен быть один, в PHP-константе, фронт потребляет его через API или константу в общем коде.
10. **НЕ запускай** миграции drop-колонок в этом рефакторинге. Только add.
11. **НЕ используй** обычного живого пользователя для AI-авто-назначения. Нужен либо system-user, либо отдельный метод action-а (см. PR 4).
12. **НЕ меняй сигнатуру** `AiProviderInterface::generateKickoffFromClientMessages(string): AiChatKickoffDto`. `NullAiProvider` должен продолжать работать как заглушка.

---

## 4. План работ: 6 PR, последовательно

Каждый PR — отдельный атомарный коммит/MR. После каждого проект должен запускаться и проходить тесты. PR 4 и далее зависят от предыдущих.

### PR 1 — Миграция `departments.icon` + бэкфил + whitelist

**Цель:** добавить колонку `icon`, заполнить её по `category`, пробросить на фронт.

**Файлы к созданию/изменению:**

1. **Новая миграция**: `database/migrations/2026_04_21_000001_add_icon_to_departments_table.php`
   ```php
   Schema::table('departments', function (Blueprint $table) {
       $table->string('icon', 64)->nullable()->after('category');
   });
   ```
   В секции `down()` — `dropColumn('icon')`.

2. **Новая миграция-бэкфил**: `database/migrations/2026_04_21_000002_backfill_department_icons.php`
   Заполняет по `category`:
   - `support` → `Headphones`
   - `registration` → `UserPlus`
   - `tech` → `Wrench`
   - `ethics` → `Scale`
   - `other` → `Building2`
   Использует `DB::table('departments')->where('category', ...)->update(['icon' => ...])`. В `down()` очистка обратно в null.

3. **Новая PHP-константа/enum**: `app/Support/DepartmentIcons.php`
   ```php
   final class DepartmentIcons {
       public const ALLOWED = [
           'Building2', 'Users', 'ShoppingCart', 'Headphones', 'CreditCard',
           'Truck', 'Phone', 'Mail', 'FileText', 'BarChart3', 'MessageCircle',
           'Settings', 'Wrench', 'Scale', 'UserPlus', 'Shield', 'Package',
           'Briefcase', 'HelpCircle', 'AlertTriangle', 'Heart', 'Globe',
           'Tag', 'Zap', 'Clipboard',
       ];
       public const FALLBACK = 'Building2';

       public static function isValid(string $icon): bool {
           return in_array($icon, self::ALLOWED, true);
       }

       public static function normalize(?string $icon): string {
           return ($icon && self::isValid($icon)) ? $icon : self::FALLBACK;
       }
   }
   ```

4. **Обновить** `app/Infrastructure/Persistence/Eloquent/DepartmentModel.php`:
   - Добавить `'icon'` в `$fillable` (если используется) или убедиться что колонка доступна.

5. **Обновить** `app/Domains/Integration/Entity/Department.php`:
   - Добавить публичное поле `public readonly ?string $icon`.
   - Обновить конструктор и все места создания Entity (hydration в репозитории).

6. **Обновить** `app/Infrastructure/Persistence/EloquentDepartmentRepository.php`:
   - Во всех методах (`findById`, `listBySourceId`, `persist`) пробрасывать `icon` между Model и Entity.

7. **Обновить** `app/Http/Resources/DepartmentResource.php` (если существует) и **`app/Http/Resources/Api/V1/ChatResource.php`**:
   - В nested department (строки 70–79 ChatResource) добавить `'icon' => DepartmentIcons::normalize($dept->icon)`.

8. **Обновить** `app/Http/Controllers/Api/V1/DepartmentController.php`:
   - В ответе `index()` добавить `icon`.

9. **Опционально** — добавить endpoint `GET /api/v1/department-icons` или прямо в ответ `/departments` положить метаданные. Но можно и просто на фронте константой дублировать (см. PR 5).

10. **Валидация** при создании/редактировании департамента через админку: в `StoreDepartmentRequest`/`UpdateDepartmentRequest` (если есть) — `icon` должен быть `nullable|string|in:<whitelist>`. Если форм нет — добавить проверку в `persist()`.

**Проверка PR 1:**
- `php artisan migrate` проходит.
- `DepartmentModel::all()` возвращает департаменты с заполненной `icon`.
- `GET /api/v1/chats/{id}` возвращает `department.icon`.
- Существующие тесты department-а не падают.

---

### PR 2 — Audit-поля на `chats`

**Цель:** возможность логировать решения AI отдельно от применённого `department_id`.

**Файлы:**

1. **Новая миграция**: `database/migrations/2026_04_21_000003_add_ai_department_audit_to_chats.php`
   ```php
   Schema::table('chats', function (Blueprint $table) {
       $table->foreignId('ai_suggested_department_id')->nullable()
             ->after('department_id')
             ->constrained('departments')->nullOnDelete();
       $table->decimal('ai_department_confidence', 3, 2)->nullable()
             ->after('ai_suggested_department_id');
       $table->timestamp('ai_department_assigned_at')->nullable()
             ->after('ai_department_confidence');
       $table->foreignId('department_reassigned_by_user_id')->nullable()
             ->after('ai_department_assigned_at')
             ->constrained('users')->nullOnDelete();
   });
   ```
   В `down()` — dropForeign + dropColumn для всех четырёх.

2. **Обновить** `app/Infrastructure/Persistence/Eloquent/ChatModel.php`:
   - Добавить поля в `$fillable`/`$casts`: `ai_department_assigned_at` → datetime.

3. **Обновить** `app/Domains/Communication/Entity/Chat.php`:
   - Добавить 4 новых поля (все nullable).
   - Обновить конструктор.

4. **Обновить** `app/Infrastructure/Persistence/EloquentChatRepository.php`:
   - Пробросить новые поля в hydration и `persist()`.

5. **Обновить** `app/Http/Resources/Api/V1/ChatResource.php`:
   - Отдавать `ai_suggested_department_id`, `ai_department_confidence`, `ai_department_assigned_at`. (`department_reassigned_by_user_id` — скорее внутреннее, можно не отдавать.)

6. **Обновить хук на смену департамента вручную**: в контроллере, который вызывает `ChangeChatDepartment` (вероятно `ChatController@changeDepartment`), после успешной смены — если `ai_suggested_department_id != null && current_user_id != system_user_id`, установить `department_reassigned_by_user_id = current_user_id`. Это позволит считать метрику точности LLM.

**Проверка PR 2:**
- Миграция обратима.
- Новые поля nullable — существующие чаты не задеты.
- `ChatResource` продолжает работать.

---

### PR 3 — Расширение `AiChatKickoffDto` и `AiKickoffPrompt`

**Цель:** LLM в ответе kickoff дополнительно выбирает департамент. Старое API kickoff-а **полностью сохраняется**, новые поля опциональны.

**Файлы:**

1. **Обновить** `app/Application/Ai/Dto/AiChatKickoffDto.php`:
   ```php
   final readonly class AiChatKickoffDto {
       public function __construct(
           public ?string $topic = null,
           public string $summary = '',
           public ?string $intentTag = null,
           public array $replies = [],
           public ?int $suggestedDepartmentId = null,
           public ?float $confidence = null,
       ) {}
   }
   ```
   `public array $replies` остаётся (list of `AiSuggestedReplyDto`).

2. **Переписать** `app/Support/AiKickoffPrompt.php`.

   **Новый SYSTEM prompt** (учесть: список департаментов подставляется динамически):
   ```
   Ты помощник службы поддержки. По первым сообщениям клиента ты должен:
   1. Сформулировать кратко тему (topic, 2-5 слов).
   2. Сделать короткое саммари (summary, 2-4 предложения на русском).
   3. Определить одну короткую метку намерения (intent_tag).
   4. Предложить 2 возможных ответа (replies, вежливый тон).
   5. Выбрать наиболее подходящий департамент из ПРЕДОСТАВЛЕННОГО списка
      по его id. Если ни один не подходит — верни null. Новые департаменты
      НЕ создавай.
   6. Оцени уверенность своего выбора числом от 0 до 1 (confidence).

   Верни ТОЛЬКО валидный JSON вида:
   {
     "topic": "...",
     "summary": "...",
     "intent_tag": "...",
     "replies": [{"id":"r1","text":"..."},{"id":"r2","text":"..."}],
     "suggested_department_id": 5,
     "confidence": 0.92
   }

   Правила:
   - topic до 255 символов, по-русски.
   - suggested_department_id — только из списка, переданного в user-сообщении.
     Если сомневаешься — верни null и confidence <= 0.5.
   - confidence отражает, насколько уверенно ты выбрал департамент:
     0.9+ — очень уверенно, явное совпадение;
     0.7–0.9 — уверенно;
     0.5–0.7 — есть сомнения;
     < 0.5 — плохо подходит.
   - Никакого текста вне JSON.
   ```

   **Метод `buildUserMessage(string $messagesText, array $departments): string`** формирует user-сообщение:
   ```
   Первые сообщения клиента:
   ---
   {$messagesText}
   ---

   Список департаментов (выбирай id только отсюда):
   [
     {"id": 1, "name": "Продажи", "category": "support", "description": "заказы, оплата, доставка"},
     {"id": 2, "name": "Техподдержка", "category": "tech", "description": "баги, настройка, интеграции"},
     ...
   ]
   ```
   `description` на первом этапе бери по мапе `category → text` (хардкод в коде), позже можно вынести в поле `departments.description`.

3. **Обновить парсер** `AiKickoffPrompt::parse()`:
   - Считывать `suggested_department_id` (int | null, проверка что это число).
   - Считывать `confidence` (float, clamp в [0, 1], null если отсутствует или не число).
   - Остальные поля парсить как раньше.
   - При parse-ошибке — возвращать DTO со всеми null (текущее поведение сохранить).

4. **Обновить сигнатуру провайдера**:
   В `app/Contracts/Ai/AiProviderInterface.php` расширить:
   ```php
   public function generateKickoffFromClientMessages(
       string $messagesText,
       array $departments = []
   ): AiChatKickoffDto;
   ```
   Второй аргумент **опциональный** (default `[]`) — это важно для обратной совместимости. При пустом массиве логика та же, что была.

5. **Обновить реализации:**
   - `app/Services/TimewebAiService.php`: передавать `$departments` в user-message через `AiKickoffPrompt::buildUserMessage()`. При пустом массиве — старое поведение (без блока департаментов в промпте).
   - `app/Services/Ai/NullAiProvider.php`: принимать второй параметр, игнорировать, возвращать пустой DTO.
   - Другие реализации провайдера (если есть, например GPTunnelService).

**Проверка PR 3:**
- Unit-тест `AiKickoffPrompt::parse()` для нового JSON.
- Unit-тест: при `null` в `suggested_department_id` — DTO имеет `null`.
- Unit-тест: при `confidence = 1.5` — clamp до 1.0.
- Unit-тест: некорректный JSON → DTO со всеми null.
- Существующий вызов job-а (без `$departments`) продолжает работать.

---

### PR 4 — `GenerateChatTopicJob`: применение департамента + feature flag + аудит

**Цель:** использовать расширенный kickoff — если LLM выбрал department с достаточной уверенностью и feature-flag включён, сменить `department_id` чата через существующий action.

**Файлы:**

1. **Создать** `config/features.php`:
   ```php
   return [
       'ai_department_assignment' => env('FEATURE_AI_DEPARTMENT_ASSIGNMENT', false),
       'ai_department_confidence_threshold' => env('FEATURE_AI_DEPT_CONFIDENCE', 0.7),
   ];
   ```
   Добавить соответствующие строки в `.env.example`.

2. **Решить вопрос system-user**. Два варианта — выбери один:

   **Вариант A (рекомендуется):** добавить в `ChangeChatDepartment` метод:
   ```php
   public function runAsSystem(int $chatId, int $departmentId): Chat {
       // те же проверки, кроме permission check
   }
   ```
   Вынести общую часть в приватный метод, `run()` делает permission check + зовёт общую часть, `runAsSystem()` — только общую часть.

   **Вариант B:** создать seed-миграцию, добавляющую технического пользователя с email `ai-system@internal`, и передавать его в существующий `run()`. Минус: нужно либо дать ему доступ ко всем департаментам, либо разрешить обход в permission check по роли.

3. **Обновить** `app/Jobs/GenerateChatTopicJob.php`:

   После получения `$kickoff` (текущая строка ~60):
   ```php
   $departments = [];
   if (config('features.ai_department_assignment')) {
       $departments = $departmentRepo->listBySourceId($chat->sourceId);
       // отфильтруй is_active === true
   }

   $kickoff = $ai->generateKickoffFromClientMessages($messagesText, $departments);

   // ... существующее сохранение topic + cache + broadcast ...

   // Новое: логирование и возможное применение департамента.
   if (config('features.ai_department_assignment')
       && $kickoff->suggestedDepartmentId !== null
       && $kickoff->confidence !== null) {

       // Валидируем, что suggested id реально в списке departments
       $validIds = array_map(fn($d) => $d->id, $departments);
       if (!in_array($kickoff->suggestedDepartmentId, $validIds, true)) {
           // LLM галлюцинировал — лог и всё.
           Log::warning('AI suggested invalid department id', [...]);
       } else {
           $chat->aiSuggestedDepartmentId = $kickoff->suggestedDepartmentId;
           $chat->aiDepartmentConfidence = $kickoff->confidence;
           $chatRepo->persist($chat);

           $threshold = config('features.ai_department_confidence_threshold', 0.7);
           $currentDeptId = $chat->departmentId;
           $suggestedId = $kickoff->suggestedDepartmentId;

           // Не переназначаем, если уже совпадает или ниже порога
           if ($kickoff->confidence >= $threshold
               && $currentDeptId !== $suggestedId) {
               try {
                   $changeChatDepartment->runAsSystem($chat->id, $suggestedId);
                   // обновить ai_department_assigned_at
                   $chat->aiDepartmentAssignedAt = now();
                   $chatRepo->persist($chat);
               } catch (\Throwable $e) {
                   Log::warning('AI dept auto-assign failed', [
                       'chat_id' => $chat->id,
                       'suggested' => $suggestedId,
                       'error' => $e->getMessage(),
                   ]);
               }
           }
       }
   }
   ```

4. **DI в Job-е**. Добавить в конструктор/handle:
   - `DepartmentRepositoryInterface $departmentRepo`
   - `ChangeChatDepartment $changeChatDepartment`
   - `ChatRepositoryInterface $chatRepo`

5. **Не трогать** existing cache-запись и `ChatTopicGenerated` event. Они продолжают работать как были.

**Дополнительный хук в `ChangeChatDepartment::run()` (не `runAsSystem()`):**
Если чат имеет `ai_suggested_department_id != null` и вызов происходит от реального пользователя, и новое `departmentId != ai_suggested_department_id`, — записать `department_reassigned_by_user_id = user.id`. Это метрика «человек переназначил после AI».

**Проверка PR 4:**
- Feature flag OFF (default): всё работает как раньше, audit-поля заполняются null.
- Feature flag ON, confidence >= 0.7: `department_id` меняется, `ai_department_assigned_at` проставляется.
- Feature flag ON, confidence < 0.7: `department_id` НЕ меняется, но `ai_suggested_department_id` и `ai_department_confidence` записываются.
- LLM вернул невалидный id: ничего не применяем, warning в лог.
- Job падает в середине → retry (tries=2) идемпотентен (повторная попытка не ломает).

---

### PR 5 — Фронт: рендер иконки департамента

**Цель:** показать lucide-иконку в плашках департамента. `topic` пока НЕ удаляем.

**Файлы:**

1. **Создать** `apps/pulse-desktop/src/constants/departmentIcons.ts`:
   ```ts
   import {
     Building2, Users, ShoppingCart, Headphones, CreditCard,
     Truck, Phone, Mail, FileText, BarChart3, MessageCircle,
     Settings, Wrench, Scale, UserPlus, Shield, Package,
     Briefcase, HelpCircle, AlertTriangle, Heart, Globe,
     Tag, Zap, Clipboard,
   } from 'lucide-vue-next';
   import type { Component } from 'vue';

   export const DEPARTMENT_ICON_MAP: Record<string, Component> = {
     Building2, Users, ShoppingCart, Headphones, CreditCard,
     Truck, Phone, Mail, FileText, BarChart3, MessageCircle,
     Settings, Wrench, Scale, UserPlus, Shield, Package,
     Briefcase, HelpCircle, AlertTriangle, Heart, Globe,
     Tag, Zap, Clipboard,
   };

   export const FALLBACK_DEPARTMENT_ICON: Component = Building2;

   export function resolveDepartmentIcon(name?: string | null): Component {
     if (!name) return FALLBACK_DEPARTMENT_ICON;
     return DEPARTMENT_ICON_MAP[name] ?? FALLBACK_DEPARTMENT_ICON;
   }
   ```

   > **Важно:** whitelist в этом файле должен **точно совпадать** с `DepartmentIcons::ALLOWED` из PR 1. Рассмотри генерацию из одного источника (например, `php artisan` команда пишет JSON, который фронт импортирует).

2. **Обновить** `apps/pulse-desktop/src/types/dto/chat.types.ts`:
   ```ts
   export interface ApiChat {
     ...
     department?: { id: number; name: string; icon?: string | null };
     ai_suggested_department_id?: number | null;
     ai_department_confidence?: number | null;
     ai_department_assigned_at?: string | null;
     ...
   }
   ```

3. **Обновить** `apps/pulse-desktop/src/utils/mappers.ts`:
   - В `mapChatToConversation` пробросить `department.icon` в объект `Conversation`.
   - Обновить внутренний тип `Conversation` (скорее всего в том же файле или `types/conversation.ts`).

4. **Обновить** `apps/pulse-desktop/src/components/chat/ChatHeader.vue`:
   - Импорт `resolveDepartmentIcon`.
   - В шаблоне рядом с именем департамента рендерить `<component :is="resolveDepartmentIcon(department.icon)" class="w-4 h-4" />`.

5. **Обновить** `apps/pulse-desktop/src/components/chat/InboxPanel.vue`:
   - Аналогично — в плашке департамента рядом с превью.

6. **НЕ трогать**: `applyChatTopicFromRealtime`, `chatStore` логику topic, `App.vue` listener, `ThreadAiPanel.vue`. Поле `topic` остаётся в `ApiChat`, `Conversation` и рендере.

**Проверка PR 5:**
- Для каждого департамента в UI видна правильная иконка.
- Неизвестная иконка (несуществующая в map) → fallback `Building2`, не краш.
- Все существующие тесты проходят.
- Визуально страница собирается без ошибок TypeScript.

---

### PR 6 — Включение feature-flag-а и удаление topic (spätere)

**Этот PR не делай сразу.** Делается через 2–4 недели после деплоя PR 1–5 в прод.

**План:**

1. Включить `FEATURE_AI_DEPARTMENT_ASSIGNMENT=true` в прод `.env`.
2. Наблюдать метрики 2–4 недели:
   - Сколько чатов авто-назначены (по `ai_department_assigned_at IS NOT NULL`).
   - Средний `ai_department_confidence`.
   - % переназначений человеком (`department_reassigned_by_user_id IS NOT NULL`) — если > 30%, откатить и улучшить промпт.
   - % ошибок в job-логе.
3. Если всё хорошо — **отдельный PR** удаляет topic:
   - Миграция `drop column chats.topic`.
   - Убрать `topic` из `ChatModel`, `Chat` Entity, `ChatRepository`, `ChatResource`.
   - Убрать `chat.topic` запись и `ChatTopicGenerated` event из `GenerateChatTopicJob`.
   - Удалить `ChatTopicGenerated` event класс.
   - Фронт: убрать `topic` из `ApiChat`, `applyChatTopicFromRealtime`, listener в `App.vue`, использование в `mappers.ts` и компонентах.
4. Job можно переименовать в `GenerateChatKickoffJob` для ясности (опционально).

---

## 5. Тестовый чеклист

### Бэкенд (Pest / PHPUnit)

- [ ] Миграция PR 1 вверх и вниз проходит без ошибок.
- [ ] Миграция PR 2 вверх и вниз проходит без ошибок.
- [ ] `DepartmentIcons::normalize()` — валидные → pass-through, невалидные → fallback.
- [ ] `AiKickoffPrompt::parse()` — корректно разбирает новые поля.
- [ ] `AiKickoffPrompt::parse()` — защита от мусора: `confidence=abc` → null.
- [ ] `GenerateChatTopicJob` при флаге OFF — старое поведение, никаких изменений в `department_id`.
- [ ] `GenerateChatTopicJob` при флаге ON + высокой confidence — меняет `department_id`.
- [ ] `GenerateChatTopicJob` при флаге ON + низкой confidence — НЕ меняет, но пишет audit.
- [ ] `GenerateChatTopicJob` при галлюцинации id — NE crash, warning в лог.
- [ ] `ChangeChatDepartment::run()` от реального пользователя после AI-предложения — пишет `department_reassigned_by_user_id`.
- [ ] `ChangeChatDepartment::runAsSystem()` не требует user, но валидирует source_id и активность.
- [ ] `NullAiProvider` принимает второй параметр, не падает.
- [ ] `ChatResource` отдаёт `department.icon` и audit-поля.

### Фронтенд

- [ ] TypeScript компилируется без ошибок.
- [ ] `resolveDepartmentIcon('Headphones')` → корректный компонент.
- [ ] `resolveDepartmentIcon('NonExistent')` → `Building2`.
- [ ] `ChatHeader.vue` рендерит иконку.
- [ ] `InboxPanel.vue` рендерит иконку.
- [ ] `ThreadAiPanel.vue` продолжает работать: summary и replies отображаются.

### Ручное тестирование в staging

- [ ] Создать новый чат с очевидной темой («хочу оформить заказ») — при флаге ON LLM должен выбрать «Продажи».
- [ ] Создать чат с неоднозначной темой — AI должен вернуть низкую confidence, department НЕ меняется.
- [ ] Переназначить департамент вручную после AI — в БД появляется `department_reassigned_by_user_id`.
- [ ] Флаг OFF → старое поведение как до рефакторинга.

---

## 6. Вопросы, на которые пусть ответит человек

Эти решения **не принимай сам** — спроси пользователя через AskUserQuestion или в тексте:

1. **System-user для AI-авто-назначения**: вариант A (runAsSystem в action) или B (реальный system-user в БД)?
2. **Описания департаментов в промпте**: хардкод `category → description` на первой итерации или добавлять колонку `departments.description`?
3. **Список иконок**: предложенные 25 подходят или нужно добавить/убрать?
4. **Порог confidence**: 0.7 ОК или поставить 0.8 для осторожности?
5. **Endpoint `/api/v1/department-icons`**: делать или хватит дубликата константы на фронте в PR 5?

---

## 7. Промпт для старта работы (скопируй это в новую сессию нейросети)

> Ты работаешь над Laravel+Vue проектом в `C:\laragon\www\pulse`. У тебя есть документ `docs/REFACTOR_PLAN_AI_DEPARTMENT.md` — это детальный план рефакторинга. Прочти его полностью перед началом работы.
>
> Твоя задача: реализовать PR 1 из этого плана (миграция `departments.icon` + бэкфил + whitelist + обновление модели/resource/controller). НИЧЕГО из других PR не трогай — они в следующих итерациях.
>
> Перед началом работы:
> 1. Прочти файл `docs/REFACTOR_PLAN_AI_DEPARTMENT.md` целиком.
> 2. Прочти файлы, упомянутые в разделе «Контекст» плана, чтобы проверить актуальность указанных строк (код мог измениться).
> 3. Задай уточняющие вопросы из раздела «Вопросы пользователю», если они критичны для PR 1 (для PR 1 критичен только вопрос № 3 про список иконок).
> 4. Составь TodoList и пошагово выполни все пункты PR 1.
> 5. Не делай коммит сам — после завершения покажи список изменённых файлов и результат `php artisan migrate --pretend`.
>
> Если найдёшь в коде расхождение с планом (например, файла нет по указанному пути), — СООБЩИ, не импровизируй.
>
> Критические «НЕ ДЕЛАЙ» из раздела 3 документа — соблюдай неукоснительно.

---

## 8. Контактные точки для последующих сессий

Каждый следующий PR начинается с промпта такого же формата, только меняется номер PR:

> «Реализуй PR N из `docs/REFACTOR_PLAN_AI_DEPARTMENT.md`. Предыдущие PR уже в main. Прочти план целиком, проверь состояние кода, задай уточняющие вопросы, составь TodoList, выполни.»

---

**Версия документа:** 1.0 • **Дата:** 2026-04-20 • **Последовательность PR:** 1 → 2 → 3 → 4 → 5, затем через 2–4 недели — 6.
