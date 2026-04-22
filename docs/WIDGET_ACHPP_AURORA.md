# Виджет Pulse: АЧПП (appp-psy.ru) и Аврора (kukushechka.ru)

В базе `pulse` заведены два **web-источника** (после миграции `2026_04_22_140000_add_achpp_and_aurora_web_sources.php`):

| Сайт | `sources.identifier` (атрибут `data-source`) | Примечание |
|------|-----------------------------------------------|------------|
| АЧПП | `achpp_web` | appp-psy.ru |
| Аврора | `aurora_web` | kukushechka.ru |

Для каждого источника миграция при необходимости создаёт отдел **«Поддержка»** (slug: `main`), иначе виджет не сможет открыть сессию чата (ошибка «No department configured»).

## 1. Развёртывание данных (миграция)

На сервере с приложением Pulse:

```bash
cd /var/www/pulse
php artisan migrate
```

## 2. Настройка Pulse (`.env`)

### CORS (обязательно для сторонних сайтов)

Браузер на `kukushechka.ru` / `appp-psy.ru` обращается к `pulse.appp-psy.ru` — в ответе должны быть заголовки CORS. В [`config/cors.php`](/var/www/pulse/config/cors.php) уже перечислены основные публичные origin’ы; при необходимости добавьте в `.env` Pulse:

`CORS_ALLOWED_ORIGINS=https://example.com`

После смены: `php artisan config:cache` (или `config:clear`).

### Статика виджета (`/widget/icons/*`)

[`config/cors.php`](/var/www/pulse/config/cors.php) задаёт CORS только для маршрутов приложения (например `api/*`). Файлы из `public/widget/icons/` обычно отдаёт **веб‑сервер** напрямую, минуя Laravel, поэтому для них заголовки нужно выставить **в nginx** (или на CDN), если иконки когда‑либо запрашиваются через cross-origin `fetch`.

В репозитории есть пример: [`docker/nginx/pulse.appp-psy.ru.conf`](/var/www/pulse/docker/nginx/pulse.appp-psy.ru.conf) — блок `location ^~ /widget/icons/` с `Access-Control-Allow-Origin`. Скопируйте его в продакшен-конфиг и перезагрузите nginx.

Текущий [`public/widget/pulse-widget.js`](/var/www/pulse/public/widget/pulse-widget.js) встраивает SVG иконок в скрипт, так что браузер не делает отдельных запросов к `/widget/icons/` ради UI; заголовки на статике остаются полезны для прямых обращений к файлам и для совместимости.

### Разрешённые origin’ы для виджета (обязательно)

В `config/widget.php` список берётся из `WIDGET_ALLOWED_ORIGINS` (через запятую, без пробелов). Укажите **публичный URL самого Pulse** и **origin каждого сайта**, куда встраивается виджет, например:

```env
WIDGET_ALLOWED_ORIGINS=https://pulse.appp-psy.ru,https://appp-psy.ru,https://www.appp-psy.ru,https://kukushechka.ru,https://www.kukushechka.ru
```

Добавьте варианты с/без `www`, если оба реально открываются в проде. После правки: `php artisan config:cache` (если кэшируете конфиг).

## 3. Модераторы

В панели Pulse: **Интеграции → Источники** — для источника `achpp_web` / `aurora_web` назначьте **модераторов** (связь `users`), либо используйте учётки с ролью **admin** (у них обычно видны все источники).

## 4. Установка виджета на сайт

Публичный хост API Pulse обозначим как **`PULSE_BASE`**, например `https://pulse.appp-psy.ru` (тот же origin, с которого отдаётся `pulse-widget.js` и срабатывают `POST` на `/api/widget/*`).

### АЧПП (appp-psy.ru)

В шаблон страницы (перед `</body>`), подставив свой `PULSE_BASE`:

```html
<script
    src="PULSE_BASE/widget/pulse-widget.js"
    data-api="PULSE_BASE"
    data-source="achpp_web"
    data-title="Поддержка"
    data-position="right"
    defer
></script>
```

Пример с подставленным URL:

```html
<script
    src="https://pulse.appp-psy.ru/widget/pulse-widget.js"
    data-api="https://pulse.appp-psy.ru"
    data-source="achpp_web"
    data-title="Поддержка"
    data-position="right"
    defer
></script>
```

### Аврора (kukushechka.ru)

Тот же `PULSE_BASE`, меняется только `data-source`:

```html
<script
    src="PULSE_BASE/widget/pulse-widget.js"
    data-api="PULSE_BASE"
    data-source="aurora_web"
    data-title="Поддержка"
    data-position="right"
    defer
></script>
```

- **`data-source`** должен **точно** совпадать с `sources.identifier` (здесь: `achpp_web` / `aurora_web`).
- **`data-api`**: базовый URL инстанса Pulse (без завершающего `/`).

## 5. Кастомизация внешнего вида (опционально)

Заголовок, подзаголовок, цвета и иконка подтягиваются из `GET {PULSE_BASE}/api/widget/config-ui?source=...` и при необходимости из таблицы `widget_configs` по полю `source_identifier`. Пока записей нет — используются значения по умолчанию.

## 6. Проверка

1. Сайт открыт по **HTTPS** с origin, перечисленным в `WIDGET_ALLOWED_ORIGINS`.
2. В DevTools: запросы к `{PULSE_BASE}/api/widget/session` и `config-ui` возвращают `200`.
3. В панели Pulse в источнике видны чаты с типом **Веб** для нужного `identifier`.
