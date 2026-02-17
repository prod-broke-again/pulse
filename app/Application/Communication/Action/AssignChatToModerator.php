<?php

declare(strict_types=1);

namespace App\Application\Communication\Action;

use App\Domains\Communication\Entity\Chat;
use App\Domains\Communication\Repository\ChatRepositoryInterface;
use App\Domains\Communication\ValueObject\ChatStatus;
use App\Events\ChatAssigned as ChatAssignedEvent;
use Illuminate\Contracts\Events\Dispatcher;

final readonly class AssignChatToModerator
{
    public function __construct(
        private ChatRepositoryInterface $chatRepository,
        private Dispatcher $events,
    ) {}

    public function run(int $chatId, int $userId): Chat
    {
        $chat = $this->chatRepository->findById($chatId);
        if ($chat === null) {
            throw new \InvalidArgumentException("Chat not found: {$chatId}");
        }

        $updated = new Chat(
            id: $chat->id,
            sourceId: $chat->sourceId,
            departmentId: $chat->departmentId,
            externalUserId: $chat->externalUserId,
            userMetadata: $chat->userMetadata,
            status: ChatStatus::Active,
            assignedTo: $userId,
        );

        $persisted = $this->chatRepository->persist($updated);

        $this->events->dispatch(new ChatAssignedEvent(chatId: $persisted->id, assignedToUserId: $userId));

        return $persisted;
    }
}
