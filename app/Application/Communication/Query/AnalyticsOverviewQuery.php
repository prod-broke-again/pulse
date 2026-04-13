<?php

declare(strict_types=1);

namespace App\Application\Communication\Query;

use App\Domains\Communication\ValueObject\SenderType;
use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

final readonly class AnalyticsOverviewQuery
{
    /**
     * @return array{
     *     period: array{from: string, to: string},
     *     chats_created: int,
     *     chats_closed: int,
     *     messages_total: int,
     *     messages_from_clients: int,
     *     messages_from_moderators: int,
     *     messages_from_system: int,
     * }
     */
    public function run(User $user, CarbonInterface $from, CarbonInterface $to, ?int $sourceId = null): array
    {
        $chatsCreated = $this->scopedChatQuery($user, $sourceId)
            ->whereBetween('chats.created_at', [$from, $to])
            ->count();

        $chatsClosed = $this->scopedChatQuery($user, $sourceId)
            ->where('chats.status', 'closed')
            ->whereBetween('chats.updated_at', [$from, $to])
            ->count();

        $messagesTotal = $this->scopedMessageQuery($user, $sourceId)
            ->whereBetween('messages.created_at', [$from, $to])
            ->count();

        $messagesFromClients = $this->scopedMessageQuery($user, $sourceId)
            ->where('messages.sender_type', SenderType::Client->value)
            ->whereBetween('messages.created_at', [$from, $to])
            ->count();

        $messagesFromModerators = $this->scopedMessageQuery($user, $sourceId)
            ->where('messages.sender_type', SenderType::Moderator->value)
            ->whereBetween('messages.created_at', [$from, $to])
            ->count();

        $messagesFromSystem = $this->scopedMessageQuery($user, $sourceId)
            ->where('messages.sender_type', SenderType::System->value)
            ->whereBetween('messages.created_at', [$from, $to])
            ->count();

        return [
            'period' => [
                'from' => $from->toIso8601String(),
                'to' => $to->toIso8601String(),
            ],
            'chats_created' => $chatsCreated,
            'chats_closed' => $chatsClosed,
            'messages_total' => $messagesTotal,
            'messages_from_clients' => $messagesFromClients,
            'messages_from_moderators' => $messagesFromModerators,
            'messages_from_system' => $messagesFromSystem,
        ];
    }

    /**
     * @param  Builder<ChatModel>|Builder<MessageModel>  $query
     */
    private function applyVisibilityScope(Builder $query, User $user, string $chatTable = 'chats'): void
    {
        if ($user->isAdmin()) {
            return;
        }

        $sourceIds = $user->sources()->pluck('id')->all();
        if ($sourceIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn($chatTable.'.source_id', $sourceIds);

        $deptIds = $user->departments()->pluck('departments.id')->all();
        if ($deptIds !== []) {
            $query->whereIn($chatTable.'.department_id', $deptIds);
        }
    }

    /** @return Builder<ChatModel> */
    private function scopedChatQuery(User $user, ?int $sourceId): Builder
    {
        $query = ChatModel::query();
        $this->applyVisibilityScope($query, $user, 'chats');

        if ($sourceId !== null) {
            $query->where('chats.source_id', $sourceId);
        }

        return $query;
    }

    /** @return Builder<MessageModel> */
    private function scopedMessageQuery(User $user, ?int $sourceId): Builder
    {
        $query = MessageModel::query()
            ->join('chats', 'chats.id', '=', 'messages.chat_id');

        $this->applyVisibilityScope($query, $user, 'chats');

        if ($sourceId !== null) {
            $query->where('chats.source_id', $sourceId);
        }

        return $query;
    }
}
