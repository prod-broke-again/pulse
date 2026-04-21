<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Communication\Action\ProcessInboundWebhook;
use App\Domains\Integration\Entity\Source;
use App\Domains\Integration\Messenger\MessengerProviderFactoryInterface;
use App\Domains\Integration\Messenger\MessengerProviderInterface;
use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class WelcomeMessageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
        Cache::flush();
    }

    public function test_welcome_sent_for_tg_start_when_enabled(): void
    {
        $source = $this->createSourceWithDepartment('tg', [
            'welcome_enabled' => true,
            'welcome_text' => 'Здравствуйте!',
        ]);
        $messenger = $this->mockMessenger();
        $messenger->expects($this->once())
            ->method('sendMessage')
            ->with('777888', 'Здравствуйте!');

        $this->bindMessengerFactory($messenger);
        $this->runWebhook($source->id, $messenger, [
            'message' => ['from' => ['id' => 777888], 'text' => '/start'],
        ]);

        $this->assertSame(0, ChatModel::query()->count());
        $this->assertSame(0, MessageModel::query()->count());
    }

    public function test_welcome_not_sent_when_disabled_and_chat_not_created(): void
    {
        $source = $this->createSourceWithDepartment('tg', [
            'welcome_enabled' => false,
            'welcome_text' => 'Здравствуйте!',
        ]);
        $messenger = $this->mockMessenger();
        $messenger->expects($this->never())->method('sendMessage');

        $this->bindMessengerFactory($messenger);
        $this->runWebhook($source->id, $messenger, [
            'message' => ['from' => ['id' => 777888], 'text' => '/start'],
        ]);

        $this->assertSame(0, ChatModel::query()->count());
    }

    public function test_welcome_not_sent_when_text_empty_and_chat_not_created(): void
    {
        $source = $this->createSourceWithDepartment('tg', [
            'welcome_enabled' => true,
            'welcome_text' => '',
        ]);
        $messenger = $this->mockMessenger();
        $messenger->expects($this->never())->method('sendMessage');

        $this->bindMessengerFactory($messenger);
        $this->runWebhook($source->id, $messenger, [
            'message' => ['from' => ['id' => 777888], 'text' => '/start'],
        ]);

        $this->assertSame(0, ChatModel::query()->count());
    }

    public function test_welcome_throttled_within_five_minutes(): void
    {
        Carbon::setTestNow('2026-04-21 10:00:00');

        $source = $this->createSourceWithDepartment('tg', [
            'welcome_enabled' => true,
            'welcome_text' => 'Здравствуйте!',
        ]);
        $messenger = $this->mockMessenger();
        $messenger->expects($this->exactly(2))
            ->method('sendMessage')
            ->with('777888', 'Здравствуйте!');

        $this->bindMessengerFactory($messenger);

        $payload = ['message' => ['from' => ['id' => 777888], 'text' => '/start']];
        $this->runWebhook($source->id, $messenger, $payload);

        Carbon::setTestNow('2026-04-21 10:02:00');
        $this->runWebhook($source->id, $messenger, $payload);

        Carbon::setTestNow('2026-04-21 10:06:00');
        $this->runWebhook($source->id, $messenger, $payload);

        Carbon::setTestNow();
    }

    public function test_welcome_for_vk_payload_start(): void
    {
        $source = $this->createSourceWithDepartment('vk', [
            'welcome_enabled' => true,
            'welcome_text' => 'Здравствуйте!',
        ]);
        $messenger = $this->mockMessenger();
        $messenger->expects($this->once())->method('sendMessage')->with('200100', 'Здравствуйте!');

        $this->bindMessengerFactory($messenger);
        $this->runWebhook($source->id, $messenger, [
            'object' => ['message' => ['from_id' => 200100, 'payload' => '{"command":"start"}']],
        ]);

        $this->assertSame(0, ChatModel::query()->count());
    }

    public function test_welcome_for_vk_text_nachat(): void
    {
        $source = $this->createSourceWithDepartment('vk', [
            'welcome_enabled' => true,
            'welcome_text' => 'Здравствуйте!',
        ]);
        $messenger = $this->mockMessenger();
        $messenger->expects($this->once())->method('sendMessage')->with('200100', 'Здравствуйте!');

        $this->bindMessengerFactory($messenger);
        $this->runWebhook($source->id, $messenger, [
            'object' => ['message' => ['from_id' => 200100, 'text' => 'Начать']],
        ]);

        $this->assertSame(0, ChatModel::query()->count());
    }

    public function test_regular_message_after_start_creates_chat_without_start_message(): void
    {
        $source = $this->createSourceWithDepartment('tg', [
            'welcome_enabled' => true,
            'welcome_text' => 'Здравствуйте!',
        ]);
        $messenger = $this->mockMessenger();
        $messenger->expects($this->once())->method('sendMessage')->with('777888', 'Здравствуйте!');

        $this->bindMessengerFactory($messenger);

        $this->runWebhook($source->id, $messenger, [
            'message' => ['message_id' => 1, 'from' => ['id' => 777888], 'text' => '/start'],
        ]);
        $this->runWebhook($source->id, $messenger, [
            'message' => ['message_id' => 2, 'from' => ['id' => 777888], 'text' => 'Мне нужна помощь'],
        ]);

        $chat = ChatModel::query()->first();
        $this->assertNotNull($chat);
        $messages = MessageModel::query()->where('chat_id', $chat->id)->orderBy('id')->pluck('text')->all();

        $this->assertSame(['Мне нужна помощь'], $messages);
    }

    public function test_welcome_send_failure_does_not_crash_webhook(): void
    {
        $source = $this->createSourceWithDepartment('tg', [
            'welcome_enabled' => true,
            'welcome_text' => 'Здравствуйте!',
        ]);
        $messenger = $this->mockMessenger();
        $messenger->expects($this->once())
            ->method('sendMessage')
            ->willThrowException(new \RuntimeException('Network down'));

        Log::spy();
        $this->bindMessengerFactory($messenger);

        app(ProcessInboundWebhook::class)->run(
            $source->id,
            $messenger,
            ['message' => ['from' => ['id' => 777888], 'text' => '/start']],
        );

        Log::shouldHaveReceived('warning')->once();
    }

    public function test_welcome_not_retried_after_send_failure_within_throttle(): void
    {
        Carbon::setTestNow('2026-04-21 10:00:00');

        $source = $this->createSourceWithDepartment('tg', [
            'welcome_enabled' => true,
            'welcome_text' => 'Здравствуйте!',
        ]);
        $messenger = $this->mockMessenger();
        $messenger->expects($this->once())
            ->method('sendMessage')
            ->with('777888', 'Здравствуйте!')
            ->willThrowException(new \RuntimeException('Network down'));

        Log::spy();
        $this->bindMessengerFactory($messenger);

        $payload = ['message' => ['from' => ['id' => 777888], 'text' => '/start']];
        $this->runWebhook($source->id, $messenger, $payload);

        Carbon::setTestNow('2026-04-21 10:02:00');
        $this->runWebhook($source->id, $messenger, $payload);

        Carbon::setTestNow();
        Log::shouldHaveReceived('warning')->once();
    }

    private function createSourceWithDepartment(string $type, array $settings): SourceModel
    {
        $source = SourceModel::query()->create([
            'name' => strtoupper($type).' source',
            'type' => $type,
            'identifier' => $type.'_'.uniqid(),
            'secret_key' => null,
            'settings' => $settings,
        ]);
        DepartmentModel::query()->create([
            'source_id' => $source->id,
            'name' => 'Support',
            'slug' => 'support_'.uniqid(),
            'is_active' => true,
        ]);

        return $source;
    }

    private function mockMessenger(): MessengerProviderInterface
    {
        $mock = $this->createMock(MessengerProviderInterface::class);
        $mock->method('validateWebhook')->willReturn(true);

        return $mock;
    }

    private function bindMessengerFactory(MessengerProviderInterface $messenger): void
    {
        $this->app->bind(MessengerProviderFactoryInterface::class, fn () => new class($messenger) implements MessengerProviderFactoryInterface
        {
            public function __construct(
                private readonly MessengerProviderInterface $messenger,
            ) {}

            public function forSource(Source $source): MessengerProviderInterface
            {
                return $this->messenger;
            }
        });
    }

    /** @param array<string, mixed> $payload */
    private function runWebhook(int $sourceId, MessengerProviderInterface $messenger, array $payload): void
    {
        app(ProcessInboundWebhook::class)->run($sourceId, $messenger, $payload);
    }
}
