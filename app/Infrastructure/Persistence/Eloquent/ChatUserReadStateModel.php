<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $chat_id
 * @property int|null $last_read_message_id
 */
class ChatUserReadStateModel extends Model
{
    protected $table = 'chat_user_read_states';

    protected $fillable = [
        'user_id',
        'chat_id',
        'last_read_message_id',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(ChatModel::class, 'chat_id');
    }

    public function lastReadMessage(): BelongsTo
    {
        return $this->belongsTo(MessageModel::class, 'last_read_message_id');
    }
}
