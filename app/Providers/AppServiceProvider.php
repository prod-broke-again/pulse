<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Telegram\TelegramExtendSocialite;
use SocialiteProviders\VKontakte\VKontakteExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureFilamentEcho();
        $this->registerSocialiteProviders();
        $this->registerFilamentSocialLoginHook();

        Gate::policy(
            \App\Infrastructure\Persistence\Eloquent\ChatModel::class,
            \App\Policies\ChatPolicy::class,
        );
    }

    private function configureFilamentEcho(): void
    {
        if (config('broadcasting.default') !== 'reverb') {
            return;
        }
        $host = config('broadcasting.connections.reverb.options.host');
        $port = (int) config('broadcasting.connections.reverb.options.port');
        $scheme = config('broadcasting.connections.reverb.options.scheme');
        $key = config('broadcasting.connections.reverb.key');
        Config::set('filament.broadcasting.echo', [
            'broadcaster' => 'pusher',
            'key' => $key,
            'cluster' => 'reverb',
            'wsHost' => $host,
            'wsPort' => $port,
            'wssPort' => $port,
            'wsPath' => '', // Pusher builds path as wsPath + "/app/" + key; empty => /app/app-key
            'authEndpoint' => '/broadcasting/auth',
            'disableStats' => true,
            'encrypted' => $scheme === 'https',
            'forceTLS' => $scheme === 'https',
        ]);
    }

    private function registerFilamentSocialLoginHook(): void
    {
        Filament::registerRenderHook('panels::auth.login.form.after', function (): string {
            return view('filament.auth.social-login-buttons')->render();
        });
    }

    private function registerSocialiteProviders(): void
    {
        Event::listen(SocialiteWasCalled::class, function (SocialiteWasCalled $event): void {
            (new VKontakteExtendSocialite)->handle($event);
            (new TelegramExtendSocialite)->handle($event);
        });

        Event::listen(
            \App\Events\NewChatMessage::class,
            \App\Listeners\SendPushOnNewMessage::class,
        );
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }
}
