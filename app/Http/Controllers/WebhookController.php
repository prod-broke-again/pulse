<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Jobs\ProcessIncomingMessageJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

final class WebhookController extends Controller
{
    public function vk(Request $request, int $sourceId): JsonResponse
    {
        $payload = $request->all();
        $source = SourceModel::find($sourceId);
        if ($source === null || $source->type !== 'vk') {
            return response()->json(['ok' => false, 'error' => 'Source not found'], 404);
        }
        if (! $this->validateVkSecret($source, $payload)) {
            return response()->json(['ok' => false, 'error' => 'Invalid signature'], 403);
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

    /** @param array<string, mixed> $payload */
    private function validateVkSecret(SourceModel $source, array $payload): bool
    {
        if (! $source->secret_key) {
            return true;
        }

        return hash_equals((string) $source->secret_key, (string) ($payload['secret'] ?? ''));
    }

    private function validateTelegramSecret(SourceModel $source, Request $request): bool
    {
        if (! $source->secret_key) {
            return true;
        }

        $headerSecret = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        return hash_equals((string) $source->secret_key, $headerSecret);
    }
}
