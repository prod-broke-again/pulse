<?php

declare(strict_types=1);

namespace App\Support;

final class ResolvesModeratorItemScope
{
    /**
     * Legacy `source_id` maps to source scope. Explicit `scope_type` + `scope_id` wins.
     *
     * @return array{0: string|null, 1: int|null}
     */
    public static function fromRequestArray(array $data): array
    {
        if (array_key_exists('scope_type', $data) || array_key_exists('scope_id', $data)) {
            $st = $data['scope_type'] ?? null;
            $sid = $data['scope_id'] ?? null;
            if ($st === null || $st === '') {
                return [null, null];
            }

            return [(string) $st, $sid !== null ? (int) $sid : null];
        }

        if (array_key_exists('source_id', $data)) {
            $legacy = $data['source_id'] ?? null;
            if ($legacy === null) {
                return [null, null];
            }

            return ['source', (int) $legacy];
        }

        return [null, null];
    }
}
