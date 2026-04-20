<?php

declare(strict_types=1);

namespace App\Contracts\Ai;

use App\Application\Ai\Dto\AiChatKickoffDto;
use App\Application\Ai\Dto\AiSuggestedReplyDto;
use App\Application\Ai\Dto\AiThreadSummaryDto;

interface AiProviderInterface
{
    /**
     * Один запрос к LLM: краткий topic + summary/intent + 2 варианта ответа по первым сообщениям клиента.
     */
    public function generateKickoffFromClientMessages(string $messagesText): AiChatKickoffDto;

    public function generateTopic(string $messagesText): ?string;

    public function summarizeThread(string $context): AiThreadSummaryDto;

    /** @return list<AiSuggestedReplyDto> */
    public function suggestReplies(string $context): array;
}
