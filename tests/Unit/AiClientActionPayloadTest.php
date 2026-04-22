<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AiClientActionPayload;
use PHPUnit\Framework\TestCase;

final class AiClientActionPayloadTest extends TestCase
{
    public function test_round_trip(): void
    {
        $s = AiClientActionPayload::encode(99, AiClientActionPayload::A_HUMAN);
        $p = AiClientActionPayload::parse($s);
        $this->assertSame(99, $p['chat_id']);
        $this->assertSame('h', $p['action']);
    }
}
