<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class IdWebhookIdempotencyKey extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'key',
        'topic',
    ];
}
