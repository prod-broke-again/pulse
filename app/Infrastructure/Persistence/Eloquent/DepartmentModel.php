<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $source_id
 * @property string $name
 * @property string $slug
 * @property bool $is_active
 */
class DepartmentModel extends Model
{
    protected $table = 'departments';

    protected $fillable = [
        'source_id',
        'name',
        'slug',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(SourceModel::class, 'source_id');
    }

    public function chats(): HasMany
    {
        return $this->hasMany(ChatModel::class, 'department_id');
    }
}
