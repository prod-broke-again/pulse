<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent;

use App\Domains\Integration\ValueObject\SourceType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $type
 * @property string $identifier
 * @property string|null $secret_key
 * @property array|null $settings
 */
class SourceModel extends Model
{
    protected $table = 'sources';

    protected $fillable = [
        'name',
        'type',
        'identifier',
        'secret_key',
        'settings',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function departments(): HasMany
    {
        return $this->hasMany(DepartmentModel::class, 'source_id');
    }

    public function chats(): HasMany
    {
        return $this->hasMany(ChatModel::class, 'source_id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            \App\Models\User::class,
            'source_user',
            foreignPivotKey: 'source_id',
            relatedPivotKey: 'user_id',
        );
    }

    public function getTypeEnum(): SourceType
    {
        return SourceType::from($this->type);
    }
}
