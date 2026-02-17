<?php

declare(strict_types=1);

namespace App\Models;

use App\Infrastructure\Persistence\Eloquent\SourceModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $source_id
 * @property string $code
 * @property string $title
 * @property string $text
 * @property bool $is_active
 */
class CannedResponse extends Model
{
    protected $table = 'canned_responses';

    protected $fillable = [
        'source_id',
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

    public function source(): BelongsTo
    {
        return $this->belongsTo(SourceModel::class, 'source_id');
    }
}
