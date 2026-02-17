<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Models\DeviceToken;
use App\Models\User;
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

    public function handle(): void
    {
        $chat = ChatModel::with(['source'])->find($this->chatId);
        if ($chat === null) {
            return;
        }

        $moderatorIds = $this->getModeratorsForChat($chat);
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

        foreach ($tokens as $token) {
            $this->sendToFcm($fcmServerKey, $token, $chat);
        }
    }

    /**
     * @return list<int>
     */
    private function getModeratorsForChat(ChatModel $chat): array
    {
        $sourceId = $chat->source_id;

        $admins = User::role('admin')->pluck('id')->all();

        $moderatorsBySource = User::role('moderator')
            ->whereHas('sources', fn ($q) => $q->where('source_id', $sourceId))
            ->pluck('id')
            ->all();

        return array_values(array_unique([...$admins, ...$moderatorsBySource]));
    }

    private function sendToFcm(string $serverKey, string $token, ChatModel $chat): void
    {
        try {
            Http::withHeaders([
                'Authorization' => 'key='.$serverKey,
                'Content-Type' => 'application/json',
            ])->post('https://fcm.googleapis.com/fcm/send', [
                'to' => $token,
                'notification' => [
                    'title' => $chat->source?->name ?? 'Pulse',
                    'body' => mb_substr($this->text, 0, 200),
                ],
                'data' => [
                    'chat_id' => $this->chatId,
                    'message_id' => $this->messageId,
                    'action' => 'new_message',
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('FCM push failed', [
                'token' => mb_substr($token, 0, 20).'...',
                'error' => $e->getMessage(),
            ]);
        }
    }
}
