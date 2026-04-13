<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domains\Communication\ValueObject\ChatStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $source_id
 * @property int $department_id
 * @property string $external_user_id
 * @property array|null $user_metadata
 * @property string $status
 * @property int|null $assigned_to
 * @property string|null $topic
 * @property \Illuminate\Support\Carbon|null $last_auto_reply_at
 * @property-read int|null $unread_count
 */
class ChatModel extends Model
{
    protected $table = 'chats';

    protected $fillable = [
        'source_id',
        'department_id',
        'external_user_id',
        'user_metadata',
        'status',
        'assigned_to',
        'topic',
        'last_auto_reply_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_metadata' => 'array',
            'last_auto_reply_at' => 'datetime',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(SourceModel::class, 'source_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(DepartmentModel::class, 'department_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(MessageModel::class, 'chat_id');
    }

    public function latestMessage(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(MessageModel::class, 'chat_id')->latestOfMany();
    }

    public function userReadStates(): HasMany
    {
        return $this->hasMany(ChatUserReadStateModel::class, 'chat_id');
    }

    /** @param  Builder<ChatModel>  $query */
    public function scopeWithUnreadCountForUser(Builder $query, User $user): void
    {
        $query->withCount([
            'messages as unread_count' => function (Builder $q) use ($user): void {
                $q->where('sender_type', 'client')
                    ->whereRaw(
                        'messages.id > COALESCE((
                            SELECT curs.last_read_message_id
                            FROM chat_user_read_states AS curs
                            WHERE curs.chat_id = messages.chat_id
                              AND curs.user_id = ?
                            LIMIT 1
                        ), 0)',
                        [$user->id]
                    );
            },
        ]);
    }

    public function loadUnreadCountForUser(User $user): void
    {
        $this->loadCount([
            'messages as unread_count' => function (Builder $q) use ($user): void {
                $q->where('sender_type', 'client')
                    ->whereRaw(
                        'messages.id > COALESCE((
                            SELECT curs.last_read_message_id
                            FROM chat_user_read_states AS curs
                            WHERE curs.chat_id = messages.chat_id
                              AND curs.user_id = ?
                            LIMIT 1
                        ), 0)',
                        [$user->id]
                    );
            },
        ]);
    }

    /** Chat has been without moderator response for more than 5 minutes. */
    public function isUrgent(): bool
    {
        $last = MessageModel::where('chat_id', $this->id)
            ->orderByDesc('id')
            ->first();
        if ($last === null || $last->sender_type === 'moderator' || $last->sender_type === 'system') {
            return false;
        }

        return $last->created_at?->lt(now()->subMinutes(5)) ?? false;
    }

    public function getStatusEnum(): ChatStatus
    {
        return ChatStatus::from($this->status);
    }
}
