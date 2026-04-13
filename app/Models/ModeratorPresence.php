<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property bool $manual_online
 * @property \Illuminate\Support\Carbon|null $last_heartbeat_at
 * @property \Illuminate\Support\Carbon|null $last_activity_at
 */
class ModeratorPresence extends Model
{
    protected $fillable = [
        'user_id',
        'manual_online',
        'last_heartbeat_at',
        'last_activity_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'manual_online' => 'boolean',
            'last_heartbeat_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
