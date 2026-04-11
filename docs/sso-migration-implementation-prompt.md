# Prompt: Pulse -> ACHPP ID SSO Migration

Ты senior full-stack engineer (Laravel/PHP/Vue), работаешь в production-кодовой базе.

Твоя задача: спроектировать и реализовать миграцию проекта **Pulse** на единую авторизацию через **ACHPP ID** (IdP), с минимальными рисками для текущего API и realtime.

---

## Engineering standards (обязательно)

- PHP 8.3+
- SOLID, DRY, KISS, YAGNI
- PSR-12, strict typing, чистая архитектура
- DTO, Enums, FormRequest, API Resources
- Repository + Service + Domain layers (без жирных контроллеров)
- Laravel / PHP / Vue best practices
- Без магии, с читаемыми именами и явными контрактами

---

## Ключевые архитектурные требования

1. **Связь аккаунтов только через `id_user_uuid`**
   - Это primary идентификатор внешнего пользователя из ACHPP ID.
   - Email использовать только как fallback для миграции, но не как источник истины.

2. **SSO Exchange endpoint в Pulse**
   - Реализовать `POST /api/v1/auth/sso/exchange`.
   - Pulse принимает access token от ID, валидирует его через API ID, получает профиль пользователя.
   - Pulse выдает локальный Sanctum token для существующего API слоя.
   - Цель: не ломать текущую защиту API и авторизацию websocket/realtime.

3. **Явное разделение Authentication vs Authorization**
   - Пользователь может быть аутентифицирован через ID, но не иметь прав в Pulse.
   - Для пользователей без роли `admin|moderator` возвращать корректный `403 Forbidden`.

4. **Удаление локального логина/регистрации в Pulse**
   - Убрать/деактивировать локальную регистрацию и парольный вход.
   - В UI оставить кнопку: `Войти через АЧПП ID`.
   - Все операции профиля/безопасности аккаунта делаются в ID.

---

## Edge cases (обязательно включить в решение)

### 1) Global Logout / Access Revocation

Сценарий: модератор заблокирован в ID, но локальный токен Pulse еще активен.

Решение:
- В Pulse добавить webhook endpoint, например:
  - `POST /api/webhooks/id/user-revoked`
- По `id_user_uuid` принудительно инвалидировать все локальные Sanctum токены пользователя.
- Добавить валидацию подписи вебхука и защиту от replay.

### 2) Profile Data Sync

Сценарий: в ID изменили имя/аватар, Pulse это не знает до relogin.

Решение:
- Добавить webhook:
  - `POST /api/webhooks/id/user-updated`
- Делать фоновый upsert `users` в Pulse по `id_user_uuid`.
- Обновлять display fields (name, avatar, email-mirror при необходимости).

### 3) Миграция и склейка пользователей

Сценарий: автоматическая склейка по email не покрывает 100% кейсов.

Решение:
- Реализовать безопасный ручной механизм:
  - либо artisan-команда,
  - либо минимальная админ-страница.
- Возможность вручную связать `pulse.user` с конкретным `id_user_uuid`.
- Логирование всех ручных действий (audit trail).

---

## Scope of implementation

### Backend (Pulse)

Сделай:

1. Миграцию users:
   - добавить `id_user_uuid` (unique + indexed),
   - при необходимости поля синхронизации профиля (`id_email`, `avatar_url`, timestamps sync).

2. Новый auth flow:
   - `POST /api/v1/auth/sso/exchange`
   - `GET /api/v1/auth/me` (совместимый формат)
   - `POST /api/v1/auth/logout` (локальный токен)

3. Service-layer:
   - `SsoExchangeService`
   - `IdIdentityClient` (HTTP-клиент к ACHPP ID)
   - `UserUpsertFromIdService`
   - `TokenIssueService`

4. Роли/доступ:
   - централизованная проверка `admin|moderator`
   - корректные ответы 401/403

5. Webhooks:
   - `id.user-revoked`
   - `id.user-updated`
   - подпись, валидация, идемпотентность

6. Деактивация legacy auth:
   - отключить/удалить password login/register routes для Pulse UI/API
   - оставить совместимые точки только где нужно для миграции

### Frontend (Pulse web + mobile)

Сделай:

1. Заменить локальный login на кнопку `Войти через АЧПП ID`.
2. Реализовать SSO callback flow:
   - получить токен ID
   - вызвать `/api/v1/auth/sso/exchange`
   - сохранить локальный токен Pulse
3. Удалить/скрыть регистрацию/восстановление пароля/2FA из Pulse UI.
4. Добавить graceful UX для 403 (нет прав модератора).

---

## Non-functional requirements

- Полная обратная совместимость текущих чатовых API для клиентов после exchange.
- Минимальный downtime при rollout.
- Feature flag для безопасного включения SSO-only режима.
- Наблюдаемость: структурные логи, метрики ошибок, трассировка webhook событий.

---

## Тесты (обязательно)

Добавь feature/integration тесты:

1. Успешный exchange по валидному ID token.
2. Exchange с невалидным/просроченным token -> 401.
3. Exchange для пользователя без роли в Pulse -> 403.
4. Webhook user-revoked очищает локальные Sanctum токены.
5. Webhook user-updated обновляет профиль.
6. Регрессия realtime auth (как минимум smoke test на валидный auth lifecycle).

---

## Формат результата

Ответ должен быть в формате:

1. **Краткий план реализации** (по шагам).
2. **Список файлов/модулей**: create/update/delete.
3. **Полный код** для новых/измененных файлов.
4. **Миграционный runbook**:
   - порядок выката,
   - rollback plan,
   - checklist для staging/prod.
5. **Риски и меры** (таблица risk -> mitigation).

Не пиши общие советы. Дай конкретную реализацию уровня senior production.
