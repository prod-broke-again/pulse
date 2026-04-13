# Подготовка Inertia-редизайна (новый веб) на базе desktop UX

Цель: после паритета `apps/pulse-desktop` и `apps/pulse-mobile` по API v1 вынести UI на новый Inertia 2 + Vue 3, повторно используя паттерны из десктопа (layouts, компоненты чата, инбокс).

## Принципы

1. **Контракт данных** — только `GET/POST /api/v1/*` + Sanctum + Echo/Reverb; Blade-страницы чата не расширяем как продукт.
2. **Повторное использование** — выделить общий пакет типов DTO (`ApiChat`, `ApiMessage`) и мапперов по аналогии с desktop/mobile.
3. **Поэтапный cutover** — старый `resources/js` живёт до готовности новых страниц; маршруты переключаются флагом/релизом, а не «big bang delete».

## Чеклист перед удалением legacy веб-UI

- [ ] Feature parity: инбокс (вкладки, фильтры, unread), тред, вложения (фото, аудио, файлы), `reply_markup`, AI-панель по необходимости.
- [ ] Web Push: `useWebPush` + `public/sw.js` работают на новом layout (тот же origin и путь к SW).
- [ ] SSO/логин: тот же `POST /auth/sso/exchange` и cookie/bearer схема.
- [ ] E2E smoke: открытие чата из push `OPEN_CHAT`, отправка с `client_message_id`.

## Технические якоря в репозитории

- API: [`routes/api.php`](../routes/api.php), [`app/Http/Controllers/Api/V1`](../app/Http/Controllers/Api/V1)
- Референс UX: [`apps/pulse-desktop/src`](../apps/pulse-desktop/src)
- Push (веб): [`resources/js/composables/useWebPush.ts`](../resources/js/composables/useWebPush.ts), [`app/Services/Push/ModeratorPushSupport.php`](../app/Services/Push/ModeratorPushSupport.php)
