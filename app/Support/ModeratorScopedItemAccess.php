<?php

declare(strict_types=1);

namespace App\Support;

use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Models\User;

final class ModeratorScopedItemAccess
{
    public static function assertCanManageScope(User $user, ?string $scopeType, ?int $scopeId): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($scopeType === null && $scopeId === null) {
            abort(403, 'Only administrators can manage global items.');
        }

        if ($scopeType === 'source') {
            if ($scopeId === null) {
                abort(403);
            }
            ModeratorSourceAccess::assertCanManageSource($user, $scopeId);

            return;
        }

        if ($scopeType === 'department') {
            if ($scopeId === null) {
                abort(403);
            }
            $dept = DepartmentModel::query()->find($scopeId);
            if ($dept === null) {
                abort(422, 'Invalid department.');
            }
            ModeratorSourceAccess::assertCanManageSource($user, (int) $dept->source_id);
            if (! $user->departments()->whereKey($scopeId)->exists()) {
                abort(403);
            }

            return;
        }

        abort(403);
    }

    /**
     * @param  array{scope_type?: string|null, scope_id?: int|null}  $scope
     */
    public static function assertCanManageExistingRow(User $user, ?int $ownerUserId, array $scope): void
    {
        if ($user->isAdmin()) {
            return;
        }

        if ($ownerUserId !== null && $ownerUserId === $user->id) {
            return;
        }

        if ($ownerUserId === null) {
            self::assertCanManageScope($user, $scope['scope_type'] ?? null, $scope['scope_id'] ?? null);

            return;
        }

        abort(403);
    }

    /**
     * @param  array{scope_type?: string|null, scope_id?: int|null}  $scope
     */
    public static function userCanReadScope(User $user, array $scope): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $scopeType = $scope['scope_type'] ?? null;
        $scopeId = $scope['scope_id'] ?? null;

        if ($scopeType === null && $scopeId === null) {
            return $user->sources()->exists();
        }

        if ($scopeType === 'source' && $scopeId !== null) {
            return $user->sources()->whereKey($scopeId)->exists();
        }

        if ($scopeType === 'department' && $scopeId !== null) {
            return $user->departments()->whereKey($scopeId)->exists();
        }

        return false;
    }
}
