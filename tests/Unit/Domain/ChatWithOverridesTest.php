<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domains\Communication\Entity\Chat;
use App\Domains\Communication\ValueObject\ChatStatus;
use PHPUnit\Framework\TestCase;

final class ChatWithOverridesTest extends TestCase
{
    public function test_with_overrides_preserves_extra_fields_by_default(): void
    {
        $c = new Chat(
            id: 1,
            sourceId: 2,
            departmentId: 3,
            externalUserId: 'u',
            userMetadata: [],
            status: ChatStatus::New,
            assignedTo: null,
            lastActivityAt: new \DateTimeImmutable('2020-01-01'),
            previousChatId: 5,
            aiAutoRepliesCount: 2,
            awaitingClientFeedback: true,
        );
        $n = $c->withOverrides(['topic' => 'T']);
        $this->assertSame(5, $n->previousChatId);
        $this->assertSame(2, $n->aiAutoRepliesCount);
        $this->assertTrue($n->awaitingClientFeedback);
        $this->assertSame('T', $n->topic);
    }
}
