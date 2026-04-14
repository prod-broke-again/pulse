<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Models\DeviceToken;
use App\Services\Push\ModeratorPushSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class SendFcmPushNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public function __construct(
        public int $chatId,
        public int $messageId,
        public string $text,
    ) {}

    public function handle(ModeratorPushSupport $pushSupport): void
    {
        $message = MessageModel::find($this->messageId);
        if ($message === null || $message->sender_type !== 'client') {
            return;
        }

        $chat = ChatModel::with(['source'])->find($this->chatId);
        if ($chat === null) {
            return;
        }

        $moderatorIds = $pushSupport->moderatorUserIdsForChat($chat);
        if ($moderatorIds === []) {
            return;
        }

        $tokens = DeviceToken::whereIn('user_id', $moderatorIds)
            ->pluck('token')
            ->all();

        if ($tokens === []) {
            return;
        }

        $fcmServerKey = config('services.fcm.server_key');
        if (empty($fcmServerKey)) {
            Log::warning('FCM server key not configured, skipping push notification');

            return;
        }

        $built = $pushSupport->buildNotificationPayload($chat, $this->chatId, $this->messageId, $this->text);

        foreach ($tokens as $token) {
            $this->sendToFcm($fcmServerKey, $token, $built);
        }
    }

    /**
     * @param  array{title: string, body: string, data: array<string, string>, tag: string, jsonPayload: string}  $built
     */
    private function sendToFcm(string $serverKey, string $token, array $built): void
    {
        try {
            Http::withHeaders([
                'Authorization' => 'key='.$serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $token,
                'notification' => array_filter([
                    'title' => $built['title'],
                    'body' => $built['body'],
                    'image' => $built['image'] ?? null,
                ], fn ($v) => $v !== null && $v !== ''),
                'data' => $built['data'],
            ]);
        } catch (\Throwable $e) {
            Log::error('FCM push failed', [
                'token' => mb_substr($token, 0, 20).'...',
                'error' => $e->getMessage(),
            ]);
        }
    }
}
