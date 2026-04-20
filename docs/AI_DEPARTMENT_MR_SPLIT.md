# Разбиение изменений AI department на три логических MR / коммита

Коммит `5ef0270` («AI department: retry guard, job tests, prompt filters») объединяет бэкенд, клиенты и доработки идемпотентности. Ниже — группировка **путей из того коммита** для отдельных MR, точечного revert или пересборки истории **до push** в общий `master`/`main`.

## MR A — Backend-ядро

Миграции, конфиг фич, домен, персистенция, API, Filament, интеграции AI, базовый job и промпт (как в монолитном коммите; см. примечание в MR C).

| Путь |
|------|
| `.env.example` |
| `config/features.php` |
| `database/migrations/2026_04_21_000001_add_icon_to_departments_table.php` |
| `database/migrations/2026_04_21_000002_backfill_department_icons.php` |
| `database/migrations/2026_04_21_000003_add_ai_department_audit_to_chats.php` |
| `app/Application/Ai/Dto/AiChatKickoffDto.php` |
| `app/Application/Communication/Action/ChangeChatDepartment.php` |
| `app/Contracts/Ai/AiProviderInterface.php` |
| `app/Domains/Communication/Entity/Chat.php` |
| `app/Domains/Integration/Entity/Department.php` |
| `app/Filament/Resources/DepartmentModels/DepartmentModelResource.php` |
| `app/Http/Controllers/Api/V1/DepartmentController.php` |
| `app/Http/Resources/Api/V1/ChatResource.php` |
| `app/Infrastructure/Persistence/Eloquent/ChatModel.php` |
| `app/Infrastructure/Persistence/Eloquent/DepartmentModel.php` |
| `app/Infrastructure/Persistence/EloquentChatRepository.php` |
| `app/Infrastructure/Persistence/EloquentDepartmentRepository.php` |
| `app/Jobs/GenerateChatTopicJob.php` |
| `app/Services/Ai/NullAiProvider.php` |
| `app/Services/GPTunnelService.php` |
| `app/Services/TimewebAiService.php` |
| `app/Support/AiKickoffPrompt.php` |
| `app/Support/DepartmentIcons.php` |
| `tests/Feature/Api/V1/ChatApiTest.php` |
| `tests/Unit/DepartmentIconsTest.php` |
| `docs/REFACTOR_PLAN_AI_DEPARTMENT.md` |

## MR B — Frontend (desktop + mobile)

| Путь |
|------|
| `apps/pulse-desktop/src/api/departments.ts` |
| `apps/pulse-desktop/src/components/chat/ChatHeader.vue` |
| `apps/pulse-desktop/src/components/chat/InboxPanel.vue` |
| `apps/pulse-desktop/src/constants/departmentIcons.ts` |
| `apps/pulse-desktop/src/types/chat.ts` |
| `apps/pulse-desktop/src/types/dto/chat.types.ts` |
| `apps/pulse-desktop/src/utils/mappers.ts` |
| `apps/pulse-mobile/src/api/chatRepository.ts` |
| `apps/pulse-mobile/src/api/types.ts` |
| `apps/pulse-mobile/src/components/chat/ChatHeader.vue` |
| `apps/pulse-mobile/src/components/inbox/ChatCard.vue` |
| `apps/pulse-mobile/src/constants/departmentIcons.ts` |
| `apps/pulse-mobile/src/mappers/chatMapper.ts` |
| `apps/pulse-mobile/src/stores/chatStore.ts` |
| `apps/pulse-mobile/src/types/chat.ts` |

## MR C — Доработки «последнего раунда» (идемпотентность, фильтры, тесты job, rollout, даты)

Логически это слой поверх MR A: **частично те же файлы**, что и в A (job, промпт, репозиторий отделов). В монолитном `5ef0270` их не отделить без переписи истории; для новой истории MR C оформляют **вторым коммитом поверх A** с диффом только по пунктам ниже.

| Путь | Содержание (ожидаемое) |
|------|-------------------------|
| `app/Jobs/GenerateChatTopicJob.php` | Ранний выход при уже заполненном audit; фильтр `ai_enabled` + cap 30; `createFromInterface(now())`; раздельные warning при смене отдела vs persist timestamp |
| `app/Support/AiKickoffPrompt.php` | `JSON_UNESCAPED_UNICODE` вместо pretty-print |
| `app/Infrastructure/Persistence/EloquentDepartmentRepository.php` | Проброс `ai_enabled` в доменную сущность (для фильтра в job) |
| `app/Domains/Integration/Entity/Department.php` | Поле `aiEnabled` |
| `tests/Feature/Jobs/GenerateChatTopicJobTest.php` | Сценарии job |
| `tests/Unit/AiKickoffPromptTest.php` | В т.ч. legacy JSON без полей отдела |
| `docs/AI_DEPARTMENT_ROLLOUT.md` | Метрики, откат, логи |

*Примечание:* строки `Department` / `EloquentDepartmentRepository` дублируются в таблице MR A, потому что в одном коммите они связаны и с ядром, и с фильтром kickoff.

---

## Пересборка истории (если `5ef0270` ещё не в общем remote)

Пример для локальной ветки, где последний коммит — `5ef0270`, а родитель — `cd75629`:

```bash
git reset --soft cd75629
# 1) git add <пути из MR A>; git commit -m "feat(ai-department): backend core (migrations, API, job, icons)"
# 2) git add <пути из MR B>; git commit -m "feat(ai-department): desktop and mobile parity"
# 3) При необходимости вынести только дифф MR C поверх — отдельный коммит с правками job/prompt/тестов/rollout
```

Если общий `master` уже содержит `5ef0270`, историю не переписывают: используйте таблицы выше для **выборочного revert** (`git revert` целого коммита или ручной revert по файлам) или для заведения трёх MR «с нуля» на новых ветках с переносом патчей.
