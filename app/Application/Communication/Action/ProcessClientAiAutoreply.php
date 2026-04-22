<?php

declare(strict_types=1);

namespace App\Application\Communication\Action;

use App\Application\Ai\Dto\AiChatKickoffDto;
use App\Application\Integration\ResolveMessengerProvider;
use App\Domains\Communication\Repository\ChatRepositoryInterface;
use App\Domains\Communication\ValueObject\ChatStatus;
use App\Domains\Communication\ValueObject\SenderType;
use App\Domains\Integration\Repository\SourceRepositoryInterface;
use App\Domains\Integration\ValueObject\SourceType;
use App\Models\AiSettings;
use App\Support\AiClientControlKeyboard;
use Illuminate\Support\Facades\Schema;

final readonly class ProcessClientAiAutoreply
{
    public function __construct(
        private SendMessage $sendMessage,
        private ChatRepositoryInterface $chats,
        private ResolveMessengerProvider $resolveMessenger,
        private SourceRepositoryInterface $sources,
    ) {}

    /**
     * If AI can answer, sends system line + control keyboard; else routes to human queue.
     */
    public function fromKickoff(int $chatId, AiChatKickoffDto $kickoff): void
    {
        if (! config('features.ai_client_autoreply', true)) {
            if ($kickoff->escalateToHuman) {
                $this->escalate($chatId);
            }

            return;
        }
        $chat = $this->chats->findById($chatId);
        if ($chat === null) {
            return;
        }
        $source = $this->sources->findById($chat->sourceId);
        if ($source === null) {
            return;
        }
        if ($this->atWebAutoreplyCap($source->type, $chat)) {
            $this->escalate($chatId);

            return;
        }
        if ($kickoff->escalateToHuman) {
            $this->escalate($chatId);

            return;
        }
        $min = (float) config('features.ai_client_autoreply_min_conf', 0.85);
        $text = $kickoff->autoReplyText;
        $conf = $kickoff->autoReplyConfidence;
        if ($text === null || $text === '' || $conf === null || $conf < $min) {
            $this->escalate($chatId);

            return;
        }
        if ($source->type === SourceType::Max) {
            $text = $text."\n\n(Ответьте: «оператор», «да» или «нет» в чат, если кнопки не видны.)";
        }
        $messenger = $this->resolveMessenger->run($source->id);
        $this->sendMessage->run(
            chatId: $chatId,
            text: $text,
            senderType: SenderType::System,
            senderId: null,
            messenger: $messenger,
            replyMarkup: $this->markupForSource($source->type, $chatId),
            deliverToMessenger: true,
        );
        $chat = $this->chats->findById($chatId) ?? $chat;
        $this->chats->persist($chat->withOverrides([
            'aiAutoRepliesCount' => $chat->aiAutoRepliesCount + 1,
            'awaitingClientFeedback' => true,
        ]));
    }

    private function atWebAutoreplyCap(SourceType $type, \App\Domains\Communication\Entity\Chat $chat): bool
    {
        if ($type !== SourceType::Web) {
            return false;
        }
        $fromDb = Schema::hasTable('ai_settings')
            ? AiSettings::query()->value('web_max_auto_replies')
            : null;
        $max = (int) ($fromDb ?? config('features.ai_widget_max_auto_replies', 3));

        return $chat->aiAutoRepliesCount >= $max;
    }

    public function escalate(int $chatId): void
    {
        $chat = $this->chats->findById($chatId);
        if ($chat === null) {
            return;
        }
        $this->chats->persist($chat->withOverrides([
            'status' => ChatStatus::New,
            'assignedTo' => null,
            'awaitingClientFeedback' => false,
        ]));
    }

    /**
     * @return list<array{text: string, url?: string, callback_data?: string}>|null
     */
    private function markupForSource(SourceType $type, int $chatId): ?array
    {
        if ($type === SourceType::Max) {
            return null;
        }

        return AiClientControlKeyboard::forChat($chatId);
    }
}
