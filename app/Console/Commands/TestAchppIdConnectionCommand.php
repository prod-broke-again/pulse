<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Identity\IdHttpConfigurator;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Probes ACHPP ID from the same PHP/cURL context as {@see \App\Services\Identity\IdIdentityClient}.
 */
final class TestAchppIdConnectionCommand extends Command
{
    protected $signature = 'pulse:test-idp
                            {--token= : Optional IdP access token — then GET profile expects 2xx}';

    protected $description = 'Test TCP/TLS and IdP API reachability (GET /api/v1/user) using pulse.id.* config';

    public function handle(): int
    {
        $base = (string) config('pulse.id.id_url_internal', (string) config('pulse.id.id_url_public', ''));
        if ($base === '') {
            $this->error('ACHPP_ID_BASE_URL/ACHPP_ID_INTERNAL_URL is empty. Set it in .env.');

            return self::FAILURE;
        }

        $path = (string) config('pulse.id.profile_path', '/api/v1/user');
        $url = rtrim($base, '/').$path;

        $this->info('Config (pulse.id):');
        $this->line('  id_url_public: '.(string) config('pulse.id.id_url_public', ''));
        $this->line('  id_url_internal: '.$base);
        $this->line('  profile_path: '.$path);
        $this->line('  verify_ssl: '.(config('pulse.id.verify_ssl', true) ? 'true' : 'false'));
        $this->line('  force_ipv4: '.(config('pulse.id.force_ipv4', false) ? 'true' : 'false'));
        $this->line('  connect_timeout: '.config('pulse.id.connect_timeout_seconds', 10).'s');
        $this->line('  timeout: '.config('pulse.id.timeout_seconds', 10).'s');
        $this->line('  oauth_client_id: '.(config('pulse.id.oauth_client_id') !== '' ? '(set)' : '(empty)'));
        $this->line('  full URL: '.$url);
        $this->newLine();

        $token = $this->option('token');
        $token = is_string($token) ? trim($token) : '';
        if ($token === '') {
            $this->warn('No --token: requesting profile without Bearer (expect 401 JSON).');
        }

        try {
            $started = microtime(true);
            $pending = IdHttpConfigurator::apply(
                Http::baseUrl($base)->acceptJson(),
                $base,
            );
            if ($token !== '') {
                $pending = $pending->withToken($token);
            }
            $response = $pending->get($path);
            $ms = round((microtime(true) - $started) * 1000, 1);

            $this->info("Response in {$ms} ms");
            $this->line('  HTTP '.$response->status());
            $body = $response->body();
            if (strlen($body) > 800) {
                $this->line('  body (truncated): '.substr($body, 0, 800).'…');
            } else {
                $this->line('  body: '.$body);
            }

            if ($token === '') {
                if ($response->status() === 401) {
                    $this->info('OK: unauthenticated request returns 401 (IdP reachable).');

                    return self::SUCCESS;
                }
                $this->warn('Expected HTTP 401 without token; got '.$response->status().'. Check IdP / Accept: application/json.');

                return self::FAILURE;
            }

            if ($response->successful()) {
                $this->info('OK: profile request succeeded with --token.');

                return self::SUCCESS;
            }

            $this->error('Profile request failed with token (HTTP '.$response->status().').');

            return self::FAILURE;
        } catch (ConnectionException $e) {
            $this->error('Connection error: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
