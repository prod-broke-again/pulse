<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Domains\Identity\DTOs\IdUserProfileDto;
use App\Models\SocialAccount;
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
            $user = User::query()->create([
                'name' => $profile->name,
                'email' => $profile->email,
                'password' => Hash::make(Str::random(64)),
                'id_user_uuid' => $uuid,
                'id_email' => $profile->email,
                'avatar_url' => $profile->avatarUrl,
                'id_profile_synced_at' => now(),
            ]);
            $this->syncSocialAccountsFromId($user, $profile);

            return $user->fresh() ?? $user;
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

        $this->syncSocialAccountsFromId($user, $profile);

        return $user->fresh() ?? $user;
    }

    /**
     * @param  list<array{provider: string, provider_user_id: string}>|null  $incoming
     */
    private function syncSocialAccountsFromId(User $user, IdUserProfileDto $profile): void
    {
        if ($profile->socialAccounts === null) {
            return;
        }

        $incomingRows = $profile->socialAccounts;
        $incomingKeys = collect($incomingRows)->mapWithKeys(
            fn (array $r): array => [$r['provider'].'|'.$r['provider_user_id'] => true],
        );

        foreach ($incomingRows as $row) {
            SocialAccount::query()->updateOrCreate(
                [
                    'provider' => $row['provider'],
                    'provider_user_id' => $row['provider_user_id'],
                ],
                [
                    'user_id' => $user->id,
                ],
            );
        }

        $user->load('socialAccounts');
        foreach ($user->socialAccounts as $existing) {
            $key = $existing->provider.'|'.$existing->provider_user_id;
            if (! isset($incomingKeys[$key])) {
                $existing->delete();
            }
        }
    }
}
