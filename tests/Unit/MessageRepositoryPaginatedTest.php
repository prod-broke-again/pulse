<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Communication\Repository\MessageRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class MessageRepositoryPaginatedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\RolesAndPermissionsSeeder::class);
    }

    public function test_list_by_chat_id_paginated_returns_newest_first_with_before_id(): void
    {
        $source = SourceModel::create([
            'name' => 'S',
            'type' => 'web',
            'identifier' => 'w1',
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
            'external_user_id' => 'u1',
            'user_metadata' => [],
            'status' => 'new',
            'assigned_to' => null,
        ]);

        foreach (['A', 'B', 'C', 'D', 'E'] as $i => $text) {
            MessageModel::create([
                'chat_id' => $chat->id,
                'external_message_id' => null,
                'sender_id' => null,
                'sender_type' => 'client',
                'text' => $text,
                'payload' => [],
                'is_read' => false,
            ]);
        }

        $repo = app(MessageRepositoryInterface::class);

        $page1 = $repo->listByChatIdPaginated($chat->id, 3, null);
        $this->assertCount(3, $page1);
        $this->assertSame('C', $page1[0]->text);
        $this->assertSame('D', $page1[1]->text);
        $this->assertSame('E', $page1[2]->text);

        $oldestInPage1 = $page1[0]->id;
        $page2 = $repo->listByChatIdPaginated($chat->id, 3, $oldestInPage1);
        $this->assertCount(2, $page2);
        $this->assertSame('A', $page2[0]->text);
        $this->assertSame('B', $page2[1]->text);
    }
}
