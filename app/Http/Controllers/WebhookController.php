<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Jobs\ProcessIncomingMessageJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

final class WebhookController extends Controller
{
    public function vk(Request $request, int $sourceId): JsonResponse|Response
    {
        $payload = $request->all();
        $source = SourceModel::find($sourceId);
        if ($source === null || $source->type !== 'vk') {
            return response()->json(['ok' => false, 'error' => 'Source not found'], 404);
        }
        if (! $this->validateVkSecret($source, $payload)) {
            return response()->json(['ok' => false, 'error' => 'Invalid signature'], 403);
        }

        if (($payload['type'] ?? null) === 'confirmation') {
            $code = $this->vkCallbackConfirmationCode($source);
            if ($code === null) {
                return $this->vkConfirmationPlainError(
                    'VK callback confirmation is not set (source settings vk_callback_confirmation or VK_CALLBACK_CONFIRMATION in .env)',
                    503,
                );
            }

            return response($code, 200)->header('Content-Type', 'text/plain; charset=UTF-8');
        }

        if (! $this->shouldDispatchVkIncomingMessage($payload)) {
            Log::debug('VK webhook event skipped', [
                'source_id' => $sourceId,
                'type' => (string) ($payload['type'] ?? ''),
            ]);

            return response()->json(['ok' => true]);
        }

        try {
            ProcessIncomingMessageJob::dispatch($sourceId, $payload);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::error('Webhook VK dispatch failed', ['source_id' => $sourceId, 'error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'error' => 'Server error'], 500);
        }
    }

    public function telegram(Request $request, int $sourceId): JsonResponse
    {
        $payload = $request->all();
        $source = SourceModel::find($sourceId);
        if ($source === null || $source->type !== 'tg') {
            return response()->json(['ok' => false, 'error' => 'Source not found'], 404);
        }
        if (! $this->validateTelegramSecret($source, $request)) {
            return response()->json(['ok' => false, 'error' => 'Invalid signature'], 403);
        }

        try {
            ProcessIncomingMessageJob::dispatch($sourceId, $payload);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::error('Webhook Telegram dispatch failed', ['source_id' => $sourceId, 'error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'error' => 'Server error'], 500);
        }
    }

    public function max(Request $request, int $sourceId): JsonResponse
    {
        $payload = $request->all();
        $source = SourceModel::find($sourceId);
        if ($source === null || $source->type !== 'max') {
            return response()->json(['ok' => false, 'error' => 'Source not found'], 404);
        }
        if (! $this->validateMaxSecret($source, $request)) {
            return response()->json(['ok' => false, 'error' => 'Invalid signature'], 403);
        }

        try {
            ProcessIncomingMessageJob::dispatch($sourceId, $payload);

            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            Log::error('Webhook MAX dispatch failed', ['source_id' => $sourceId, 'error' => $e->getMessage()]);

            return response()->json(['ok' => false, 'error' => 'Server error'], 500);
        }
    }

    private function vkConfirmationPlainError(string $message, int $status): Response
    {
        return response($message, $status)->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    private function vkCallbackConfirmationCode(SourceModel $source): ?string
    {
        $fromSettings = $source->settings['vk_callback_confirmation'] ?? null;
        if (is_string($fromSettings) && $fromSettings !== '') {
            return $fromSettings;
        }
        $fromEnv = (string) Config::get('pulse.vk.callback_confirmation', '');

        return $fromEnv !== '' ? $fromEnv : null;
    }

    /** @param array<string, mixed> $payload */
    private function validateVkSecret(SourceModel $source, array $payload): bool
    {
        $expected = (string) ($source->secret_key ?? '');
        if ($expected === '') {
            $expected = (string) Config::get('pulse.vk.callback_secret', '');
        }
        if ($expected === '') {
            return true;
        }

        return hash_equals($expected, (string) ($payload['secret'] ?? ''));
    }

    private function validateTelegramSecret(SourceModel $source, Request $request): bool
    {
        if (! $source->secret_key) {
            return true;
        }

        $headerSecret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        return hash_equals((string) $source->secret_key, $headerSecret);
    }

    private function validateMaxSecret(SourceModel $source, Request $request): bool
    {
        if (! $source->secret_key) {
            return true;
        }

        $headerSecret = (string) $request->header('X-Max-Bot-Secret', '');

        return hash_equals((string) $source->secret_key, $headerSecret);
    }

    /** @param array<string, mixed> $payload */
    private function shouldDispatchVkIncomingMessage(array $payload): bool
    {
        $type = (string) ($payload['type'] ?? '');

        return match ($type) {
            // Only true inbound messages should become chat messages.
            'message_new' => ! $this->isVkOutgoingEvent($payload),

            'message_event' => true,

            // Events we currently acknowledge but intentionally do not persist as inbound messages.
            'message_reply',
            'message_edit',
            'message_allow',
            'message_deny',
            'message_typing_state',
            'message_read',
            'message_reaction_event' => false,

            default => false,
        };
    }

    /** @param array<string, mixed> $payload */
    private function isVkOutgoingEvent(array $payload): bool
    {
        $out = $payload['object']['message']['out'] ?? $payload['object']['out'] ?? null;
        if ($out === 1 || $out === '1' || $out === true) {
            return true;
        }

        $fromId = $payload['object']['message']['from_id'] ?? $payload['object']['from_id'] ?? null;
        if (is_numeric($fromId) && (int) $fromId < 0) {
            // In VK negative from_id is usually a community sender (outgoing).
            return true;
        }

        return false;
    }
}
