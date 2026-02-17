<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Jobs\ProcessIncomingMessageJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProcessIncomingMessageJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_job_creates_chat_and_message_for_telegram_payload(): void
    {
        $source = SourceModel::create([
            'name' => 'TG',
            'type' => 'tg',
            'identifier' => 'tg_bot',
            'secret_key' => null,
            'settings' => [],
        ]);
        $department = DepartmentModel::create([
            'source_id' => $source->id,
            'name' => 'Support',
            'slug' => 'support',
            'is_active' => true,
        ]);

        $payload = [
            'update_id' => 100,
            'message' => [
                'message_id' => 501,
                'from' => ['id' => 777888, 'first_name' => 'Alice'],
                'text' => 'Нужна помощь с заказом',
            ],
        ];

        $job = new ProcessIncomingMessageJob($source->id, $payload);
        $job->handle(
            app(\App\Application\Integration\ResolveMessengerProvider::class),
            app(\App\Application\Communication\Action\ProcessInboundWebhook::class),
        );

        $chat = ChatModel::where('source_id', $source->id)->where('external_user_id', '777888')->first();
        $this->assertNotNull($chat);
        $this->assertSame($department->id, $chat->department_id);
        $this->assertSame('new', $chat->status);

        $message = MessageModel::where('chat_id', $chat->id)->first();
        $this->assertNotNull($message);
        $this->assertSame('501', $message->external_message_id);
        $this->assertSame('client', $message->sender_type);
        $this->assertSame('Нужна помощь с заказом', $message->text);
    }

    public function test_job_idempotency_duplicate_payload_creates_only_one_message(): void
    {
        $source = SourceModel::create([
            'name' => 'VK',
            'type' => 'vk',
            'identifier' => 'vk_group',
            'secret_key' => null,
            'settings' => [],
        ]);
        DepartmentModel::create([
            'source_id' => $source->id,
            'name' => 'Sales',
            'slug' => 'sales',
            'is_active' => true,
        ]);

        $payload = [
            'type' => 'message_new',
            'object' => [
                'message_id' => 123,
                'user_id' => 200100,
                'body' => 'Повторное сообщение',
            ],
            'user_id' => '200100',
            'body' => 'Повторное сообщение',
        ];

        $job = new ProcessIncomingMessageJob($source->id, $payload);
        $handle = fn () => $job->handle(
            app(\App\Application\Integration\ResolveMessengerProvider::class),
            app(\App\Application\Communication\Action\ProcessInboundWebhook::class),
        );

        $handle();
        $handle();

        $chat = ChatModel::where('source_id', $source->id)->where('external_user_id', '200100')->first();
        $this->assertNotNull($chat);
        $count = MessageModel::where('chat_id', $chat->id)->count();
        $this->assertSame(1, $count);
    }
}
