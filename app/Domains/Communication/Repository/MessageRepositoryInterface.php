<?php

declare(strict_types=1);

namespace App\Domains\Communication\Repository;

use App\Domains\Communication\Entity\Message;

interface MessageRepositoryInterface
{
    public function findById(int $id): ?Message;

    public function findByChatAndExternalMessageId(int $chatId, string $externalMessageId): ?Message;

    /** @return list<Message> */
    public function listByChatId(int $chatId): array;

    /** @return list<Message> Newest first; use $beforeId to load older (scroll up). */
    public function listByChatIdPaginated(int $chatId, int $limit = 50, ?int $beforeId = null): array;

    public function persist(Message $message): Message;
}
