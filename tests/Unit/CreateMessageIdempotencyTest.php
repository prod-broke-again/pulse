<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Application\Communication\Action\CreateMessage;
use App\Domains\Communication\ValueObject\SenderType;
use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CreateMessageIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_creating_message_with_same_external_message_id_returns_existing(): void
    {
        $source = SourceModel::create([
            'name' => 'S',
            'type' => 'tg',
            'identifier' => 'tg_x',
            'secret_key' => null,
            'settings' => [],
        ]);
        $department = DepartmentModel::create([
            'source_id' => $source->id,
            'name' => 'D',
            'slug' => 'd',
            'is_active' => true,
        ]);
        $chat = ChatModel::create([
            'source_id' => $source->id,
            'department_id' => $department->id,
            'external_user_id' => 'user1',
            'user_metadata' => [],
            'status' => 'new',
            'assigned_to' => null,
        ]);

        $createMessage = app(CreateMessage::class);

        $first = $createMessage->run(
            chatId: $chat->id,
            text: 'First',
            senderType: SenderType::Client,
            senderId: null,
            payload: [],
            externalMessageId: 'ext_123',
        );

        $second = $createMessage->run(
            chatId: $chat->id,
            text: 'Second (duplicate)',
            senderType: SenderType::Client,
            senderId: null,
            payload: [],
            externalMessageId: 'ext_123',
        );

        $this->assertSame($first->id, $second->id);
        $this->assertSame('First', $second->text);
        $this->assertSame(1, MessageModel::where('chat_id', $chat->id)->count());
    }
}
