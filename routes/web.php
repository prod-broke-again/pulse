<?php

use App\Http\Controllers\Auth\SocialAuthController;
use App\Http\Controllers\WidgetApiController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('chat', function () {
    return Inertia::render('Chat', [
        'initialChatId' => request()->query('chat') ? (int) request()->query('chat') : null,
    ]);
})->middleware(['auth', 'verified'])->name('chat');

Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('auth.social.redirect');
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('auth.social.callback');

Route::post('/webhook/vk/{sourceId}', [\App\Http\Controllers\WebhookController::class, 'vk'])->name('webhook.vk');
Route::post('/webhook/telegram/{sourceId}', [\App\Http\Controllers\WebhookController::class, 'telegram'])->name('webhook.telegram');
Route::post('/webhook/max/{sourceId}', [\App\Http\Controllers\WebhookController::class, 'max'])->name('webhook.max');

Route::prefix('/api/widget')->group(function (): void {
    Route::get('/config', [WidgetApiController::class, 'config'])->name('api.widget.config');
    Route::get('/config-ui', [WidgetApiController::class, 'configUi'])->name('api.widget.config-ui');
    Route::post('/session', [WidgetApiController::class, 'session'])->name('api.widget.session');
    Route::get('/messages', [WidgetApiController::class, 'messages'])->name('api.widget.messages');
    Route::post('/messages', [WidgetApiController::class, 'send'])->name('api.widget.send');
    Route::post('/messages/read', [WidgetApiController::class, 'markRead'])->name('api.widget.messages.read');
    Route::post('/typing', [WidgetApiController::class, 'typing'])->name('api.widget.typing');
});

/*
| Avatar is stored and edited on ACHPP ID only. Misconfigured clients sometimes POST
| to Pulse (404 → NOT_FOUND). Respond with a clear pointer to the IdP profile page.
*/
Route::match(['post', 'patch'], 'settings/avatars/{userAvatar?}', function () {
    $idp = rtrim((string) config('pulse.id.id_url_public', ''), '/');
    $profileUrl = $idp !== '' ? $idp.'/settings/profile' : null;

    return response()->json([
        'message' => $profileUrl !== null
            ? 'Фото профиля меняется в ACHPP ID, а не в Pulse. Откройте: '.$profileUrl
            : 'Фото профиля меняется в ACHPP ID (настройте ACHPP_ID_BASE_URL).',
        'code' => 'PROFILE_AVATAR_USE_IDP',
        'idp_profile_url' => $profileUrl,
    ], 422);
})->where('userAvatar', '[^/]*');

require __DIR__.'/settings.php';
