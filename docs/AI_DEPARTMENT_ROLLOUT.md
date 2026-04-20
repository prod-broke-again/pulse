# Включение AI-назначения отдела (`FEATURE_AI_DEPARTMENT_ASSIGNMENT`)

## Переменные окружения

- `FEATURE_AI_DEPARTMENT_ASSIGNMENT` — `true` / `false` (по умолчанию выкл.).
- `FEATURE_AI_DEPT_CONFIDENCE` — порог уверенности LLM (0.0–1.0), по умолчанию `0.7`.

После изменения `.env`: `php artisan config:clear` (или перезапуск PHP-FPM / queue workers).

## SQL для метрик (PostgreSQL)

Доля чатов с авто-применённым отделом (после успешного `runAsSystem`):

```sql
SELECT COUNT(*) FILTER (WHERE ai_department_assigned_at IS NOT NULL) AS auto_assigned,
       COUNT(*) AS total_with_audit_hint
FROM chats
WHERE ai_suggested_department_id IS NOT NULL;
```

Средняя уверенность по записям с предложением:

```sql
SELECT AVG(ai_department_confidence::numeric) AS avg_confidence
FROM chats
WHERE ai_department_confidence IS NOT NULL;
```

Доля ручных переназначений после AI (модератор выбрал отдел ≠ предложенному):

```sql
SELECT COUNT(*) FILTER (WHERE department_reassigned_by_user_id IS NOT NULL) AS human_overrides,
       COUNT(*) FILTER (WHERE ai_suggested_department_id IS NOT NULL) AS with_suggestion
FROM chats;
```

## Что смотреть в логах

- `AI suggested invalid department id` — LLM вернул id не из списка; отдел не меняется, audit для этого прохода не пишется.
- `AI dept auto-assign failed` — исключение в `ChangeChatDepartment::runAsSystem()` (валидация отдела, БД и т.д.); audit уже может быть записан, `department_id` не сменился.

Уровень: `warning` в канале по умолчанию (`Log::warning`).

## Процедура отката

1. Выставить `FEATURE_AI_DEPARTMENT_ASSIGNMENT=false`, очистить конфиг, перезапустить воркеры очереди.
2. Новые чаты снова не будут получать kickoff с списком департаментов и авто-смену отдела.
3. Уже записанные поля `ai_*` и `department_id` в БД **не откатываются** автоматически — при необходимости правки делаются отдельной задачей / скриптом.

## Ограничения промпта

В job в LLM передаётся не более **30** активных отделов с `ai_enabled = true` для данного `source_id`. При очень большом числе отделов часть не попадает в контекст — при необходимости увеличивать лимит осознанно (токены / стоимость).
