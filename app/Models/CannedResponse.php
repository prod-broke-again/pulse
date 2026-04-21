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
 * @property string $code
 * @property string $title
 * @property string $text
 * @property bool $is_active
 */
class CannedResponse extends Model
{
    protected $table = 'canned_responses';

    protected $fillable = [
        'owner_user_id',
        'scope_type',
        'scope_id',
        'code',
        'title',
        'text',
        'is_active',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** When {@see $scope_type} is `source`, the related source row. */
    public function sourceForScope(): BelongsTo
    {
        return $this->belongsTo(SourceModel::class, 'scope_id');
    }
}
