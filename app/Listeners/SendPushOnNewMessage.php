<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\NewChatMessage;
use App\Jobs\SendFcmPushNotificationJob;
use App\Jobs\SendWebPushNotificationJob;

final class SendPushOnNewMessage
{
    public function handle(NewChatMessage $event): void
    {
        SendFcmPushNotificationJob::dispatch(
            $event->chatId,
            $event->messageId,
            $event->text,
        );
        SendWebPushNotificationJob::dispatch(
            $event->chatId,
            $event->messageId,
            $event->text,
        );
    }
}
