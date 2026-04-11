<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Domains\Identity\DTOs\IdUserProfileDto;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class UserUpsertFromIdService
{
    /**
     * Link or create local user from IdP profile. Primary key: id_user_uuid.
     * Email is used only as a migration fallback when no UUID match exists.
     */
    public function upsert(IdUserProfileDto $profile): User
    {
        $uuid = $profile->id;

        $user = User::query()->where('id_user_uuid', $uuid)->first();

        if ($user === null && $profile->email !== '') {
            $user = User::query()->where('email', $profile->email)->first();
        }

        if ($user === null) {
            return User::query()->create([
                'name' => $profile->name,
                'email' => $profile->email,
                'password' => Hash::make(Str::random(64)),
                'id_user_uuid' => $uuid,
                'id_email' => $profile->email,
                'avatar_url' => $profile->avatarUrl,
                'id_profile_synced_at' => now(),
            ]);
        }

        $user->forceFill([
            'id_user_uuid' => $uuid,
            'name' => $profile->name,
            'email' => $profile->email,
            'id_email' => $profile->email,
            'avatar_url' => $profile->avatarUrl,
            'id_profile_synced_at' => now(),
        ]);

        $user->save();

        return $user->fresh() ?? $user;
    }
}
