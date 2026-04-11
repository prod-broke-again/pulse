<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use Illuminate\Http\Request;

final class TokenIssueService
{
    public function issueSanctumToken(User $user, ?string $deviceName, Request $request): string
    {
        $name = $deviceName ?? ($request->userAgent() ?: 'unknown');

        return $user->createToken($name)->plainTextToken;
    }
}
