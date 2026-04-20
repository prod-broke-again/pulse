<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domains\Department\ValueObject\DepartmentCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $source_id
 * @property string $name
 * @property string $slug
 * @property string $category
 * @property string|null $icon
 * @property bool $ai_enabled
 * @property bool $is_active
 */
class DepartmentModel extends Model
{
    protected $table = 'departments';

    protected $fillable = [
        'source_id',
        'name',
        'slug',
        'category',
        'icon',
        'ai_enabled',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'category' => DepartmentCategory::class,
            'is_active' => 'boolean',
            'ai_enabled' => 'boolean',
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

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\User::class,
            'department_user',
            foreignPivotKey: 'department_id',
            relatedPivotKey: 'user_id',
        );
    }
}
