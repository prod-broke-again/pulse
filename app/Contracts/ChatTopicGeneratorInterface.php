<?php

declare(strict_types=1);

namespace App\Contracts;

interface ChatTopicGeneratorInterface
{
    /**
     * Генерирует короткий заголовок (2-5 слов) на основе текста сообщений.
     */
    public function generateTopic(string $messagesText): ?string;
}
