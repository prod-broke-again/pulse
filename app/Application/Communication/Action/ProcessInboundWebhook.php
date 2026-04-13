<?php

declare(strict_types=1);

namespace App\Application\Communication\Action;

use App\Application\Communication\Webhook\InboundAttachmentExtractor;
use App\Application\Communication\Webhook\InboundChatUpsert;
use App\Application\Communication\Webhook\VkUserMetadataEnricher;
use App\Application\Communication\Webhook\WebhookPayloadExtractor;
use App\Domains\Communication\ValueObject\SenderType;
use App\Domains\Integration\Messenger\MessengerProviderInterface;
use App\Domains\Integration\Repository\SourceRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Jobs\DownloadInboundAttachmentJob;
use App\Services\MaybeSendOfflineAutoReply;

final readonly class ProcessInboundWebhook
{
    public function __construct(
        private CreateMessage $createMessage,
        private SourceRepositoryInterface $sourceRepository,
        private MaybeSendOfflineAutoReply $maybeSendOfflineAutoReply,
        private InboundAttachmentExtractor $inboundAttachmentExtractor,
        private WebhookPayloadExtractor $webhookPayloadExtractor,
        private VkUserMetadataEnricher $vkUserMetadataEnricher,
        private InboundChatUpsert $inboundChatUpsert,
    ) {}

    /** @param array<string, mixed> $payload */
    public function run(
        int $sourceId,
        MessengerProviderInterface $messenger,
        array $payload,
    ): void {
        if (! $messenger->validateWebhook($payload)) {
            throw new \InvalidArgumentException('Invalid webhook payload');
        }

        $source = $this->sourceRepository->findById($sourceId);
        if ($source === null) {
            throw new \InvalidArgumentException("Source not found: {$sourceId}");
        }

        $externalUserId = $this->webhookPayloadExtractor->extractExternalUserId($payload);
        $departmentId = $this->webhookPayloadExtractor->extractDepartmentId($payload, $sourceId);
        $attachments = $this->inboundAttachmentExtractor->extract($payload);
        $text = $this->webhookPayloadExtractor->extractText($payload, $attachments);
        $userMetadata = $this->webhookPayloadExtractor->extractUserMetadata($payload);
        $userMetadata = $this->vkUserMetadataEnricher->enrich(
            $source->type,
            $source->settings,
            $payload,
            $userMetadata,
        );
        $externalMessageId = $this->webhookPayloadExtractor->extractExternalMessageId($payload);

        $chat = $this->inboundChatUpsert->resolve(
            $sourceId,
            $departmentId,
            $externalUserId,
            $userMetadata,
        );

        $message = $this->createMessage->run(
            chatId: $chat->id,
            text: $text,
            senderType: SenderType::Client,
            senderId: null,
            payload: $payload,
            externalMessageId: $externalMessageId,
        );

        $this->dispatchAttachmentDownloads($attachments, $message->id);

        $chatModel = ChatModel::query()->find($chat->id);
        if ($chatModel !== null) {
            $this->maybeSendOfflineAutoReply->run($chatModel);
        }
    }

    /**
     * @param  list<array{url: string, file_name: string, mime_type: string, kind?: string}>  $attachments
     */
    private function dispatchAttachmentDownloads(array $attachments, int $messageId): void
    {
        foreach ($attachments as $attachment) {
            DownloadInboundAttachmentJob::dispatch(
                $messageId,
                $attachment['url'],
                $attachment['file_name'],
                $attachment['mime_type'],
                $attachment['kind'] ?? null,
            );
        }
    }
}
