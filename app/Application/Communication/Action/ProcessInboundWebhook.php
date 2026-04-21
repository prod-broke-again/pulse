<?php

declare(strict_types=1);

namespace App\Application\Communication\Action;

use App\Application\Communication\Webhook\InboundAttachmentExtractor;
use App\Application\Communication\Webhook\InboundChatUpsert;
use App\Application\Communication\Webhook\StartCommandDetector;
use App\Application\Communication\Webhook\TelegramMediaGroupInboundBuffer;
use App\Application\Communication\Webhook\TelegramUserMetadataEnricher;
use App\Application\Communication\Webhook\VkUserMetadataEnricher;
use App\Application\Communication\Webhook\WebhookPayloadExtractor;
use App\Domains\Communication\Repository\MessageRepositoryInterface;
use App\Domains\Communication\ValueObject\SenderType;
use App\Domains\Integration\Messenger\MessengerProviderInterface;
use App\Domains\Integration\Repository\SourceRepositoryInterface;
use App\Domains\Integration\ValueObject\SourceType;
use App\Domains\Integration\ValueObject\TelegramMode;
use App\Infrastructure\Integration\Client\TelegramApiClient;
use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Jobs\DownloadInboundAttachmentJob;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\MaybeSendOfflineAutoReply;
use App\Services\SendWelcomeMessage;
use App\Support\PendingInboundAttachments;
use Illuminate\Support\Facades\Log;

final readonly class ProcessInboundWebhook
{
    public function __construct(
        private CreateMessage $createMessage,
        private SourceRepositoryInterface $sourceRepository,
        private MaybeSendOfflineAutoReply $maybeSendOfflineAutoReply,
        private InboundAttachmentExtractor $inboundAttachmentExtractor,
        private WebhookPayloadExtractor $webhookPayloadExtractor,
        private VkUserMetadataEnricher $vkUserMetadataEnricher,
        private TelegramUserMetadataEnricher $telegramUserMetadataEnricher,
        private InboundChatUpsert $inboundChatUpsert,
        private StartCommandDetector $startCommandDetector,
        private SendWelcomeMessage $sendWelcomeMessage,
        private MessageRepositoryInterface $messageRepository,
        private TelegramMediaGroupInboundBuffer $telegramMediaGroupInboundBuffer,
        private HandleBusinessConnectionEvent $handleBusinessConnectionEvent,
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

        if (isset($payload['business_connection'])) {
            $this->handleBusinessConnectionEvent->run($sourceId, $payload);

            return;
        }

        if (isset($payload['edited_business_message']) || isset($payload['deleted_business_messages'])) {
            Log::info('Business edit/delete event ignored', ['source_id' => $sourceId]);

            return;
        }

        if ($source->type === SourceType::Tg) {
            $mode = TelegramMode::fromSettings($source->settings);

            if (isset($payload['business_message']) && $mode !== TelegramMode::Business) {
                Log::warning('business_message arrived on non-business source', ['source_id' => $sourceId]);

                return;
            }

            if (isset($payload['message']) && $mode === TelegramMode::Business) {
                Log::warning('direct message arrived on business source', ['source_id' => $sourceId]);

                return;
            }

            if ($mode === TelegramMode::Business && $this->isBusinessOwnerOutgoingMessage($source->settings, $payload)) {
                if ($this->tryIngestBusinessOwnerMessageAsModerator($sourceId, $source->settings, $payload, $messenger)) {
                    return;
                }

                if ($this->tryIngestBusinessOwnerOutgoingAsTelegramApp($sourceId, $source->settings, $payload, $messenger)) {
                    return;
                }

                Log::info('business owner outgoing message ignored', ['source_id' => $sourceId]);

                return;
            }
        }

        $externalUserId = $this->webhookPayloadExtractor->extractExternalUserId($payload);
        if ($this->startCommandDetector->isStartCommand($source->type, $payload)) {
            $sourceModel = SourceModel::query()->find($sourceId);
            if ($sourceModel !== null) {
                $this->sendWelcomeMessage->run($sourceModel, $externalUserId);
            }

            return;
        }

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
        $userMetadata = $this->telegramUserMetadataEnricher->enrich(
            $source->type,
            $source->settings,
            $payload,
            $userMetadata,
        );
        $externalMessageId = $this->webhookPayloadExtractor->extractExternalMessageId($payload);

        $businessConnectionId = $this->webhookPayloadExtractor->extractBusinessConnectionId($payload);

        $chat = $this->inboundChatUpsert->resolve(
            $sourceId,
            $departmentId,
            $externalUserId,
            $userMetadata,
            $businessConnectionId,
        );

        $attachments = $this->resolveTelegramFileUrls($attachments, $source->type, $source->settings, $sourceId);

        $replyToExternalId = $this->webhookPayloadExtractor->extractReplyToExternalMessageId($payload);
        $replyToInternalId = null;
        if ($replyToExternalId !== null && $replyToExternalId !== '') {
            $replied = $this->messageRepository->findByChatAndExternalMessageId($chat->id, $replyToExternalId);
            $replyToInternalId = $replied?->id;
        }

        $mediaGroupId = $source->type === SourceType::Tg
            ? $this->webhookPayloadExtractor->extractTelegramMediaGroupId($payload)
            : null;
        if ($mediaGroupId !== null && $mediaGroupId !== '') {
            $resolvedForDownloads = $this->onlyAttachmentsWithUrl($attachments);
            if ($resolvedForDownloads === []) {
                return;
            }
            $rawCaption = $this->webhookPayloadExtractor->extractRawTelegramMessageText($payload);
            $this->telegramMediaGroupInboundBuffer->appendAndSchedule(
                $sourceId,
                $chat->id,
                $mediaGroupId,
                [
                    'attachments' => $resolvedForDownloads,
                    'raw_caption' => $rawCaption,
                    'reply_to_message_id' => $replyToInternalId,
                    'telegram_message_id' => $externalMessageId,
                ],
            );

            return;
        }

        if ($attachments !== []) {
            $payload['pending_attachments'] = PendingInboundAttachments::fromDownloadDescriptors($attachments);
        }

        $message = $this->createMessage->run(
            chatId: $chat->id,
            text: $text,
            senderType: SenderType::Client,
            senderId: null,
            payload: $payload,
            externalMessageId: $externalMessageId,
            replyToMessageId: $replyToInternalId,
        );

        $this->dispatchAttachmentDownloads($attachments, $message->id);

        $chatModel = ChatModel::query()->find($chat->id);
        if ($chatModel !== null) {
            $this->maybeSendOfflineAutoReply->run($chatModel);
        }
    }

    /**
     * @param  list<array{url?: string, telegram_file_id?: string, file_name: string, mime_type: string, kind?: string}>  $attachments
     * @param  array<string, mixed>  $settings
     * @return list<array{url: string, file_name: string, mime_type: string, kind?: string}>
     */
    private function resolveTelegramFileUrls(array $attachments, SourceType $sourceType, array $settings, int $sourceId): array
    {
        if ($sourceType !== SourceType::Tg) {
            return $this->onlyAttachmentsWithUrl($attachments);
        }

        $token = isset($settings['bot_token']) && is_string($settings['bot_token'])
            ? trim($settings['bot_token'])
            : '';
        if ($token === '') {
            foreach ($attachments as $attachment) {
                $hasFileId = ! empty($attachment['telegram_file_id']) && is_string($attachment['telegram_file_id']);
                $hasUrl = isset($attachment['url']) && is_string($attachment['url']) && trim($attachment['url']) !== '';
                if ($hasFileId && ! $hasUrl) {
                    Log::warning('Telegram inbound attachment skipped: bot_token missing in source settings', [
                        'source_id' => $sourceId,
                        'telegram_file_id' => $attachment['telegram_file_id'],
                        'kind' => $attachment['kind'] ?? null,
                    ]);
                }
            }

            return $this->onlyAttachmentsWithUrl($attachments);
        }

        $client = new TelegramApiClient($token);
        $resolved = [];
        foreach ($attachments as $attachment) {
            $url = isset($attachment['url']) && is_string($attachment['url']) ? trim($attachment['url']) : '';
            if ($url === '' && ! empty($attachment['telegram_file_id']) && is_string($attachment['telegram_file_id'])) {
                $resolvedUrl = $client->getFileDownloadUrl($attachment['telegram_file_id']);
                if ($resolvedUrl !== null && $resolvedUrl !== '') {
                    $url = $resolvedUrl;
                } else {
                    Log::warning('Telegram getFile did not return download URL for file_id', [
                        'source_id' => $sourceId,
                        'telegram_file_id' => $attachment['telegram_file_id'],
                        'kind' => $attachment['kind'] ?? null,
                    ]);
                }
            }
            if ($url === '') {
                continue;
            }

            $resolved[] = [
                'url' => $url,
                'file_name' => $attachment['file_name'],
                'mime_type' => $attachment['mime_type'],
                'kind' => $attachment['kind'] ?? null,
            ];
        }

        return $resolved;
    }

    /**
     * @param  list<array{url?: string, telegram_file_id?: string, file_name: string, mime_type: string, kind?: string}>  $attachments
     * @return list<array{url: string, file_name: string, mime_type: string, kind?: string}>
     */
    private function onlyAttachmentsWithUrl(array $attachments): array
    {
        $out = [];
        foreach ($attachments as $attachment) {
            $url = isset($attachment['url']) && is_string($attachment['url']) ? trim($attachment['url']) : '';
            if ($url === '') {
                continue;
            }

            $out[] = [
                'url' => $url,
                'file_name' => $attachment['file_name'],
                'mime_type' => $attachment['mime_type'],
                'kind' => $attachment['kind'] ?? null,
            ];
        }

        return $out;
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

    /**
     * When the business account owner writes from the Telegram app, {@see business_message.from} is the owner.
     * If that Telegram id matches a Pulse moderator linked via {@see SocialAccount} (telegram) who can access this
     * source, record the line as a moderator message in the chat with the client peer ({@see Message::chat}).
     */
    private function tryIngestBusinessOwnerMessageAsModerator(
        int $sourceId,
        array $settings,
        array $payload,
        MessengerProviderInterface $messenger,
    ): bool {
        $mediaGroupId = $this->webhookPayloadExtractor->extractTelegramMediaGroupId($payload);
        if ($mediaGroupId !== null && $mediaGroupId !== '') {
            Log::info('business owner outgoing media group skipped (not supported)', ['source_id' => $sourceId]);

            return true;
        }

        $fromId = $this->businessMessageFromTelegramUserId($payload);
        if ($fromId === null || $fromId === '') {
            return false;
        }

        $account = SocialAccount::query()
            ->where('provider', 'telegram')
            ->where('provider_user_id', $fromId)
            ->first();
        if ($account === null) {
            return false;
        }

        $user = $account->user;
        if ($user === null) {
            Log::warning('business owner outgoing: social account has no user', [
                'source_id' => $sourceId,
                'social_account_id' => $account->id,
            ]);

            return false;
        }

        if (! $this->userCanModerateSource($user, $sourceId)) {
            Log::info('business owner outgoing: telegram linked user not on source', [
                'source_id' => $sourceId,
                'user_id' => $user->id,
            ]);

            return false;
        }

        $peerExternalUserId = $this->webhookPayloadExtractor->extractTelegramBusinessMessagePrivateChatPeerExternalUserId($payload);
        if ($peerExternalUserId === null || $peerExternalUserId === '') {
            Log::warning('business owner outgoing: missing private chat peer', ['source_id' => $sourceId]);

            return false;
        }

        if ($peerExternalUserId === $fromId) {
            return false;
        }

        $departmentId = $this->webhookPayloadExtractor->extractDepartmentId($payload, $sourceId);
        $attachments = $this->inboundAttachmentExtractor->extract($payload);
        $text = $this->webhookPayloadExtractor->extractText($payload, $attachments);
        $userMetadata = $this->webhookPayloadExtractor->extractTelegramBusinessMessageChatPeerUserMetadata($payload);
        $userMetadata = $this->vkUserMetadataEnricher->enrich(
            SourceType::Tg,
            $settings,
            $payload,
            $userMetadata,
        );
        $userMetadata = $this->telegramUserMetadataEnricher->enrich(
            SourceType::Tg,
            $settings,
            $payload,
            $userMetadata,
        );
        $externalMessageId = $this->webhookPayloadExtractor->extractExternalMessageId($payload);
        $businessConnectionId = $this->webhookPayloadExtractor->extractBusinessConnectionId($payload);

        $chat = $this->inboundChatUpsert->resolve(
            $sourceId,
            $departmentId,
            $peerExternalUserId,
            $userMetadata,
            $businessConnectionId,
        );

        $attachments = $this->resolveTelegramFileUrls($attachments, SourceType::Tg, $settings, $sourceId);

        $replyToExternalId = $this->webhookPayloadExtractor->extractReplyToExternalMessageId($payload);
        $replyToInternalId = null;
        if ($replyToExternalId !== null && $replyToExternalId !== '') {
            $replied = $this->messageRepository->findByChatAndExternalMessageId($chat->id, $replyToExternalId);
            $replyToInternalId = $replied?->id;
        }

        if ($externalMessageId !== null && $externalMessageId !== ''
            && $this->messageRepository->findByChatAndExternalMessageId($chat->id, $externalMessageId) !== null) {
            return true;
        }

        if ($attachments !== []) {
            $payload['pending_attachments'] = PendingInboundAttachments::fromDownloadDescriptors($attachments);
        }

        $messagePayload = $payload;
        $messagePayload['delivery_channel'] = 'telegram_app';

        $message = $this->createMessage->run(
            chatId: $chat->id,
            text: $text,
            senderType: SenderType::Moderator,
            senderId: $user->id,
            payload: $messagePayload,
            externalMessageId: $externalMessageId,
            replyToMessageId: $replyToInternalId,
        );

        $this->dispatchAttachmentDownloads($attachments, $message->id);

        ChatModel::query()->whereKey($chat->id)->update(['last_auto_reply_at' => now()]);

        return true;
    }

    /**
     * When the business owner writes from the Telegram app but Pulse cannot match a linked moderator
     * ({@see SocialAccount}), still persist the line in the client chat so operators see it — clients show
     * the "Telegram" label when {@code delivery_channel} is {@code telegram_app}.
     */
    private function tryIngestBusinessOwnerOutgoingAsTelegramApp(
        int $sourceId,
        array $settings,
        array $payload,
        MessengerProviderInterface $messenger,
    ): bool {
        $mediaGroupId = $this->webhookPayloadExtractor->extractTelegramMediaGroupId($payload);
        if ($mediaGroupId !== null && $mediaGroupId !== '') {
            Log::info('business owner outgoing media group skipped (not supported)', ['source_id' => $sourceId]);

            return true;
        }

        $peerExternalUserId = $this->webhookPayloadExtractor->extractTelegramBusinessMessagePrivateChatPeerExternalUserId($payload);
        if ($peerExternalUserId === null || $peerExternalUserId === '') {
            return false;
        }

        $fromId = $this->businessMessageFromTelegramUserId($payload);
        if ($fromId !== null && $fromId !== '' && $peerExternalUserId === $fromId) {
            return false;
        }

        $departmentId = $this->webhookPayloadExtractor->extractDepartmentId($payload, $sourceId);
        $attachments = $this->inboundAttachmentExtractor->extract($payload);
        $text = $this->webhookPayloadExtractor->extractText($payload, $attachments);
        $userMetadata = $this->webhookPayloadExtractor->extractTelegramBusinessMessageChatPeerUserMetadata($payload);
        $userMetadata = $this->vkUserMetadataEnricher->enrich(
            SourceType::Tg,
            $settings,
            $payload,
            $userMetadata,
        );
        $userMetadata = $this->telegramUserMetadataEnricher->enrich(
            SourceType::Tg,
            $settings,
            $payload,
            $userMetadata,
        );
        $externalMessageId = $this->webhookPayloadExtractor->extractExternalMessageId($payload);
        $businessConnectionId = $this->webhookPayloadExtractor->extractBusinessConnectionId($payload);

        $chat = $this->inboundChatUpsert->resolve(
            $sourceId,
            $departmentId,
            $peerExternalUserId,
            $userMetadata,
            $businessConnectionId,
        );

        $attachments = $this->resolveTelegramFileUrls($attachments, SourceType::Tg, $settings, $sourceId);

        $replyToExternalId = $this->webhookPayloadExtractor->extractReplyToExternalMessageId($payload);
        $replyToInternalId = null;
        if ($replyToExternalId !== null && $replyToExternalId !== '') {
            $replied = $this->messageRepository->findByChatAndExternalMessageId($chat->id, $replyToExternalId);
            $replyToInternalId = $replied?->id;
        }

        if ($externalMessageId !== null && $externalMessageId !== ''
            && $this->messageRepository->findByChatAndExternalMessageId($chat->id, $externalMessageId) !== null) {
            return true;
        }

        if ($attachments !== []) {
            $payload['pending_attachments'] = PendingInboundAttachments::fromDownloadDescriptors($attachments);
        }

        $messagePayload = $payload;
        $messagePayload['delivery_channel'] = 'telegram_app';

        $message = $this->createMessage->run(
            chatId: $chat->id,
            text: $text,
            senderType: SenderType::Moderator,
            senderId: null,
            payload: $messagePayload,
            externalMessageId: $externalMessageId,
            replyToMessageId: $replyToInternalId,
        );

        $this->dispatchAttachmentDownloads($attachments, $message->id);

        ChatModel::query()->whereKey($chat->id)->update(['last_auto_reply_at' => now()]);

        Log::info('business owner outgoing ingested as telegram_app', [
            'source_id' => $sourceId,
            'chat_id' => $chat->id,
        ]);

        return true;
    }

    private function userCanModerateSource(User $user, int $sourceId): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $source = SourceModel::query()->find($sourceId);
        if ($source === null) {
            return false;
        }

        return $source->users()->whereKey($user->id)->exists();
    }

    /** @param array<string, mixed> $payload */
    private function businessMessageFromTelegramUserId(array $payload): ?string
    {
        $bm = $payload['business_message'] ?? null;
        if (! is_array($bm)) {
            return null;
        }
        $from = $bm['from'] ?? null;
        if (! is_array($from)) {
            return null;
        }
        $fromIdRaw = $from['id'] ?? null;
        if (! is_scalar($fromIdRaw)) {
            return null;
        }
        $fromId = trim((string) $fromIdRaw);

        return $fromId === '' ? null : $fromId;
    }

    /** @param array<string, mixed> $settings @param array<string, mixed> $payload */
    private function isBusinessOwnerOutgoingMessage(array $settings, array $payload): bool
    {
        $ownerRaw = $settings['business_connection_user_id'] ?? null;
        if (! is_scalar($ownerRaw)) {
            return false;
        }
        $ownerId = trim((string) $ownerRaw);
        if ($ownerId === '') {
            return false;
        }

        $bm = $payload['business_message'] ?? null;
        if (! is_array($bm)) {
            return false;
        }
        $from = $bm['from'] ?? null;
        if (! is_array($from)) {
            return false;
        }
        $fromIdRaw = $from['id'] ?? null;
        if (! is_scalar($fromIdRaw)) {
            return false;
        }
        $fromId = trim((string) $fromIdRaw);
        if ($fromId === '') {
            return false;
        }

        return $fromId === $ownerId;
    }
}
