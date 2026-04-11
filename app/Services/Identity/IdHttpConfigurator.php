<?php

declare(strict_types=1);

namespace App\Services\Identity;

use Illuminate\Http\Client\PendingRequest;

/**
 * Shared cURL options for outbound ACHPP ID requests (same as {@see IdIdentityClient}).
 */
final class IdHttpConfigurator
{
    public static function apply(PendingRequest $pending, ?string $baseUrl = null): PendingRequest
    {
        $timeout = (int) config('pulse.id.timeout_seconds', 10);
        $connectTimeout = (int) config('pulse.id.connect_timeout_seconds', 10);

        $pending = $pending->connectTimeout($connectTimeout)->timeout($timeout);

        $isHttpInternal = is_string($baseUrl) && str_starts_with(strtolower($baseUrl), 'http://');
        if (! config('pulse.id.verify_ssl', true) || $isHttpInternal) {
            $pending = $pending->withoutVerifying();
        }

        $curl = [];

        if (config('pulse.id.force_ipv4', false) && defined('CURL_IPRESOLVE_V4')) {
            $curl[\CURLOPT_IPRESOLVE] = \CURL_IPRESOLVE_V4;
        }

        /** @var list<string> $resolve */
        $resolve = config('pulse.id.curl_resolve', []);
        if ($resolve !== []) {
            $curl[\CURLOPT_RESOLVE] = $resolve;
        }

        if ($curl !== []) {
            $pending = $pending->withOptions(['curl' => $curl]);
        }

        return $pending;
    }
}
