<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

final class SendWebPushNotificationJob implements ShouldQueue
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

        $subscriptions = PushSubscription::whereIn('user_id', $moderatorIds)->get();
        if ($subscriptions->isEmpty()) {
            return;
        }

        $vapidPublic = config('services.web_push.vapid_public_key');
        $vapidPrivate = config('services.web_push.vapid_private_key');
        $subject = config('services.web_push.subject', 'mailto:admin@localhost');
        if (empty($vapidPublic) || empty($vapidPrivate)) {
            Log::warning('Web Push VAPID keys not configured, skipping');

            return;
        }

        $title = $chat->source?->name ?? config('app.name');
        $body = mb_substr($this->text, 0, 200);
        $payload = json_encode([
            'title' => $title,
            'body' => $body,
            'data' => [
                'chat_id' => $this->chatId,
                'message_id' => $this->messageId,
                'action' => 'new_message',
                'url' => '/chat',
            ],
        ], JSON_THROW_ON_ERROR);

        try {
            $webPush = new WebPush([
                'VAPID' => [
                    'subject' => $subject,
                    'publicKey' => $vapidPublic,
                    'privateKey' => $vapidPrivate,
                ],
            ]);

            foreach ($subscriptions as $sub) {
                try {
                    $subscription = Subscription::create([
                        'endpoint' => $sub->endpoint,
                        'keys' => [
                            'p256dh' => $sub->public_key,
                            'auth' => $sub->auth_token,
                        ],
                    ]);
                    $webPush->queueNotification($subscription, $payload);
                } catch (\Throwable $e) {
                    Log::warning('Web Push invalid subscription', [
                        'subscription_id' => $sub->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $reports = $webPush->flush();
            foreach ($reports as $report) {
                if ($report->isSubscriptionExpired() && $report->getEndpoint()) {
                    PushSubscription::where('endpoint', $report->getEndpoint())->delete();
                }
            }
        } catch (\Throwable $e) {
            Log::error('Web Push send failed', ['error' => $e->getMessage()]);

            throw $e;
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
}
