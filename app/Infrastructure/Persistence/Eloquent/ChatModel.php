<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domains\Communication\ValueObject\ChatStatus;
use App\Models\User;
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
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'user_metadata' => 'array',
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

    public function getStatusEnum(): ChatStatus
    {
        return ChatStatus::from($this->status);
    }
}
