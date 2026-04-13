<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;

final class ModeratorSourceAccess
{
    public static function assertCanManageGlobal(User $user): void
    {
        if (! $user->isAdmin()) {
            abort(403, 'Only administrators can manage global items.');
        }
    }

    public static function assertCanManageSource(User $user, ?int $sourceId): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($sourceId === null) {
            abort(403, 'Only administrators can manage global items.');
        }

        $ids = $user->sources()->pluck('id')->all();
        if (! in_array($sourceId, $ids, true)) {
            abort(403);
        }
    }

    public static function assertCanAccessSourceForRead(User $user, ?int $sourceId): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($sourceId === null) {
            return;
        }

        $ids = $user->sources()->pluck('id')->all();
        if (! in_array($sourceId, $ids, true)) {
            abort(403);
        }
    }
}
