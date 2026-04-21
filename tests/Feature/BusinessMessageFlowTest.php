<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Application\Communication\Action\DeliverOutboundMessageToMessenger;
use App\Application\Communication\Action\ProcessInboundWebhook;
use App\Application\Communication\Action\SendMessage;
use App\Domains\Communication\ValueObject\SenderType;
use App\Domains\Integration\Entity\Source;
use App\Domains\Integration\Messenger\MessengerProviderFactoryInterface;
use App\Domains\Integration\Messenger\MessengerProviderInterface;
use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

final class BusinessMessageFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_business_message_on_business_source_creates_chat_with_connection_id(): void
    {
        $source = $this->createTgSource(['telegram_mode' => 'business']);
        $messenger = $this->mockMessenger();

        app(ProcessInboundWebhook::class)->run($source->id, $messenger, [
            'business_message' => [
                'message_id' => 10,
                'business_connection_id' => 'bc-store',
                'from' => ['id' => 444, 'first_name' => 'X'],
                'text' => 'hello biz',
            ],
        ]);

        $chat = ChatModel::query()->first();
        $this->assertNotNull($chat);
        $this->assertSame('444', $chat->external_user_id);
        $this->assertSame('bc-store', $chat->external_business_connection_id);
        $msg = MessageModel::query()->where('chat_id', $chat->id)->first();
        $this->assertNotNull($msg);
        $this->assertSame('hello biz', $msg->text);
    }

    public function test_business_message_on_direct_source_is_rejected(): void
    {
        Log::spy();
        $source = $this->createTgSource(['telegram_mode' => 'direct']);
        $messenger = $this->mockMessenger();

        app(ProcessInboundWebhook::class)->run($source->id, $messenger, [
            'business_message' => [
                'message_id' => 1,
                'business_connection_id' => 'bc',
                'from' => ['id' => 1],
                'text' => 'nope',
            ],
        ]);

        $this->assertSame(0, ChatModel::query()->count());
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $m, array $ctx): bool => $m === 'business_message arrived on non-business source'
                && ($ctx['source_id'] ?? null) === $source->id);
    }

    public function test_direct_message_on_business_source_is_rejected(): void
    {
        Log::spy();
        $source = $this->createTgSource(['telegram_mode' => 'business']);
        $messenger = $this->mockMessenger();

        app(ProcessInboundWebhook::class)->run($source->id, $messenger, [
            'message' => [
                'message_id' => 1,
                'from' => ['id' => 1],
                'text' => 'direct',
            ],
        ]);

        $this->assertSame(0, ChatModel::query()->count());
        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $m, array $ctx): bool => $m === 'direct message arrived on business source'
                && ($ctx['source_id'] ?? null) === $source->id);
    }

    public function test_outbound_message_uses_business_connection_id_from_chat(): void
    {
        $source = $this->createTgSource(['telegram_mode' => 'business']);
        $dept = DepartmentModel::query()->where('source_id', $source->id)->firstOrFail();
        $chat = ChatModel::query()->create([
            'source_id' => $source->id,
            'department_id' => $dept->id,
            'external_user_id' => '999',
            'external_business_connection_id' => 'xyz',
            'user_metadata' => [],
            'status' => 'new',
        ]);
        $message = MessageModel::query()->create([
            'chat_id' => $chat->id,
            'sender_type' => 'moderator',
            'sender_id' => null,
            'text' => 'reply',
            'payload' => [],
        ]);

        $messenger = $this->createMock(MessengerProviderInterface::class);
        $messenger->expects($this->once())
            ->method('sendMessage')
            ->with(
                '999',
                'reply',
                $this->callback(fn (array $o): bool => ($o['business_connection_id'] ?? null) === 'xyz'
                    && ($o['message_id'] ?? null) === $message->id),
            );

        app(DeliverOutboundMessageToMessenger::class)->run($message, $messenger, $chat);
    }

    public function test_outbound_fallback_to_source_business_connection_id(): void
    {
        $source = $this->createTgSource([
            'telegram_mode' => 'business',
            'business_connection_id' => 'fallback-id',
        ]);
        $dept = DepartmentModel::query()->where('source_id', $source->id)->firstOrFail();
        $chat = ChatModel::query()->create([
            'source_id' => $source->id,
            'department_id' => $dept->id,
            'external_user_id' => '888',
            'external_business_connection_id' => null,
            'user_metadata' => [],
            'status' => 'new',
        ]);
        $message = MessageModel::query()->create([
            'chat_id' => $chat->id,
            'sender_type' => 'moderator',
            'sender_id' => null,
            'text' => 'hi',
            'payload' => [],
        ]);

        $messenger = $this->createMock(MessengerProviderInterface::class);
        $messenger->expects($this->once())
            ->method('sendMessage')
            ->with(
                '888',
                'hi',
                $this->callback(fn (array $o): bool => ($o['business_connection_id'] ?? null) === 'fallback-id'),
            );

        app(DeliverOutboundMessageToMessenger::class)->run($message, $messenger, $chat);
    }

    public function test_outbound_direct_source_does_not_send_business_connection_id(): void
    {
        $source = $this->createTgSource(['telegram_mode' => 'direct']);
        $dept = DepartmentModel::query()->where('source_id', $source->id)->firstOrFail();
        $chat = ChatModel::query()->create([
            'source_id' => $source->id,
            'department_id' => $dept->id,
            'external_user_id' => '1',
            'external_business_connection_id' => null,
            'user_metadata' => [],
            'status' => 'new',
        ]);
        $message = MessageModel::query()->create([
            'chat_id' => $chat->id,
            'sender_type' => 'moderator',
            'sender_id' => null,
            'text' => 'x',
            'payload' => [],
        ]);

        $messenger = $this->createMock(MessengerProviderInterface::class);
        $messenger->expects($this->once())
            ->method('sendMessage')
            ->with(
                '1',
                'x',
                $this->callback(fn (array $o): bool => ! array_key_exists('business_connection_id', $o)
                    || $o['business_connection_id'] === null),
            );

        app(DeliverOutboundMessageToMessenger::class)->run($message, $messenger, $chat);
    }

    public function test_edited_business_message_is_ignored_without_error(): void
    {
        Log::spy();
        $source = $this->createTgSource(['telegram_mode' => 'business']);
        $messenger = $this->mockMessenger();

        app(ProcessInboundWebhook::class)->run($source->id, $messenger, [
            'edited_business_message' => [
                'message_id' => 1,
                'text' => 'edited',
            ],
        ]);

        $this->assertSame(0, ChatModel::query()->count());
        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $m, array $ctx): bool => str_contains($m, 'Business edit/delete event ignored'));
    }

    public function test_welcome_and_offline_auto_reply_work_in_business_mode(): void
    {
        $source = $this->createTgSource([
            'telegram_mode' => 'business',
            'business_connection_id' => 'welcome-bc',
            'welcome_enabled' => true,
            'welcome_text' => 'Hello!',
        ]);
        $messenger = $this->mockMessenger();
        $messenger->expects($this->once())
            ->method('sendMessage')
            ->with('777', 'Hello!', ['business_connection_id' => 'welcome-bc']);

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

        app(ProcessInboundWebhook::class)->run($source->id, $messenger, [
            'business_message' => [
                'from' => ['id' => 777],
                'text' => '/start',
            ],
        ]);

        $this->assertSame(0, ChatModel::query()->count());
    }

    public function test_send_message_system_delivery_includes_business_connection_id(): void
    {
        $source = $this->createTgSource([
            'telegram_mode' => 'business',
            'business_connection_id' => 'sys-bc',
        ]);
        $dept = DepartmentModel::query()->where('source_id', $source->id)->firstOrFail();
        $chat = ChatModel::query()->create([
            'source_id' => $source->id,
            'department_id' => $dept->id,
            'external_user_id' => '42',
            'external_business_connection_id' => null,
            'user_metadata' => [],
            'status' => 'new',
        ]);

        $messenger = $this->createMock(MessengerProviderInterface::class);
        $messenger->expects($this->once())
            ->method('sendMessage')
            ->with(
                '42',
                'auto',
                $this->callback(fn (array $o): bool => ($o['business_connection_id'] ?? null) === 'sys-bc'),
            );

        app(SendMessage::class)->run(
            $chat->id,
            'auto',
            SenderType::System,
            null,
            $messenger,
            [],
            null,
            null,
            true,
        );
    }

    private function createTgSource(array $settings): SourceModel
    {
        $source = SourceModel::query()->create([
            'name' => 'TG flow',
            'type' => 'tg',
            'identifier' => 'tg_flow_'.uniqid(),
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
}
