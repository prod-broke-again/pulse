<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\Models\IdWebhookIdempotencyKey;
use Illuminate\Database\UniqueConstraintViolationException;

final class IdWebhookIdempotencyService
{
    /**
     * @return bool true if this is a duplicate (already processed)
     */
    public function isDuplicate(string $key, string $topic): bool
    {
        if ($key === '') {
            return false;
        }

        try {
            IdWebhookIdempotencyKey::query()->create([
                'key' => $key,
                'topic' => $topic,
            ]);

            return false;
        } catch (UniqueConstraintViolationException) {
            return true;
        }
    }
}
