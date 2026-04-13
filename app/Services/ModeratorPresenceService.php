<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ModeratorPresence;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Presence: online = manual switch on + heartbeat within {@see self::HEARTBEAT_TTL_SECONDS}.
 * Away = online but no activity within {@see self::AWAY_AFTER_MINUTES} (informational only).
 */
final class ModeratorPresenceService
{
    public const HEARTBEAT_TTL_SECONDS = 90;

    public const AWAY_AFTER_MINUTES = 5;

    /**
     * Moderators attached to the source (same pool as push routing) + all admins.
     *
     * @return Collection<int, int>
     */
    public function moderatorUserIdsForSource(int $sourceId): Collection
    {
        $admins = User::role('admin')->pluck('id');
        $moderatorsBySource = User::role('moderator')
            ->whereHas('sources', fn ($q) => $q->where('source_id', $sourceId))
            ->pluck('id');

        return $admins->merge($moderatorsBySource)->unique()->values();
    }

    public function anyModeratorOnlineForSource(int $sourceId): bool
    {
        $ids = $this->moderatorUserIdsForSource($sourceId);
        if ($ids->isEmpty()) {
            return false;
        }

        $threshold = now()->subSeconds(self::HEARTBEAT_TTL_SECONDS);

        return ModeratorPresence::query()
            ->whereIn('user_id', $ids->all())
            ->where('manual_online', true)
            ->whereNotNull('last_heartbeat_at')
            ->where('last_heartbeat_at', '>=', $threshold)
            ->exists();
    }

    /**
     * @return array{manual_online: bool, last_heartbeat_at: string|null, last_activity_at: string|null, is_online: bool, is_away: bool}
     */
    public function stateForUser(User $user): array
    {
        $presence = ModeratorPresence::query()->where('user_id', $user->id)->first();

        $manual = $presence?->manual_online ?? false;
        $heartbeat = $presence?->last_heartbeat_at;
        $activity = $presence?->last_activity_at;

        $isOnline = $manual
            && $heartbeat !== null
            && $heartbeat->greaterThanOrEqualTo(now()->subSeconds(self::HEARTBEAT_TTL_SECONDS));

        $isAway = $isOnline
            && (
                $activity === null
                || $activity->lessThan(now()->subMinutes(self::AWAY_AFTER_MINUTES))
            );

        return [
            'manual_online' => $manual,
            'last_heartbeat_at' => $heartbeat?->toIso8601String(),
            'last_activity_at' => $activity?->toIso8601String(),
            'is_online' => $isOnline,
            'is_away' => $isAway,
        ];
    }

    public function setManualOnline(User $user, bool $online): ModeratorPresence
    {
        $presence = ModeratorPresence::query()->firstOrNew(['user_id' => $user->id]);
        $presence->manual_online = $online;
        $now = now();
        if ($online) {
            $presence->last_heartbeat_at = $now;
            $presence->last_activity_at = $now;
        }
        $presence->save();

        return $presence;
    }

    public function recordHeartbeat(User $user): ModeratorPresence
    {
        $presence = ModeratorPresence::query()->firstOrNew(['user_id' => $user->id]);
        $presence->last_heartbeat_at = now();
        $presence->save();

        return $presence;
    }

    public function recordActivity(User $user): ModeratorPresence
    {
        $presence = ModeratorPresence::query()->firstOrNew(['user_id' => $user->id]);
        $presence->last_activity_at = now();
        $presence->save();

        return $presence;
    }
}
