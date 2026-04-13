<?php

declare(strict_types=1);

namespace App\Application\Communication\Webhook;

/**
 * Merges inbound user metadata with existing chat metadata or VK profile data.
 */
final class UserMetadataMerger
{
    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    public function merge(array $current, array $incoming): array
    {
        $merged = $current;

        foreach ($incoming as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
                if ($value === '') {
                    continue;
                }
            }

            if ($key === 'name' && is_string($value) && $this->isPlaceholderGuestName($value)) {
                continue;
            }

            $existing = $merged[$key] ?? null;
            $shouldReplaceName = $key === 'name'
                && is_string($value)
                && (! is_string($existing) || $existing === '' || $this->isPlaceholderGuestName($existing));

            if ($shouldReplaceName || $existing === null || $existing === '' || $existing === []) {
                $merged[$key] = $value;
            }
        }

        return $merged;
    }

    public function isPlaceholderGuestName(string $value): bool
    {
        return in_array(mb_strtolower(trim($value)), ['гость', 'guest', 'клиент', 'client'], true);
    }
}
