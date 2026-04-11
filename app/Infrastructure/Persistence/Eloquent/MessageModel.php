<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domains\Communication\ValueObject\SenderType;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int $chat_id
 * @property string|null $external_message_id
 * @property int|null $sender_id
 * @property string $sender_type
 * @property string $text
 * @property array|null $payload
 * @property array<int, array{text: string, url: string}>|null $reply_markup
 * @property bool $is_read
 * @property int|null $reply_to_id
 */
class MessageModel extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'messages';

    protected $fillable = [
        'chat_id',
        'reply_to_id',
        'external_message_id',
        'sender_id',
        'sender_type',
        'text',
        'payload',
        'reply_markup',
        'is_read',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'reply_markup' => 'array',
            'is_read' => 'boolean',
        ];
    }

    public function chat(): BelongsTo
    {
        return $this->belongsTo(ChatModel::class, 'chat_id');
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_id');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function getSenderTypeEnum(): SenderType
    {
        return SenderType::from($this->sender_type);
    }
}
