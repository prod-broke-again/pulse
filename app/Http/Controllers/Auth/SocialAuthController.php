<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

final class SocialAuthController extends Controller
{
    public function redirect(string $provider)
    {
        $this->validateProvider($provider);

        return Socialite::driver($provider)->redirect();
    }

    public function callback(string $provider)
    {
        $this->validateProvider($provider);

        $socialUser = Socialite::driver($provider)->user();

        $account = SocialAccount::where('provider', $provider)
            ->where('provider_user_id', (string) $socialUser->getId())
            ->first();

        if ($account) {
            $user = $account->user;
        } else {
            $user = $this->findOrCreateUser($provider, $socialUser);
        }

        Auth::guard('web')->login($user, true);

        return redirect()->intended('/admin');
    }

    private function validateProvider(string $provider): void
    {
        if (! in_array($provider, ['vkontakte', 'telegram'], true)) {
            abort(404);
        }
    }

    private function findOrCreateUser(string $provider, \Laravel\Socialite\Contracts\User $socialUser): User
    {
        $email = $socialUser->getEmail() ?? 'social-' . $provider . '-' . $socialUser->getId() . '@placeholder.local';
        $user = User::where('email', $email)
            ->first();

        if (! $user) {
            $user = User::create([
                'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
                'email' => $email,
                'password' => bcrypt(str()->random(32)),
            ]);
        }

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => $provider,
            'provider_user_id' => (string) $socialUser->getId(),
            'token' => $socialUser->token ?? null,
            'refresh_token' => $socialUser->refreshToken ?? null,
        ]);

        return $user;
    }
}
