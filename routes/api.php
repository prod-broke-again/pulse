<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AnalyticsController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CannedResponseController;
use App\Http\Controllers\Api\V1\ChatAiController;
use App\Http\Controllers\Api\V1\ChatController;
use App\Http\Controllers\Api\V1\ChatMessageController;
use App\Http\Controllers\Api\V1\DepartmentController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\ModeratorPresenceController;
use App\Http\Controllers\Api\V1\NotificationSoundPreferencesController;
use App\Http\Controllers\Api\V1\PushSubscriptionController;
use App\Http\Controllers\Api\V1\QuickLinkController;
use App\Http\Controllers\Api\V1\SsoExchangeController;
use App\Http\Controllers\Api\V1\UploadController;
use App\Http\Controllers\Api\Webhooks\IdUserRevokedWebhookController;
use App\Http\Controllers\Api\Webhooks\IdUserUpdatedWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('webhooks/id')->middleware(['pulse.id.webhook'])->group(function (): void {
    Route::post('/user-revoked', IdUserRevokedWebhookController::class)->name('api.webhooks.id.user-revoked');
    Route::post('/user-updated', IdUserUpdatedWebhookController::class)->name('api.webhooks.id.user-updated');
});

Route::prefix('v1')->group(function (): void {
    // Auth (public)
    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.v1.auth.login');
    Route::post('/auth/sso/exchange', [SsoExchangeController::class, 'exchange'])->name('api.v1.auth.sso.exchange');

    // Auth (protected)
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.v1.auth.logout');
        Route::get('/auth/me', [AuthController::class, 'me'])->name('api.v1.auth.me');
        Route::post('/auth/profile/avatar', [AuthController::class, 'uploadAvatar'])->name('api.v1.auth.profile.avatar');

        // Chats
        Route::get('/chats', [ChatController::class, 'index'])->name('api.v1.chats.index');
        Route::get('/chats/tab-counts', [ChatController::class, 'tabCounts'])->name('api.v1.chats.tab-counts');
        Route::get('/chats/{chat}', [ChatController::class, 'show'])->name('api.v1.chats.show');
        Route::post('/chats/{chat}/assign-me', [ChatController::class, 'assignMe'])->name('api.v1.chats.assign-me');
        Route::patch('/chats/{chat}/department', [ChatController::class, 'changeDepartment'])->name('api.v1.chats.department');
        Route::post('/chats/{chat}/close', [ChatController::class, 'close'])->name('api.v1.chats.close');
        Route::post('/chats/{chat}/typing', [ChatController::class, 'typing'])->name('api.v1.chats.typing');
        Route::post('/chats/{chat}/sync-history', [ChatController::class, 'syncHistory'])->name('api.v1.chats.sync-history');
        Route::post('/chats/{chat}/mute', [ChatController::class, 'mute'])->name('api.v1.chats.mute');
        Route::get('/chats/{chat}/ai/summary', [ChatAiController::class, 'summary'])->name('api.v1.chats.ai.summary');
        Route::get('/chats/{chat}/ai/suggestions', [ChatAiController::class, 'suggestions'])->name('api.v1.chats.ai.suggestions');

        // Chat Messages
        Route::get('/chats/{chat}/messages', [ChatMessageController::class, 'index'])->name('api.v1.chats.messages.index');
        Route::post('/chats/{chat}/send', [ChatMessageController::class, 'send'])->name('api.v1.chats.messages.send');
        Route::post('/chats/{chat}/read', [ChatMessageController::class, 'readChat'])
            ->middleware('throttle:api-chat-read')
            ->name('api.v1.chats.read');
        Route::get('/messages/{message}/context', [ChatMessageController::class, 'messageContext'])
            ->name('api.v1.messages.context');

        // Uploads
        Route::post('/uploads', [UploadController::class, 'store'])->name('api.v1.uploads.store');

        // Departments (для смены отдела в чате)
        Route::get('/departments', [DepartmentController::class, 'index'])->name('api.v1.departments.index');

        // Analytics
        Route::get('/analytics/overview', [AnalyticsController::class, 'overview'])->name('api.v1.analytics.overview');

        // Canned Responses
        Route::get('/canned-responses', [CannedResponseController::class, 'index'])->name('api.v1.canned-responses.index');
        Route::post('/canned-responses', [CannedResponseController::class, 'store'])->name('api.v1.canned-responses.store');
        Route::patch('/canned-responses/{cannedResponse}', [CannedResponseController::class, 'update'])->name('api.v1.canned-responses.update');
        Route::delete('/canned-responses/{cannedResponse}', [CannedResponseController::class, 'destroy'])->name('api.v1.canned-responses.destroy');

        // Quick links (reply_markup presets)
        Route::get('/quick-links', [QuickLinkController::class, 'index'])->name('api.v1.quick-links.index');
        Route::post('/quick-links', [QuickLinkController::class, 'store'])->name('api.v1.quick-links.store');
        Route::post('/quick-links/reorder', [QuickLinkController::class, 'reorder'])->name('api.v1.quick-links.reorder');
        Route::patch('/quick-links/{quickLink}', [QuickLinkController::class, 'update'])->name('api.v1.quick-links.update');
        Route::delete('/quick-links/{quickLink}', [QuickLinkController::class, 'destroy'])->name('api.v1.quick-links.destroy');

        // Device Tokens (FCM)
        Route::post('/devices/register-token', [DeviceController::class, 'register'])->name('api.v1.devices.register');
        Route::delete('/devices/{token}', [DeviceController::class, 'destroy'])->name('api.v1.devices.destroy');

        // Web Push subscriptions
        Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('api.v1.push-subscriptions.store');

        // Notification sound preferences (moderator staff, synced)
        Route::get('/user/notification-sound-preferences', [NotificationSoundPreferencesController::class, 'show'])
            ->name('api.v1.user.notification-sound-preferences.show');
        Route::patch('/user/notification-sound-preferences', [NotificationSoundPreferencesController::class, 'update'])
            ->name('api.v1.user.notification-sound-preferences.update');

        // Moderator presence (web / desktop / mobile)
        Route::prefix('moderator/presence')->group(function (): void {
            Route::get('/me', [ModeratorPresenceController::class, 'me'])->name('api.v1.moderator.presence.me');
            Route::post('/toggle', [ModeratorPresenceController::class, 'toggle'])->name('api.v1.moderator.presence.toggle');
            Route::post('/heartbeat', [ModeratorPresenceController::class, 'heartbeat'])->name('api.v1.moderator.presence.heartbeat');
            Route::post('/activity', [ModeratorPresenceController::class, 'activity'])->name('api.v1.moderator.presence.activity');
        });
    });
});
