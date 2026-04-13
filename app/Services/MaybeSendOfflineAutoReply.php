<?php

declare(strict_types=1);

namespace App\Services;

use App\Application\Communication\Action\SendMessage;
use App\Application\Integration\ResolveMessengerProvider;
use App\Domains\Communication\ValueObject\SenderType;
use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use Illuminate\Support\Facades\DB;

final readonly class MaybeSendOfflineAutoReply
{
    public function __construct(
        private ModeratorPresenceService $presenceService,
        private ResolveMessengerProvider $resolveMessenger,
        private SendMessage $sendMessage,
    ) {}

    public function run(ChatModel $chat): void
    {
        $source = SourceModel::query()->find($chat->source_id);
        if ($source === null) {
            return;
        }

        $settings = is_array($source->settings) ? $source->settings : [];
        $enabled = (bool) ($settings['offline_auto_reply_enabled'] ?? false);
        $text = trim((string) ($settings['offline_auto_reply_text'] ?? ''));
        if (! $enabled || $text === '') {
            return;
        }

        if ($this->presenceService->anyModeratorOnlineForSource($chat->source_id)) {
            return;
        }

        DB::transaction(function () use ($chat, $text): void {
            /** @var ChatModel $locked */
            $locked = ChatModel::query()->whereKey($chat->id)->lockForUpdate()->first();
            if ($locked === null) {
                return;
            }

            $last = $locked->last_auto_reply_at;
            if ($last !== null && $last->greaterThan(now()->subMinutes(30))) {
                return;
            }

            $messenger = $this->resolveMessenger->run($locked->source_id);

            $this->sendMessage->run(
                chatId: $locked->id,
                text: $text,
                senderType: SenderType::System,
                senderId: null,
                messenger: $messenger,
                payload: ['kind' => 'offline_auto_reply'],
            );

            ChatModel::query()->whereKey($locked->id)->update([
                'last_auto_reply_at' => now(),
            ]);
        });
    }
}
