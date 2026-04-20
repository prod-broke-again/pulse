# Промпт для создания DepartmentSeeder

> **Для кого:** следующая нейросеть, которая получит эту задачу без доступа к нашим предыдущим обсуждениям.
> **Что делает:** создаёт идемпотентный Laravel-сидер с 10 базовыми департаментами на каждый существующий `source`.

---

## 1. Задача

Создать файл `database/seeders/DepartmentSeeder.php`, который для **каждого** существующего `source` (или для конкретного, если передан `--source=ID`) добавит 10 базовых департаментов из списка ниже. Сидер должен быть **идемпотентным** — повторный запуск не должен ни дублировать записи, ни сбрасывать ручные правки (иконку, `ai_enabled`, `is_active` менять только при первом создании).

---

## 2. Контекст архитектуры (не переоткрывай сам)

**Таблица `departments`** (`app/Infrastructure/Persistence/Eloquent/DepartmentModel.php`):

- `id`
- `source_id` — FK на `sources.id`, обязательный
- `name` — строка, человекочитаемое название
- `slug` — строка, **уникальна в паре с `source_id`** (`unique(['source_id', 'slug'])`)
- `category` — enum `DepartmentCategory` в `app/Domains/Department/ValueObject/DepartmentCategory.php`:
  - значения: `support`, `registration`, `tech`, `ethics`, `other`
- `ai_enabled` — boolean, default `true`
- `is_active` — boolean, default `true`
- `icon` — nullable string, должно быть из whitelist `app/Support/DepartmentIcons.php::ALLOWED`
- `created_at`, `updated_at`

**Whitelist иконок** (`app/Support/DepartmentIcons.php`, константа `ALLOWED`): 25 имён Lucide — `Building2, Users, ShoppingCart, Headphones, CreditCard, Truck, Phone, Mail, FileText, BarChart3, MessageCircle, Settings, Wrench, Scale, UserPlus, Shield, Package, Briefcase, HelpCircle, AlertTriangle, Heart, Globe, Tag, Zap, Clipboard`. Если LLM-задание помечает иконкой что-то вне этого списка — фикс на `Building2`.

**Источники (`sources`):** уже существуют в БД. Не создавай новые. Если при запуске сидера сорсов нет — просто вывести предупреждение «Нет источников в БД, сидер пропущен» и выйти.

**Domain Entity `Department`** (`app/Domains/Integration/Entity/Department.php`): поля `id`, `sourceId`, `name`, `slug`, `isActive`, `category`, `icon`. Пишется через `EloquentDepartmentRepository::persist(Department)`, но для сидера — ОК использовать напрямую `DepartmentModel::firstOrCreate()`, так быстрее и прозрачнее.

---

## 3. Список департаментов (ровно 10)

### Откуда взялся список (экосистема АЧПП)

Прежний набор («продажи», «доставка», «возвраты») соответствует типичному интернет-магазину и **плохо бьётся** с реальными темами обращений в экосистеме **Ассоциации частнопрактикующих психологов**:

| Проект | Роль для пользователя | О чём спрашивают (по коду и README) |
|--------|------------------------|--------------------------------------|
| **`id`** (ACHPP ID) | Центральный IdP: OAuth2 (Passport), кабинет, Fortify, 2FA, соцвходы (VK, Telegram, Yandex), сессии, подключённые приложения | вход и ошибки SSO, сброс пароля, 2FA, привязка провайдеров |
| **`a4pp-1`** (портал АЧПП) | LMS: курсы, видео (Kinescope), клубы, встречи, новости/блог, подписки, баланс, Robokassa, мессенджер, чат поддержки, Telegram, docs-api / ЕЦД, Android-приложение | доступ к материалам, оплата и подписки, мероприятия, внутренний мессенджер, документы |
| **`psy`** (Аврора) | Агрегатор: поиск психологов, консультации, расписание, чат/WebRTC, оплаты, верификация, корпоративные кабинеты | запись и отмена, сессия консультации, звонок/видео не работает, оплата консультации |

**Выборка из БД портала:** в `a4pp-1` обращения лежат в `support_sessions` / `support_messages`. Если при сидировании доступна база — имеет смысл **прогнать выборку текстов** и при необходимости скорректировать формулировки `name` (slug и категории трогать только осознанно). Если БД недоступна — опираться на этот документ и актуальные маршруты/API трёх проектов.

### Таблица (10 строк — под маршрутизацию чатов Pulse по продуктам экосистемы)

| # | name | slug | category | icon | ai_enabled | is_active |
|---|------|------|----------|------|------------|-----------|
| 1 | АЧПП ID: вход и аккаунт | achpp-id-account | registration | UserPlus | true | true |
| 2 | Портал: подписки и оплата | portal-billing | support | CreditCard | true | true |
| 3 | Портал: курсы и видеотека | portal-learning | support | Clipboard | true | true |
| 4 | Портал: клубы и встречи | portal-clubs-events | support | Users | true | true |
| 5 | Портал: мессенджер и уведомления | portal-messenger | tech | MessageCircle | true | true |
| 6 | Аврора: консультации и расписание | aurora-consultations | support | Briefcase | true | true |
| 7 | Аврора: звонки, чат и видео | aurora-realtime | tech | Headphones | true | true |
| 8 | Документы и мобильное приложение | documents-and-apps | tech | FileText | true | true |
| 9 | Этика и жалобы | ethics-complaints | ethics | Scale | false | true |
| 10 | Прочие вопросы | general | other | HelpCircle | true | true |

**Пояснения к строкам (для LLM / модераторов):**

- **1** — единый аккаунт, OAuth/PKCE, ошибки входа через IdP, восстановление доступа, 2FA, активные устройства и сессии.
- **2** — баланс, подписки портала, Robokassa, транзакции, списания (не путать с оплатой **конкретной консультации** в Авроре — там уместнее **6**).
- **3** — программы, материалы, доступ к видео (Kinescope), прохождение курсов.
- **4** — клубы, календарь встреч/мероприятий, регистрация на события.
- **5** — внутренний мессенджер портала, in-app уведомления, при необходимости связка с Telegram-ботом уведомлений (без смешения с **техническими** сбоями звонка — это **7**).
- **6** — подбор специалиста, бронирование слотов, отмена, перенос, корпоративные сценарии кабинета, оплата услуги на стороне Авроры.
- **7** — WebRTC/PeerJS, качество связи, чат/звонок/видео в сессии консультации.
- **8** — синхронизация с сервисом документов (ЕЦД), статусы документов, Android-клиент портала / выгрузки версий.
- **9** — чувствительные темы; **AI-автоназначение отключено** (`ai_enabled = false`).
- **10** — всё, что не классифицируется выше или на стыке продуктов.

**Обрати внимание:** у «Этика и жалобы» `ai_enabled = false`. Остальные — с AI.

---

## 4. Требования к реализации

1. **Идемпотентность.** Использовать `DepartmentModel::firstOrCreate(['source_id' => $id, 'slug' => $slug], [ ...rest ])`. Если запись уже есть (по паре `source_id + slug`) — не трогать её.
2. **Перебор источников.** По умолчанию сидер проходит по всем `sources` в БД. Опционально принимать `--source=ID` как команду artisan (через параметр) — если передан, сидит только для этого source.
3. **Никаких side-effects:** не менять уже существующие департаменты, не удалять, не деактивировать.
4. **Лог.** В конце вывести в консоль: сколько sources обработано, сколько departments создано, сколько пропущено (уже существовали). Формат:
   ```
   Seeding departments:
     Source #1 (Telegram Bot): 10 created, 0 skipped
     Source #2 (Web Widget):   3 created, 7 skipped
   Total: 13 created, 7 skipped across 2 sources.
   ```
5. **Валидация иконки.** Перед записью прогонять `DepartmentIcons::normalize($icon)`. Формально все иконки из таблицы выше в whitelist-е, но это страховка от опечатки.
6. **Category** передавать как `DepartmentCategory::from('support')` (string value) или как enum — в зависимости от того, что принимает модель. Скорее всего Eloquent-каст сам приведёт строку к enum, но проверь существующий код модели.

---

## 5. Регистрация сидера

Добавить вызов в `database/seeders/DatabaseSeeder.php`:

```php
public function run(): void
{
    $this->call([
        // ... существующие сидеры ...
        DepartmentSeeder::class,
    ]);
}
```

Если `DatabaseSeeder::run()` уже содержит что-то важное (проверь перед правкой), добавь вызов рядом с остальными, не трогая существующий порядок.

---

## 6. Запуск

Три способа запустить:

```bash
# Все источники
php artisan db:seed --class=DepartmentSeeder

# Только для конкретного source (если реализован флаг)
php artisan db:seed --class=DepartmentSeeder -- --source=1

# В составе полного сида (fresh database)
php artisan migrate:fresh --seed
```

---

## 7. Тест (минимальный, обязательно)

Создать `tests/Feature/Database/DepartmentSeederTest.php`:

1. Тест: сид проходит — для одного созданного `source` появляется ровно 10 департаментов с корректными slug-ами.
2. Тест: повторный запуск не создаёт дубликатов — счётчик `departments` тот же.
3. Тест: если существующий департамент имеет `ai_enabled = false` (ручная правка), после повторного сида значение не перезаписывается на `true`.
4. Тест: при пустой таблице `sources` сидер не падает, выводит предупреждение.

---

## 8. Skeleton кода (ориентир, не копипасти бездумно)

```php
<?php

namespace Database\Seeders;

use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel; // проверь актуальный namespace
use App\Support\DepartmentIcons;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /** @var list<array{name:string,slug:string,category:string,icon:string,ai_enabled:bool,is_active:bool}> */
    private const DEPARTMENTS = [
        ['name' => 'АЧПП ID: вход и аккаунт', 'slug' => 'achpp-id-account', 'category' => 'registration', 'icon' => 'UserPlus', 'ai_enabled' => true, 'is_active' => true],
        ['name' => 'Портал: подписки и оплата', 'slug' => 'portal-billing', 'category' => 'support', 'icon' => 'CreditCard', 'ai_enabled' => true, 'is_active' => true],
        ['name' => 'Портал: курсы и видеотека', 'slug' => 'portal-learning', 'category' => 'support', 'icon' => 'Clipboard', 'ai_enabled' => true, 'is_active' => true],
        ['name' => 'Портал: клубы и встречи', 'slug' => 'portal-clubs-events', 'category' => 'support', 'icon' => 'Users', 'ai_enabled' => true, 'is_active' => true],
        ['name' => 'Портал: мессенджер и уведомления', 'slug' => 'portal-messenger', 'category' => 'tech', 'icon' => 'MessageCircle', 'ai_enabled' => true, 'is_active' => true],
        ['name' => 'Аврора: консультации и расписание', 'slug' => 'aurora-consultations', 'category' => 'support', 'icon' => 'Briefcase', 'ai_enabled' => true, 'is_active' => true],
        ['name' => 'Аврора: звонки, чат и видео', 'slug' => 'aurora-realtime', 'category' => 'tech', 'icon' => 'Headphones', 'ai_enabled' => true, 'is_active' => true],
        ['name' => 'Документы и мобильное приложение', 'slug' => 'documents-and-apps', 'category' => 'tech', 'icon' => 'FileText', 'ai_enabled' => true, 'is_active' => true],
        ['name' => 'Этика и жалобы', 'slug' => 'ethics-complaints', 'category' => 'ethics', 'icon' => 'Scale', 'ai_enabled' => false, 'is_active' => true],
        ['name' => 'Прочие вопросы', 'slug' => 'general', 'category' => 'other', 'icon' => 'HelpCircle', 'ai_enabled' => true, 'is_active' => true],
    ];

    public function run(): void
    {
        $sources = SourceModel::query()->get(['id', 'name']);

        if ($sources->isEmpty()) {
            $this->command?->warn('Нет источников в БД, сидер пропущен.');
            return;
        }

        $totalCreated = 0;
        $totalSkipped = 0;

        foreach ($sources as $source) {
            $created = 0;
            $skipped = 0;

            foreach (self::DEPARTMENTS as $dept) {
                $model = DepartmentModel::firstOrCreate(
                    ['source_id' => $source->id, 'slug' => $dept['slug']],
                    [
                        'name'       => $dept['name'],
                        'category'   => $dept['category'],
                        'icon'       => DepartmentIcons::normalize($dept['icon']),
                        'ai_enabled' => $dept['ai_enabled'],
                        'is_active'  => $dept['is_active'],
                    ],
                );

                $model->wasRecentlyCreated ? $created++ : $skipped++;
            }

            $this->command?->info(sprintf(
                '  Source #%d (%s): %d created, %d skipped',
                $source->id, $source->name ?? '—', $created, $skipped
            ));

            $totalCreated += $created;
            $totalSkipped += $skipped;
        }

        $this->command?->info(sprintf(
            'Total: %d created, %d skipped across %d sources.',
            $totalCreated, $totalSkipped, $sources->count()
        ));
    }
}
```

**Обрати внимание:** имя модели source может отличаться (`SourceModel`, `Source`, `App\Models\Source` — что там у вас). Проверь грепом `grep -r "class.*SourceModel\|class Source " app/` и используй правильное.

---

## 9. Чего **НЕ** делать

- Не удалять и не обновлять существующие записи `departments`.
- Не создавать новые `sources`.
- Не добавлять seed-данные в другие таблицы (chats, messages, users).
- Не завязывать сидер на специфический environment — он должен работать и в dev, и в staging.
- Не хардкодить ID источников — всегда перебирать через Eloquent.

---

## 10. Промпт для старта

> Ты работаешь над Laravel-проектом в `C:\laragon\www\pulse`. Прочти `docs/DEPARTMENT_SEEDER_PROMPT.md` полностью. Твоя задача — реализовать сидер согласно этому документу.
>
> Шаги:
> 1. Прочти документ.
> 2. Грепом проверь актуальные namespace и имя модели для `sources` (возможно `App\Infrastructure\Persistence\Eloquent\SourceModel`, возможно `App\Models\Source` — используй то, что есть).
> 3. Проверь содержимое `app/Support/DepartmentIcons.php` и `app/Domains/Department/ValueObject/DepartmentCategory.php` — все ли используемые в таблице § 3 значения валидны.
> 4. Составь TodoList.
> 5. Создай `database/seeders/DepartmentSeeder.php`.
> 6. Зарегистрируй в `DatabaseSeeder::run()`.
> 7. Создай `tests/Feature/Database/DepartmentSeederTest.php` с 4 тестами из раздела 7.
> 8. Запусти `php artisan db:seed --class=DepartmentSeeder` (если есть тестовая БД) и приложи вывод. Также прогон `php artisan test --filter=DepartmentSeederTest`.
> 9. Не коммить — покажи diff и жди подтверждения.
>
> Соблюдай раздел «Чего НЕ делать» неукоснительно.
