<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Communication\Action\CreateMessage;
use App\Application\Communication\Webhook\InboundAttachmentExtractor;
use App\Application\Communication\Webhook\TelegramMediaGroupInboundBuffer;
use App\Domains\Communication\ValueObject\SenderType;
use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Services\MaybeSendOfflineAutoReply;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

final class ProcessTelegramMediaGroupJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 30;

    public int $timeout = 120;

    public function __construct(
        public int $sourceId,
        public int $chatId,
        public string $groupId,
    ) {}

    public function handle(
        CreateMessage $createMessage,
        InboundAttachmentExtractor $inboundAttachmentExtractor,
        MaybeSendOfflineAutoReply $maybeSendOfflineAutoReply,
    ): void {
        $bufKey = TelegramMediaGroupInboundBuffer::bufferKey($this->sourceId, $this->chatId, $this->groupId);
        $lockKey = TelegramMediaGroupInboundBuffer::lockKey($this->sourceId, $this->chatId, $this->groupId);
        $quietMs = (int) config('pulse.telegram_media_group_quiet_ms', 450);
        if ($quietMs < 0) {
            $quietMs = 0;
        }

        $lock = Cache::lock($lockKey, 30);
        if (! $lock->get()) {
            $this->release(0.5);

            return;
        }

        try {
            /** @var array{fragments?: list<array<string, mixed>>, updated_at?: float}|null $buf */
            $buf = Cache::get($bufKey);
            if ($buf === null || ! isset($buf['fragments']) || $buf['fragments'] === []) {
                return;
            }

            $updatedAt = (float) ($buf['updated_at'] ?? 0.0);
            $ageMs = (int) ((microtime(true) - $updatedAt) * 1000);
            if ($ageMs < $quietMs) {
                $wait = max(0.15, ($quietMs - $ageMs) / 1000);
                $this->release($wait);

                return;
            }

            $buf = Cache::pull($bufKey);
            if ($buf === null || ! isset($buf['fragments']) || $buf['fragments'] === []) {
                return;
            }

            $fragments = $buf['fragments'];
            $mergedAttachments = $this->mergeAttachments($fragments);
            if ($mergedAttachments === []) {
                return;
            }
            $rawCaption = '';
            foreach ($fragments as $fragment) {
                $t = trim((string) ($fragment['raw_caption'] ?? ''));
                if ($t !== '') {
                    $rawCaption = $t;

                    break;
                }
            }

            $text = $rawCaption !== ''
                ? $rawCaption
                : $inboundAttachmentExtractor->buildAttachmentPlaceholderText($mergedAttachments);

            $replyToMessageId = null;
            foreach ($fragments as $fragment) {
                $rid = $fragment['reply_to_message_id'] ?? null;
                if (is_int($rid) && $rid > 0) {
                    $replyToMessageId = $rid;

                    break;
                }
            }

            $telegramMessageIds = [];
            foreach ($fragments as $fragment) {
                $mid = $fragment['telegram_message_id'] ?? null;
                if (is_string($mid) && $mid !== '') {
                    $telegramMessageIds[] = $mid;
                }
            }
            $telegramMessageIds = array_values(array_unique($telegramMessageIds));

            $externalMessageId = 'mg:'.$this->groupId;
            $payload = [
                'telegram_media_group_id' => $this->groupId,
                'telegram_message_ids' => $telegramMessageIds,
                'media_group_fragment_count' => count($fragments),
            ];

            $message = $createMessage->run(
                chatId: $this->chatId,
                text: $text,
                senderType: SenderType::Client,
                senderId: null,
                payload: $payload,
                externalMessageId: $externalMessageId,
                replyToMessageId: $replyToMessageId,
            );

            foreach ($mergedAttachments as $attachment) {
                DownloadInboundAttachmentJob::dispatch(
                    $message->id,
                    $attachment['url'],
                    $attachment['file_name'],
                    $attachment['mime_type'],
                    $attachment['kind'] ?? null,
                );
            }

            $chatModel = ChatModel::query()->find($this->chatId);
            if ($chatModel !== null) {
                $maybeSendOfflineAutoReply->run($chatModel);
            }
        } catch (\Throwable $e) {
            Log::error('ProcessTelegramMediaGroupJob failed', [
                'source_id' => $this->sourceId,
                'chat_id' => $this->chatId,
                'group_id' => $this->groupId,
                'exception' => $e,
            ]);
            throw $e;
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  list<array<string, mixed>>  $fragments
     * @return list<array{url: string, file_name: string, mime_type: string, kind?: string|null}>
     */
    private function mergeAttachments(array $fragments): array
    {
        $merged = [];
        $seen = [];

        foreach ($fragments as $fragment) {
            $atts = $fragment['attachments'] ?? null;
            if (! is_array($atts)) {
                continue;
            }
            foreach ($atts as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $url = isset($row['url']) && is_string($row['url']) ? trim($row['url']) : '';
                if ($url === '') {
                    continue;
                }
                $key = mb_strtolower($url.'|'.(string) ($row['kind'] ?? '').'|'.(string) ($row['file_name'] ?? ''));
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $merged[] = [
                    'url' => $url,
                    'file_name' => is_string($row['file_name'] ?? null) ? $row['file_name'] : 'file',
                    'mime_type' => is_string($row['mime_type'] ?? null) ? $row['mime_type'] : 'application/octet-stream',
                    'kind' => isset($row['kind']) && is_string($row['kind']) ? $row['kind'] : null,
                ];
            }
        }

        return $merged;
    }
}
