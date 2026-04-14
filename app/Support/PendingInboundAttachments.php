<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Metadata for attachment rows not yet downloaded into storage (see DownloadInboundAttachmentJob).
 *
 * @phpstan-type PendingItem array{type: string, source_url: string, kind: string}
 */
final class PendingInboundAttachments
{
    /**
     * @param  list<array{url: string, file_name: string, mime_type: string, kind?: string|null}>  $descriptors
     * @return list<PendingItem>
     */
    public static function fromDownloadDescriptors(array $descriptors): array
    {
        $out = [];
        foreach ($descriptors as $row) {
            $url = isset($row['url']) && is_string($row['url']) ? trim($row['url']) : '';
            if ($url === '') {
                continue;
            }
            $kind = isset($row['kind']) && is_string($row['kind']) ? $row['kind'] : '';
            $mime = isset($row['mime_type']) && is_string($row['mime_type']) ? $row['mime_type'] : '';

            $out[] = [
                'type' => self::resolveType($kind, $mime),
                'source_url' => $url,
                'kind' => $kind,
            ];
        }

        return $out;
    }

    private static function resolveType(string $kind, string $mime): string
    {
        $k = mb_strtolower($kind);

        if (in_array($k, ['photo', 'sticker'], true)) {
            return 'image';
        }
        if ($k === 'video' || $k === 'video_note') {
            return 'video';
        }
        if (in_array($k, ['audio', 'voice'], true)) {
            return 'audio';
        }

        if ($mime !== '' && str_starts_with($mime, 'image/')) {
            return 'image';
        }
        if ($mime !== '' && str_starts_with($mime, 'video/')) {
            return 'video';
        }
        if ($mime !== '' && str_starts_with($mime, 'audio/')) {
            return 'audio';
        }

        return 'file';
    }

    /**
     * Removes the first pending entry matching the completed download (same key as duplicate check in DownloadInboundAttachmentJob).
     *
     * @param  list<array<string, mixed>>  $pending
     * @return list<array<string, mixed>>
     */
    public static function removePendingForCompletedDownload(array $pending, string $fileUrl, ?string $kind): array
    {
        $targetUrl = trim($fileUrl);
        $targetKind = trim((string) ($kind ?? ''));
        $out = [];
        $removed = false;
        foreach ($pending as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (! $removed) {
                $u = isset($item['source_url']) ? trim((string) $item['source_url']) : '';
                $k = isset($item['kind']) ? trim((string) $item['kind']) : '';
                if ($u !== '' && $u === $targetUrl && $k === $targetKind) {
                    $removed = true;

                    continue;
                }
            }
            $out[] = $item;
        }

        return array_values($out);
    }
}
