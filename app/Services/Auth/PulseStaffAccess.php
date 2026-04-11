<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class PulseStaffAccess
{
    /**
     * Staff API (moderator tools) is limited to admin|moderator roles.
     *
     * @throws AuthorizationException
     */
    public function ensureStaff(User $user): void
    {
        if (! $user->hasAnyRole(['admin', 'moderator'])) {
            throw new AuthorizationException(
                __('Access denied. Only administrators and moderators can use Pulse.')
            );
        }
    }
}
