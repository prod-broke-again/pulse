<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\AiKickoffPrompt;
use PHPUnit\Framework\TestCase;

final class AiKickoffPromptTest extends TestCase
{
    public function test_parse_includes_department_and_confidence(): void
    {
        $json = '{"topic":"Заказ","summary":"Клиент хочет оплатить.","intent_tag":"оплата","replies":[{"id":"r1","text":"Да"},{"id":"r2","text":"Нет"}],"suggested_department_id":12,"confidence":0.88}';
        $dto = AiKickoffPrompt::parse($json);

        $this->assertSame('Заказ', $dto->topic);
        $this->assertSame(12, $dto->suggestedDepartmentId);
        $this->assertSame(0.88, $dto->confidence);
        $this->assertCount(2, $dto->replies);
    }

    public function test_parse_null_suggested_department(): void
    {
        $json = '{"topic":"Вопрос","summary":"Общее.","intent_tag":"общее","replies":[],"suggested_department_id":null,"confidence":0.3}';
        $dto = AiKickoffPrompt::parse($json);

        $this->assertNull($dto->suggestedDepartmentId);
        $this->assertSame(0.3, $dto->confidence);
    }

    public function test_parse_confidence_clamped_above_one(): void
    {
        $json = '{"topic":"A","summary":"B","intent_tag":"c","replies":[],"suggested_department_id":1,"confidence":1.5}';
        $dto = AiKickoffPrompt::parse($json);

        $this->assertSame(1.0, $dto->confidence);
    }

    public function test_parse_confidence_invalid_string_yields_null(): void
    {
        $json = '{"topic":"A","summary":"B","intent_tag":"c","replies":[],"suggested_department_id":1,"confidence":"abc"}';
        $dto = AiKickoffPrompt::parse($json);

        $this->assertNull($dto->confidence);
    }

    public function test_parse_invalid_json_returns_empty_dto(): void
    {
        $dto = AiKickoffPrompt::parse('not json');

        $this->assertNull($dto->topic);
        $this->assertSame('', $dto->summary);
        $this->assertNull($dto->suggestedDepartmentId);
        $this->assertNull($dto->confidence);
    }

    public function test_parse_legacy_json_without_department_fields(): void
    {
        $json = '{"topic":"Старый формат","summary":"Текст.","intent_tag":"метка","replies":[{"id":"r1","text":"Ок"}]}';
        $dto = AiKickoffPrompt::parse($json);

        $this->assertSame('Старый формат', $dto->topic);
        $this->assertSame('Текст.', $dto->summary);
        $this->assertSame('метка', $dto->intentTag);
        $this->assertNull($dto->suggestedDepartmentId);
        $this->assertNull($dto->confidence);
        $this->assertCount(1, $dto->replies);
    }
}
