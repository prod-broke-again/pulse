<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\Communication\ValueObject\ChatStatus;
use App\Infrastructure\Persistence\Eloquent\ChatModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Closes chats that had no message activity in N hours, only in "idle" support states
 * (not actively handled by a moderator: unassigned, AI waiting feedback).
 */
final class CloseIdleChatsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 120;

    public function handle(): void
    {
        if (! (bool) config('features.ai_idle_close_resolved_state', true)) {
            return;
        }
        $hours = (int) config('features.ai_idle_close_hours', 24);
        if ($hours < 1) {
            return;
        }
        $cut = now()->subHours($hours);

        $query = ChatModel::query()
            ->whereIn('status', [ChatStatus::New->value, ChatStatus::Active->value])
            ->where('awaiting_client_feedback', true)
            ->where('assigned_to', null)
            ->whereNotNull('last_activity_at')
            ->where('last_activity_at', '<', $cut);

        $ids = $query->pluck('id')->all();
        if ($ids === []) {
            return;
        }
        $updated = ChatModel::query()
            ->whereIn('id', $ids)
            ->update(['status' => ChatStatus::Closed->value, 'awaiting_client_feedback' => false]);

        Log::info('CloseIdleChatsJob closed idle chats', ['count' => $updated, 'ids' => $ids]);
    }
}
