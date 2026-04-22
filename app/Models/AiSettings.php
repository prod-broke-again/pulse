<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Singleton row (id=1) for admin-editable AI prompt fragments and limits.
 *
 * @property int $id
 * @property string|null $extra_kickoff_instructions
 * @property string|null $autoreply_rules
 * @property int $web_max_auto_replies
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class AiSettings extends Model
{
    protected $table = 'ai_settings';

    protected $fillable = [
        'extra_kickoff_instructions',
        'autoreply_rules',
        'web_max_auto_replies',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'web_max_auto_replies' => 'integer',
        ];
    }

    public static function singleton(): self
    {
        $row = self::query()->find(1);
        if ($row === null) {
            $row = new self;
            $row->id = 1;
            $row->web_max_auto_replies = 3;
            $row->save();
        }

        return $row;
    }
}
