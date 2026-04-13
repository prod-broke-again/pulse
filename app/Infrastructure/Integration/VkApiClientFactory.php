<?php

declare(strict_types=1);

namespace App\Infrastructure\Integration;

use App\Infrastructure\Integration\Client\VkApiClient;

/**
 * Centralizes construction of {@see VkApiClient} for dependency injection.
 */
final readonly class VkApiClientFactory
{
    public function make(string $accessToken): VkApiClient
    {
        return new VkApiClient($accessToken);
    }
}
