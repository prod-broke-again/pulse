<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Application\Ai\Dto\AiThreadSummaryDto;
use App\Contracts\Ai\AiProviderInterface;

final class NullAiProvider implements AiProviderInterface
{
    public function generateTopic(string $messagesText): ?string
    {
        return null;
    }

    public function summarizeThread(string $context): AiThreadSummaryDto
    {
        return new AiThreadSummaryDto(summary: '', intentTag: null);
    }

    public function suggestReplies(string $context): array
    {
        return [];
    }
}
