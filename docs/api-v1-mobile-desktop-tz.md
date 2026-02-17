ТЗ: API v1 для desktop/mobile клиента
Цель
Вынести ключевую чат-логику из Filament/Livewire в REST API v1 + токен-авторизацию, чтобы desktop/mobile клиенты работали нативно и быстро.

1) API и авторизация (P0, обязательно)
1.1 Auth (Sanctum токены)
Эндпоинты:

POST /api/v1/auth/login — логин, выдача токена.

POST /api/v1/auth/logout — отзыв текущего токена.

GET /api/v1/auth/me — профиль + роли + доступные source/department.

Формат:

Bearer token в заголовке Authorization.

Все ответы строго JSON (application/json).

1.2 Чаты
Эндпоинты:

GET /api/v1/chats

Фильтры: tab=my|unassigned|all, source_id, department_id, search, status=open|closed.

Пагинация: page, per_page.

GET /api/v1/chats/{id}/messages

Пагинация: курсорная (before_id) или классическая (page/per_page).

POST /api/v1/chats/{id}/send

Тело: text, attachments[] (опционально), client_message_id (UUID для идемпотентности).

Минимальные действия:

POST /api/v1/chats/{id}/assign-me

POST /api/v1/chats/{id}/close

1.3 Права доступа
Те же правила, что в Filament (через Policy):

admin видит все.

moderator видит только чаты своих source (и опционально department).

Проверка прав Gate::authorize('view', $chat) на каждый запрос.

2) Медиа (P1, сразу после P0)
2.1 Хранилище
Подключить spatie/laravel-medialibrary.

Диск: s3 (MinIO), для локальной разработки local / public.

2.2 Входящие (Webhook)
Сценарий:

Telegram/VK webhook присылает файл.

Сервер скачивает файл в tmp.

Сохраняет в media library ($message->addMedia(...)).

В JSON-ответе сообщения отдавать полный URL и превью.

2.3 Исходящие
Эндпоинты:

POST /api/v1/uploads — загрузка файла модератором (возвращает media_id).

POST /api/v1/chats/{id}/send — в поле attachments передаем массив media_id.

3) Push-уведомления FCM (P1)
3.1 Таблица device_tokens
Поля: id, user_id, token, platform (ios|android|desktop|web), last_seen_at, created_at.

3.2 API
POST /api/v1/devices/register-token

DELETE /api/v1/devices/{token}

3.3 Логика отправки
Job при создании входящего сообщения (CreatedMessage event):

Найти всех модераторов, имеющих доступ к этому чату.

Исключить тех, кто сейчас Online (через Reverb presence channel или cache).

Отправить FCM push с payload: { "chat_id": 1, "action": "new_message" }.

4) Шаблоны ответов (P1)
4.1 Структура
Таблица canned_responses:

id, source_id (nullable, если общий), code (шорткат), text, is_active.

4.2 API
GET /api/v1/canned-responses (фильтр по source_id текущего чата).

5) Typing indicators (P2)
Через Reverb (Whisper events):

Канал: private-chat.{chatId}.

События: client-typing (отправляет клиент приложения).

Бэкенд слушает вебхук от Telegram (chat_action) -> транслирует в Reverb событие typing.

6) Технические стандарты и Правила разработки (Code Style & Guidelines)
6.1 Архитектура (DDD + Actions)
Controllers должны быть "тонкими". Никакой бизнес-логики в контроллерах.

Плохо: Chat::where(...)->get() внутри контроллера.

Хорошо: $action->run($dto) или вызов репозитория.

Request Validation: Вся валидация строго через FormRequest классы.

API Resources: Ответы всегда оборачивать в JsonResource (Laravel Resources). Не возвращать Eloquent-модели напрямую, чтобы не светить структуру БД.

6.2 Стиль кода (PHP 8.4)
Strict Types: В начале каждого файла declare(strict_types=1);.

Типизация: Обязательно указывать типы аргументов и возвращаемых значений.

Пример: public function execute(int $chatId, string $text): MessageDTO

Constructor Promotion: Использовать сокращенный синтаксис в конструкторах.

Null Safety: Использовать ?-> вместо if ($obj !== null).

Formatting: Код должен проходить проверку Laravel Pint (PSR-12).

6.3 Формат API ответов
Все успешные ответы должны возвращать данные в ключе data (стандарт JsonResource).
Ошибки должны соответствовать единому формату (App\Exceptions\Handler):

JSON

// Успех (200 OK)
{
  "data": {
    "id": 1,
    "text": "Hello",
    "sender": { ... }
  },
  "meta": { "page": 1 } // Если есть пагинация
}

// Ошибка (422 Unprocessable Entity)
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["Email is required."]
  },
  "code": "VALIDATION_ERROR"
}
6.4 Git Flow и Коммиты
Ветки: feature/task-name или fix/issue-name. В main только через Pull Request.

Сообщения: Conventional Commits.

feat: add api endpoint for chat history

fix: resolve double webhook processing

refactor: move logic to domain action

Review: PR не вливается без аппрува и прохождения CI (тесты + Pint).

6.5 Документация (OpenAPI)
Использовать Scribe или Swagger-аннотации прямо в контроллерах.

Каждое изменение API должно отражаться в docs/openapi.yaml. Фронтенд-разработчик не должен гадать, какие поля приходят.

6.6 Производительность (N+1)
В API ресурсах строго следить за ленивой загрузкой (N+1 query problem).

Использовать $chat->loadMissing(['source', 'lastMessage']) перед отдачей в Resource.

Использовать Larastan (level 5+) для отлова ошибок типов.

Definition of Done (Критерии готовности задачи)
Код написан по стандартам (п. 6.2).

Написан Feature-тест, покрывающий позитивный и негативный сценарии.

API Resource возвращает только необходимые поля.

Обновлен файл спецификации API (Swagger/Scribe).

Пройден Code Review.