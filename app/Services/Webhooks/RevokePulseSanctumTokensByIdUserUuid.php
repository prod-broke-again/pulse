<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\Models\User;

final class RevokePulseSanctumTokensByIdUserUuid
{
    public function handle(string $idUserUuid): void
    {
        $user = User::query()->where('id_user_uuid', $idUserUuid)->first();
        if ($user === null) {
            return;
        }

        $user->tokens()->delete();
    }
}
