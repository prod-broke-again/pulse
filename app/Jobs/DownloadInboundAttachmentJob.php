<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Infrastructure\Persistence\Eloquent\MessageModel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
    ) {}

    public function handle(): void
    {
        $message = MessageModel::find($this->messageId);
        if ($message === null) {
            Log::warning('DownloadInboundAttachmentJob: message not found', ['message_id' => $this->messageId]);

            return;
        }

        try {
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

            $media = $message
                ->addMedia($fullPath)
                ->usingFileName($localName)
                ->toMediaCollection('attachments');

            $payload = $message->payload ?? [];
            $attachments = $payload['attachments'] ?? [];
            $attachments[] = [
                'id' => $media->id,
                'name' => $this->fileName,
                'mime_type' => $this->mimeType,
                'size' => $media->size,
                'url' => $media->getUrl(),
            ];
            $payload['attachments'] = $attachments;
            $message->update(['payload' => $payload]);

        } catch (\Throwable $e) {
            Log::error('DownloadInboundAttachmentJob: exception', [
                'message_id' => $this->messageId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
