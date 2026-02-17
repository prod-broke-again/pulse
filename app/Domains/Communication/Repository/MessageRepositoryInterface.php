<?php

declare(strict_types=1);

namespace App\Domains\Communication\Repository;

use App\Domains\Communication\Entity\Message;

interface MessageRepositoryInterface
{
    public function findById(int $id): ?Message;

    /** @return list<Message> */
    public function listByChatId(int $chatId): array;

    public function persist(Message $message): Message;
}
