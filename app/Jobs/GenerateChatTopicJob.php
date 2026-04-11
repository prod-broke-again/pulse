<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\ChatTopicGeneratorInterface;
use App\Domains\Communication\Entity\Chat;
use App\Domains\Communication\Repository\ChatRepositoryInterface;
use App\Events\ChatTopicGenerated;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

final class GenerateChatTopicJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 30;

    public function __construct(
        public int $chatId,
    ) {}

    public function handle(
        ChatRepositoryInterface $chatRepository,
        ChatTopicGeneratorInterface $aiGenerator,
    ): void {
        $chat = $chatRepository->findById($this->chatId);
        if ($chat === null || ($chat->topic !== null && $chat->topic !== '')) {
            return;
        }

        $clientMessages = MessageModel::where('chat_id', $this->chatId)
            ->where('sender_type', 'client')
            ->orderBy('id')
            ->limit(2)
            ->pluck('text')
            ->all();

        $messagesText = Str::limit(
            implode("\n", array_map('trim', $clientMessages)),
            1500,
        );

        if ($messagesText === '') {
            return;
        }

        $topic = $aiGenerator->generateTopic($messagesText);

        if ($topic === null || $topic === '') {
            return;
        }

        $updated = new Chat(
            id: $chat->id,
            sourceId: $chat->sourceId,
            departmentId: $chat->departmentId,
            externalUserId: $chat->externalUserId,
            userMetadata: $chat->userMetadata,
            status: $chat->status,
            assignedTo: $chat->assignedTo,
            topic: $topic,
        );
        $chatRepository->persist($updated);

        event(new ChatTopicGenerated(chatId: $this->chatId, topic: $topic));
    }
}
