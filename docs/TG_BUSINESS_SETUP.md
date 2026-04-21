# Telegram Business Mode — деплой и webhook

После включения **Business Mode** для TG-источника в Filament (`telegram_mode = business`) бот должен получать обновления `business_connection`, `business_message` и связанные типы. Стандартный `setWebhook` без `allowed_updates` может их не доставлять.

## setWebhook с `allowed_updates`

Подставьте токен бота, URL вебхука Pulse и при необходимости `secret_token`:

```bash
curl "https://api.telegram.org/bot<TOKEN>/setWebhook" \
  --data-urlencode "url=https://your-pulse-host/webhook/telegram/<SOURCE_ID>" \
  --data-urlencode 'allowed_updates=["message","edited_message","callback_query","channel_post","business_connection","business_message","edited_business_message","deleted_business_messages"]'
```

Если используете секрет из поля «Секретный ключ» источника:

```bash
curl "https://api.telegram.org/bot<TOKEN>/setWebhook" \
  --data-urlencode "url=https://your-pulse-host/webhook/telegram/<SOURCE_ID>" \
  --data-urlencode "secret_token=<SECRET_FROM_SOURCE>" \
  --data-urlencode 'allowed_updates=["message","edited_message","callback_query","channel_post","business_connection","business_message","edited_business_message","deleted_business_messages"]'
```

## Подключение в клиенте Telegram

На аккаунте с **Telegram Premium**: **Settings → Business → Chatbots** — добавить username бота. Режим `direct` / `business` задаётся только в Filament; Pulse сохраняет `business_connection_id` из вебхука автоматически.
