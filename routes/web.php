<?php

use App\Http\Controllers\Auth\SocialAuthController;
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

Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])->name('auth.social.redirect');
Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])->name('auth.social.callback');

Route::post('/webhook/vk/{sourceId}', [\App\Http\Controllers\WebhookController::class, 'vk'])->name('webhook.vk');
Route::post('/webhook/telegram/{sourceId}', [\App\Http\Controllers\WebhookController::class, 'telegram'])->name('webhook.telegram');

require __DIR__.'/settings.php';
