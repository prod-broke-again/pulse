<?php

declare(strict_types=1);

namespace App\Application\Communication\Action;

use App\Domains\Integration\Entity\Source;
use App\Domains\Integration\Repository\SourceRepositoryInterface;
use Illuminate\Support\Facades\Log;

final readonly class HandleBusinessConnectionEvent
{
    public function __construct(
        private SourceRepositoryInterface $sourceRepository,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function run(int $sourceId, array $payload): void
    {
        $bc = $payload['business_connection'] ?? null;
        if (! is_array($bc)) {
            return;
        }

        $rawId = $bc['id'] ?? null;
        if ($rawId === null || ! is_scalar($rawId)) {
            return;
        }
        $connectionId = trim((string) $rawId);
        if ($connectionId === '') {
            return;
        }

        $isEnabled = (bool) ($bc['is_enabled'] ?? false);

        $source = $this->sourceRepository->findById($sourceId);
        if ($source === null) {
            return;
        }

        $settings = $source->settings;
        $currentId = isset($settings['business_connection_id']) && is_string($settings['business_connection_id'])
            ? $settings['business_connection_id']
            : null;

        if ($isEnabled) {
            $userId = null;
            if (isset($bc['user']) && is_array($bc['user']) && isset($bc['user']['id'])) {
                $uid = $bc['user']['id'];
                $userId = is_int($uid) || (is_string($uid) && ctype_digit($uid)) ? (string) $uid : null;
            }

            $settings['business_connection_id'] = $connectionId;
            if ($userId !== null) {
                $settings['business_connection_user_id'] = $userId;
            }
            $settings['business_connection_activated_at'] = now()->toIso8601String();

            $this->sourceRepository->persist(new Source(
                id: $source->id,
                name: $source->name,
                type: $source->type,
                identifier: $source->identifier,
                secretKey: $source->secretKey,
                settings: $settings,
            ));

            Log::info('Telegram business connection activated', [
                'source_id' => $sourceId,
                'connection_id_hash' => hash('sha256', $connectionId),
            ]);

            return;
        }

        if ($currentId !== null && $currentId === $connectionId) {
            unset($settings['business_connection_id'], $settings['business_connection_user_id'], $settings['business_connection_activated_at']);

            $this->sourceRepository->persist(new Source(
                id: $source->id,
                name: $source->name,
                type: $source->type,
                identifier: $source->identifier,
                secretKey: $source->secretKey,
                settings: $settings,
            ));

            Log::info('Telegram business connection deactivated', [
                'source_id' => $sourceId,
                'connection_id_hash' => hash('sha256', $connectionId),
            ]);
        }
    }
}
