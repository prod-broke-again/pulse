<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GPTunnel RAG / database file API (list, upload, delete).
 * Retrieval is model-specific; this service is the integration seam for a KB pipeline.
 */
final class GptunnelRagService
{
    private const BASE = 'https://gptunnel.ru/v1';

    public function listDatabases(): array
    {
        return $this->getJson('database/list');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listFiles(string $databaseId): array
    {
        $r = $this->getJson('database/file/list', [
            'databaseId' => $databaseId,
        ]);

        return is_array($r) ? $r : [];
    }

    public function addFile(string $databaseId, string $absolutePath, ?string $name = null): ?array
    {
        if (! is_file($absolutePath)) {
            return null;
        }
        $contents = file_get_contents($absolutePath);
        if ($contents === false) {
            return null;
        }
        $r = $this->client()
            ->asMultipart()
            ->attach('file', $contents, $name ?? basename($absolutePath))
            ->post(self::BASE.'/database/file/add', [
                'databaseId' => $databaseId,
            ]);
        if (! $r->successful()) {
            Log::warning('GptunnelRag: upload failed', ['body' => $r->body()]);

            return null;
        }
        $j = $r->json();

        return is_array($j) ? $j : null;
    }

    public function deleteFile(string $databaseId, string $fileId): bool
    {
        $r = $this->client()->post(self::BASE.'/database/file/delete', [
            'databaseId' => $databaseId,
            'fileId' => $fileId,
        ]);
        if (! $r->successful()) {
            Log::warning('GptunnelRag: delete file failed', ['body' => $r->body()]);

            return false;
        }

        return true;
    }

    private function getJson(string $path, array $query = []): array
    {
        $r = $this->client()->get(self::BASE.'/'.$path, $query);
        if (! $r->successful()) {
            Log::warning('GptunnelRag: GET failed', [
                'path' => $path,
                'status' => $r->status(),
                'body' => $r->body(),
            ]);

            return [];
        }
        $j = $r->json();
        if (! is_array($j)) {
            return [];
        }

        return $j;
    }

    private function client()
    {
        $key = (string) config('services.ai.gptunnel.api_key', '');
        if ($key === '') {
            return Http::withHeaders([])->timeout(5);
        }

        return Http::withHeaders([
            'Authorization' => $key,
        ])->timeout(120);
    }
}
