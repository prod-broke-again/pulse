<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Communication\Webhook\StartCommandDetector;
use App\Domains\Integration\ValueObject\SourceType;
use PHPUnit\Framework\TestCase;

final class StartCommandDetectorTest extends TestCase
{
    public function test_tg_start_plain_detected(): void
    {
        $detector = new StartCommandDetector;

        $this->assertTrue($detector->isStartCommand(SourceType::Tg, [
            'message' => ['text' => '/start'],
        ]));
    }

    public function test_tg_start_in_business_message_detected(): void
    {
        $detector = new StartCommandDetector;

        $this->assertTrue($detector->isStartCommand(SourceType::Tg, [
            'business_message' => ['text' => '/start'],
        ]));
    }

    public function test_tg_start_with_payload_detected(): void
    {
        $detector = new StartCommandDetector;

        $this->assertTrue($detector->isStartCommand(SourceType::Tg, [
            'message' => ['text' => '/start ref_abc'],
        ]));
    }

    public function test_tg_start_with_botname_detected(): void
    {
        $detector = new StartCommandDetector;

        $this->assertTrue($detector->isStartCommand(SourceType::Tg, [
            'message' => ['text' => '/start@MyBot'],
        ]));
    }

    public function test_tg_startup_not_detected(): void
    {
        $detector = new StartCommandDetector;

        $this->assertFalse($detector->isStartCommand(SourceType::Tg, [
            'message' => ['text' => '/startup please'],
        ]));
    }

    public function test_tg_regular_text_not_detected(): void
    {
        $detector = new StartCommandDetector;

        $this->assertFalse($detector->isStartCommand(SourceType::Tg, [
            'message' => ['text' => 'привет'],
        ]));
    }

    public function test_vk_payload_command_start_detected(): void
    {
        $detector = new StartCommandDetector;

        $this->assertTrue($detector->isStartCommand(SourceType::Vk, [
            'object' => ['message' => ['payload' => '{"command":"start"}']],
        ]));
    }

    public function test_vk_text_nachat_detected(): void
    {
        $detector = new StartCommandDetector;

        $this->assertTrue($detector->isStartCommand(SourceType::Vk, [
            'object' => ['message' => ['text' => 'Начать']],
        ]));
    }

    public function test_vk_text_nachat_lowercase_detected(): void
    {
        $detector = new StartCommandDetector;

        $this->assertTrue($detector->isStartCommand(SourceType::Vk, [
            'object' => ['message' => ['text' => 'начать']],
        ]));
    }

    public function test_vk_text_start_detected(): void
    {
        $detector = new StartCommandDetector;

        $this->assertTrue($detector->isStartCommand(SourceType::Vk, [
            'object' => ['message' => ['text' => 'Start']],
        ]));
    }

    public function test_vk_regular_text_not_detected(): void
    {
        $detector = new StartCommandDetector;

        $this->assertFalse($detector->isStartCommand(SourceType::Vk, [
            'object' => ['message' => ['text' => 'здравствуйте']],
        ]));
    }

    public function test_vk_broken_payload_json_not_detected(): void
    {
        $detector = new StartCommandDetector;

        $this->assertFalse($detector->isStartCommand(SourceType::Vk, [
            'object' => ['message' => ['payload' => '{broken']],
        ]));
    }

    public function test_max_start_detected(): void
    {
        $detector = new StartCommandDetector;

        $this->assertTrue($detector->isStartCommand(SourceType::Max, [
            'message' => ['text' => '/start'],
        ]));
    }

    public function test_web_source_ignored(): void
    {
        $detector = new StartCommandDetector;

        $this->assertFalse($detector->isStartCommand(SourceType::Web, [
            'message' => ['text' => '/start'],
        ]));
    }
}
