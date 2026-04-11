<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Validates HMAC signature from ACHPP ID: hex(sha256_hmac(secret, timestamp + '.' + rawBody)).
 * Headers: X-Pulse-Timestamp, X-Pulse-Signature
 */
final class VerifyPulseIdWebhookSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('pulse.id_webhooks.enabled')) {
            abort(response()->json([
                'message' => 'ID webhooks are disabled.',
                'code' => 'WEBHOOKS_DISABLED',
            ], 503));
        }

        $secret = config('pulse.id_webhooks.secret');
        if ($secret === '') {
            abort(response()->json([
                'message' => 'Webhook secret not configured.',
                'code' => 'WEBHOOKS_MISCONFIGURED',
            ], 503));
        }

        $ts = $request->header('X-Pulse-Timestamp');
        $sig = $request->header('X-Pulse-Signature');
        if ($ts === null || $ts === '' || $sig === null || $sig === '') {
            abort(response()->json([
                'message' => 'Missing signature headers.',
                'code' => 'INVALID_SIGNATURE',
            ], 401));
        }

        $tolerance = (int) config('pulse.id_webhooks.replay_tolerance_seconds', 300);
        if (abs(time() - (int) $ts) > $tolerance) {
            abort(response()->json([
                'message' => 'Request timestamp outside allowed window.',
                'code' => 'REPLAY_OR_STALE',
            ], 401));
        }

        $payload = $request->getContent();
        $expected = hash_hmac('sha256', $ts.'.'.$payload, $secret);

        if (! hash_equals($expected, strtolower((string) $sig))) {
            abort(response()->json([
                'message' => 'Invalid signature.',
                'code' => 'INVALID_SIGNATURE',
            ], 401));
        }

        return $next($request);
    }
}
