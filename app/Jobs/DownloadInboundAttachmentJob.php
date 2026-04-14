<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Events\ChatMessageUpdated;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Support\NewChatMessageBroadcastExtras;
use App\Support\PendingInboundAttachments;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class DownloadInboundAttachmentJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public int $messageId,
        public string $fileUrl,
        public string $fileName,
        public string $mimeType,
        public ?string $kind = null,
    ) {}

    public function handle(): void
    {
        try {
            $alreadyProcessed = false;

            DB::transaction(function () use (&$alreadyProcessed): void {
                $message = MessageModel::query()
                    ->whereKey($this->messageId)
                    ->lockForUpdate()
                    ->first();

                if ($message === null) {
                    Log::warning('DownloadInboundAttachmentJob: message not found', ['message_id' => $this->messageId]);
                    $alreadyProcessed = true;

                    return;
                }

                $payload = $message->payload ?? [];
                $existingAttachments = is_array($payload['attachments'] ?? null) ? $payload['attachments'] : [];
                $alreadyExists = collect($existingAttachments)->contains(function ($item): bool {
                    if (! is_array($item)) {
                        return false;
                    }

                    $existingSource = isset($item['source_url']) ? trim((string) $item['source_url']) : '';
                    $existingKind = isset($item['kind']) ? trim((string) $item['kind']) : '';

                    return $existingSource !== ''
                        && $existingSource === trim($this->fileUrl)
                        && $existingKind === trim((string) ($this->kind ?? ''));
                });

                if ($alreadyExists) {
                    $alreadyProcessed = true;
                }
            });

            if ($alreadyProcessed) {
                return;
            }

            $tempDir = 'temp/inbound';
            $localName = Str::uuid()->toString().'_'.$this->fileName;
            $tempPath = $tempDir.'/'.$localName;

            $response = Http::timeout(60)->get($this->fileUrl);
            if (! $response->successful()) {
                Log::error('DownloadInboundAttachmentJob: failed to download', [
                    'message_id' => $this->messageId,
                    'url' => $this->fileUrl,
                    'status' => $response->status(),
                ]);

                return;
            }

            Storage::disk('local')->put($tempPath, $response->body());
            $fullPath = Storage::disk('local')->path($tempPath);

            DB::transaction(function () use ($fullPath, $localName): void {
                $message = MessageModel::query()
                    ->whereKey($this->messageId)
                    ->lockForUpdate()
                    ->first();

                if ($message === null) {
                    return;
                }

                $payload = $message->payload ?? [];
                $existingAttachments = is_array($payload['attachments'] ?? null) ? $payload['attachments'] : [];
                $alreadyExists = collect($existingAttachments)->contains(function ($item): bool {
                    if (! is_array($item)) {
                        return false;
                    }

                    $existingSource = isset($item['source_url']) ? trim((string) $item['source_url']) : '';
                    $existingKind = isset($item['kind']) ? trim((string) $item['kind']) : '';

                    return $existingSource !== ''
                        && $existingSource === trim($this->fileUrl)
                        && $existingKind === trim((string) ($this->kind ?? ''));
                });
                if ($alreadyExists) {
                    return;
                }

                $media = $message
                    ->addMedia($fullPath)
                    ->usingFileName($localName)
                    ->toMediaCollection('attachments');

                $existingAttachments[] = [
                    'id' => $media->id,
                    'name' => $this->fileName,
                    'mime_type' => $this->mimeType,
                    'size' => $media->size,
                    'url' => $media->getUrl(),
                    'kind' => $this->kind,
                    'source_url' => $this->fileUrl,
                ];
                $payload['attachments'] = $existingAttachments;
                $pending = is_array($payload['pending_attachments'] ?? null) ? $payload['pending_attachments'] : [];
                $payload['pending_attachments'] = PendingInboundAttachments::removePendingForCompletedDownload(
                    $pending,
                    $this->fileUrl,
                    $this->kind,
                );
                $message->update(['payload' => $payload]);
            });

            $model = MessageModel::query()->with(['replyTo', 'chat'])->find($this->messageId);
            if ($model !== null) {
                $extras = NewChatMessageBroadcastExtras::fromMessage($model);
                event(new ChatMessageUpdated(
                    chatId: $model->chat_id,
                    messageId: $model->id,
                    attachments: $extras['attachments'],
                    pendingAttachments: $extras['pending_attachments'],
                    assignedModeratorUserId: $model->chat?->assigned_to,
                ));
            }

        } catch (\Throwable $e) {
            Log::error('DownloadInboundAttachmentJob: exception', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
