<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Integration\ValueObject\TelegramMode;
use Tests\TestCase;

final class TelegramModeFromSettingsTest extends TestCase
{
    public function test_defaults_to_direct(): void
    {
        $this->assertSame(TelegramMode::Direct, TelegramMode::fromSettings([]));
    }

    public function test_parses_business(): void
    {
        $this->assertSame(TelegramMode::Business, TelegramMode::fromSettings(['telegram_mode' => 'business']));
    }

    public function test_invalid_value_falls_back_to_direct(): void
    {
        $this->assertSame(TelegramMode::Direct, TelegramMode::fromSettings(['telegram_mode' => 'nope']));
        $this->assertSame(TelegramMode::Direct, TelegramMode::fromSettings(['telegram_mode' => 123]));
    }
}
