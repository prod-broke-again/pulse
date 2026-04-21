<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\SourceModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $owner_user_id
 * @property string|null $scope_type
 * @property int|null $scope_id
 * @property string $title
 * @property string $url
 * @property bool $is_active
 * @property int $sort_order
 */
class QuickLink extends Model
{
    protected $table = 'quick_links';

    protected $fillable = [
        'owner_user_id',
        'scope_type',
        'scope_id',
        'title',
        'url',
        'is_active',
        'sort_order',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function sourceForScope(): BelongsTo
    {
        return $this->belongsTo(SourceModel::class, 'scope_id');
    }
}
