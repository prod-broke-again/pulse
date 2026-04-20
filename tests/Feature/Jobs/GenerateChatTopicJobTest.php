<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Application\Ai\Dto\AiChatKickoffDto;
use App\Application\Ai\Dto\AiSuggestedReplyDto;
use App\Application\Ai\Dto\AiThreadSummaryDto;
use App\Application\Communication\Action\ChangeChatDepartment;
use App\Contracts\Ai\AiProviderInterface;
use App\Domains\Communication\Repository\ChatRepositoryInterface;
use App\Domains\Integration\Repository\DepartmentRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Jobs\GenerateChatTopicJob;
use App\Support\ChatAiKickoffCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

final class GenerateChatTopicJobTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private SourceModel $source;

    private DepartmentModel $departmentA;

    private DepartmentModel $departmentB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->source = SourceModel::create([
            'name' => 'Src',
            'type' => 'web',
            'identifier' => 'src_'.uniqid(),
            'secret_key' => null,
            'settings' => [],
        ]);

        $this->departmentA = DepartmentModel::create([
            'source_id' => $this->source->id,
            'name' => 'Dept A',
            'slug' => 'dept-a-'.uniqid(),
            'is_active' => true,
            'ai_enabled' => true,
        ]);

        $this->departmentB = DepartmentModel::create([
            'source_id' => $this->source->id,
            'name' => 'Dept B',
            'slug' => 'dept-b-'.uniqid(),
            'is_active' => true,
            'ai_enabled' => true,
        ]);
    }

    public function test_flag_off_does_not_touch_department_or_audit(): void
    {
        $this->setAiDepartmentFeature(false);

        $chat = $this->makeChatWithClientMessage('Привет');

        $dto = new AiChatKickoffDto(
            topic: 'Тема',
            summary: 'Саммари',
            intentTag: 'тег',
            replies: [new AiSuggestedReplyDto(id: 'r1', text: 'Ответ')],
            suggestedDepartmentId: $this->departmentB->id,
            confidence: 0.99,
        );
        $this->app->instance(AiProviderInterface::class, new DeterministicKickoffAi($dto));

        $this->runJob($chat->id);

        $chat->refresh();
        $this->assertSame('Тема', $chat->topic);
        $this->assertSame($this->departmentA->id, $chat->department_id);
        $this->assertNull($chat->ai_suggested_department_id);
        $this->assertNull($chat->ai_department_confidence);
    }

    public function test_flag_on_high_confidence_calls_run_as_system_and_moves_department(): void
    {
        $this->setAiDepartmentFeature(true, 0.7);

        $chat = $this->makeChatWithClientMessage('Нужен отдел B');

        $dto = new AiChatKickoffDto(
            topic: 'Заказ',
            summary: 'Клиент',
            intentTag: 'продажи',
            replies: [],
            suggestedDepartmentId: $this->departmentB->id,
            confidence: 0.95,
        );
        $this->app->instance(AiProviderInterface::class, new DeterministicKickoffAi($dto));

        $this->runJob($chat->id);

        $chat->refresh();
        $this->assertSame($this->departmentB->id, $chat->department_id);
        $this->assertSame($this->departmentB->id, $chat->ai_suggested_department_id);
        $this->assertEqualsWithDelta(0.95, (float) $chat->ai_department_confidence, 0.001);
        $this->assertNotNull($chat->ai_department_assigned_at);
        $this->assertSame('Заказ', $chat->topic);
    }

    public function test_flag_on_low_confidence_writes_audit_only_no_department_change(): void
    {
        $this->setAiDepartmentFeature(true, 0.7);

        $chat = $this->makeChatWithClientMessage('Размыто');

        $dto = new AiChatKickoffDto(
            topic: 'Общее',
            summary: 'Текст',
            intentTag: null,
            replies: [],
            suggestedDepartmentId: $this->departmentB->id,
            confidence: 0.5,
        );
        $this->app->instance(AiProviderInterface::class, new DeterministicKickoffAi($dto));

        $this->runJob($chat->id);

        $chat->refresh();
        $this->assertSame($this->departmentA->id, $chat->department_id);
        $this->assertSame($this->departmentB->id, $chat->ai_suggested_department_id);
        $this->assertEqualsWithDelta(0.5, (float) $chat->ai_department_confidence, 0.001);
        $this->assertNull($chat->ai_department_assigned_at);
    }

    public function test_flag_on_hallucinated_department_id_skips_audit(): void
    {
        $this->setAiDepartmentFeature(true);

        $chat = $this->makeChatWithClientMessage('Тест');

        $dto = new AiChatKickoffDto(
            topic: 'Тема',
            summary: '',
            replies: [],
            suggestedDepartmentId: 9_999_999,
            confidence: 0.99,
        );
        $this->app->instance(AiProviderInterface::class, new DeterministicKickoffAi($dto));

        $this->runJob($chat->id);

        $chat->refresh();
        $this->assertSame($this->departmentA->id, $chat->department_id);
        $this->assertNull($chat->ai_suggested_department_id);
        $this->assertSame('Тема', $chat->topic);
    }

    public function test_retry_skips_llm_when_audit_already_present_uses_cache_for_topic(): void
    {
        $this->setAiDepartmentFeature(true);

        $chat = $this->makeChatWithClientMessage('Сообщение');
        $msg = MessageModel::where('chat_id', $chat->id)->first();
        $this->assertNotNull($msg);

        $chat->forceFill([
            'ai_suggested_department_id' => $this->departmentB->id,
            'ai_department_confidence' => 0.88,
        ])->save();

        ChatAiKickoffCache::put($chat->id, (int) $msg->id, new AiChatKickoffDto(
            topic: 'Из кеша',
            summary: 'S',
            intentTag: 'i',
            replies: [new AiSuggestedReplyDto(id: 'r1', text: 't')],
        ));

        $this->app->instance(AiProviderInterface::class, new ForbidsKickoffAi);

        $this->runJob($chat->id);

        $chat->refresh();
        $this->assertSame('Из кеша', $chat->topic);
        $this->assertSame($this->departmentB->id, $chat->ai_suggested_department_id);
    }

    private function setAiDepartmentFeature(bool $enabled, float $threshold = 0.7): void
    {
        Config::set('features', array_merge(
            config('features', []),
            [
                'ai_department_assignment' => $enabled,
                'ai_department_confidence_threshold' => $threshold,
            ],
        ));
    }

    private function makeChatWithClientMessage(string $text): ChatModel
    {
        $chat = ChatModel::create([
            'source_id' => $this->source->id,
            'department_id' => $this->departmentA->id,
            'external_user_id' => 'u_'.uniqid(),
            'user_metadata' => [],
            'status' => 'new',
            'assigned_to' => null,
            'topic' => null,
        ]);

        MessageModel::create([
            'chat_id' => $chat->id,
            'sender_id' => null,
            'sender_type' => 'client',
            'text' => $text,
            'payload' => null,
            'is_read' => false,
        ]);

        return $chat;
    }

    private function runJob(int $chatId): void
    {
        Event::fake();

        $job = new GenerateChatTopicJob($chatId);
        $job->handle(
            $this->app->make(ChatRepositoryInterface::class),
            $this->app->make(AiProviderInterface::class),
            $this->app->make(DepartmentRepositoryInterface::class),
            $this->app->make(ChangeChatDepartment::class),
        );
    }
}

final class DeterministicKickoffAi implements AiProviderInterface
{
    public function __construct(
        private readonly AiChatKickoffDto $dto,
    ) {}

    public function generateKickoffFromClientMessages(string $messagesText, array $departments = []): AiChatKickoffDto
    {
        return $this->dto;
    }

    public function generateTopic(string $messagesText): ?string
    {
        return $this->dto->topic;
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

final class ForbidsKickoffAi implements AiProviderInterface
{
    public function generateKickoffFromClientMessages(string $messagesText, array $departments = []): AiChatKickoffDto
    {
        throw new \RuntimeException('generateKickoffFromClientMessages must not be called when AI audit is already done');
    }

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
