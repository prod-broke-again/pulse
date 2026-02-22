<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CannedResponseController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\ChatMessageController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\PushSubscriptionController;
use App\Http\Controllers\Api\V1\UploadController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    // Auth (public)
    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');

    // Auth (protected)
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');

        // Chats
        Route::get('/chats', [ChatController::class, 'index'])->name('api.v1.chats.index');
        Route::post('/chats/{chat}/assign-me', [ChatController::class, 'assignMe'])->name('api.v1.chats.assign-me');
        Route::post('/chats/{chat}/close', [ChatController::class, 'close'])->name('api.v1.chats.close');
        Route::post('/chats/{chat}/typing', [ChatController::class, 'typing'])->name('api.v1.chats.typing');

        // Chat Messages
        Route::get('/chats/{chat}/messages', [ChatMessageController::class, 'index'])->name('api.v1.chats.messages.index');
        Route::post('/chats/{chat}/send', [ChatMessageController::class, 'send'])->name('api.v1.chats.messages.send');

        // Uploads
        Route::post('/uploads', [UploadController::class, 'store'])->name('api.v1.uploads.store');

        // Canned Responses
        Route::get('/canned-responses', [CannedResponseController::class, 'index'])->name('api.v1.canned-responses.index');

        // Device Tokens (FCM)
        Route::post('/devices/register-token', [DeviceController::class, 'register'])->name('api.v1.devices.register');
        Route::delete('/devices/{token}', [DeviceController::class, 'destroy'])->name('api.v1.devices.destroy');

        // Web Push subscriptions
        Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('api.v1.push-subscriptions.store');
    });
});
