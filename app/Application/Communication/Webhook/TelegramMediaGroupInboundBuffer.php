<?php

declare(strict_types=1);

namespace App\Application\Communication\Webhook;

use App\Jobs\ProcessTelegramMediaGroupJob;
use Illuminate\Support\Facades\Cache;

/**
 * Buffers Telegram webhook fragments that share {@see media_group_id} until {@see ProcessTelegramMediaGroupJob} flushes them.
 */
final class TelegramMediaGroupInboundBuffer
{
    public static function bufferKey(int $sourceId, int $chatId, string $groupId): string
    {
        return 'tg_mg_in:'.$sourceId.':'.$chatId.':'.$groupId;
    }

    /** Serialize append (get/merge/put) with the buffer read/pull in {@see ProcessTelegramMediaGroupJob}. */
    public static function lockKey(int $sourceId, int $chatId, string $groupId): string
    {
        return 'tg_mg_in_lock:'.$sourceId.':'.$chatId.':'.$groupId;
    }

    /** Serialize flush across queue workers (quiet check + pull + persist for one group). */
    public static function flushLockKey(int $sourceId, int $chatId, string $groupId): string
    {
        return 'tg_mg_flush:'.$sourceId.':'.$chatId.':'.$groupId;
    }

    /**
     * @param  array{
     *     attachments: list<array{url: string, file_name: string, mime_type: string, kind?: string|null}>,
     *     raw_caption: string,
     *     reply_to_message_id: int|null,
     *     telegram_message_id: string|null,
     * }  $fragment
     */
    public function appendAndSchedule(
        int $sourceId,
        int $chatId,
        string $groupId,
        array $fragment,
    ): void {
        $scheduleMs = (int) config('pulse.telegram_media_group_schedule_ms', 700);
        if ($scheduleMs < 50) {
            $scheduleMs = 50;
        }

        $bufKey = self::bufferKey($sourceId, $chatId, $groupId);
        $lockKey = self::lockKey($sourceId, $chatId, $groupId);

        Cache::lock($lockKey, 15)->block(5, function () use ($bufKey, $fragment): void {
            /** @var array{fragments: list<array<string, mixed>>, updated_at: float} $buf */
            $buf = Cache::get($bufKey, ['fragments' => [], 'updated_at' => 0.0]);
            if (! isset($buf['fragments']) || ! is_array($buf['fragments'])) {
                $buf['fragments'] = [];
            }
            $buf['fragments'][] = $fragment;
            $buf['updated_at'] = microtime(true);
            Cache::put($bufKey, $buf, 300);
        });

        ProcessTelegramMediaGroupJob::dispatch($sourceId, $chatId, $groupId)
            ->delay(now()->addMilliseconds($scheduleMs));
    }
}
