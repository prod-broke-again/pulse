<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Communication\Action\ProcessInboundWebhook;
use App\Application\Integration\ResolveMessengerProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

final class WebhookController extends Controller
{
    public function __construct(
        private ProcessInboundWebhook $processInboundWebhook,
        private ResolveMessengerProvider $resolveMessenger,
    ) {}

    public function vk(Request $request, int $sourceId): JsonResponse
    {
        $messenger = $this->resolveMessenger->run($sourceId);
        $payload = $request->all();

        try {
            $this->processInboundWebhook->run($sourceId, $messenger, $payload);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function telegram(Request $request, int $sourceId): JsonResponse
    {
        $messenger = $this->resolveMessenger->run($sourceId);
        $payload = $request->all();

        try {
            $this->processInboundWebhook->run($sourceId, $messenger, $payload);
            return response()->json(['ok' => true]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }
    }
}
