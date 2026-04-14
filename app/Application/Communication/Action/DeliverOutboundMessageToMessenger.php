<?php

declare(strict_types=1);

namespace App\Application\Communication\Action;

use App\Domains\Integration\Messenger\MessengerProviderInterface;
use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Infrastructure\Persistence\Eloquent\MessageModel;

/**
 * Sends a persisted moderator message (including local media) to the external messenger.
 */
final readonly class DeliverOutboundMessageToMessenger
{
    public function run(
        MessageModel $message,
        MessengerProviderInterface $messenger,
        ChatModel $chat,
    ): void {
        $options = [
            'message_id' => $message->id,
        ];

        if ($message->reply_markup !== null && $message->reply_markup !== []) {
            $options['reply_markup'] = $message->reply_markup;
        }

        if ($message->reply_to_id !== null) {
            $replied = MessageModel::query()->find($message->reply_to_id);
            if ($replied !== null
                && $replied->external_message_id !== null
                && $replied->external_message_id !== '') {
                $options['reply_to_external_message_id'] = $replied->external_message_id;
            }
        }

        $paths = [];
        foreach ($message->getMedia('attachments') as $media) {
            $path = $media->getPath();
            if (is_string($path) && $path !== '' && is_file($path)) {
                $paths[] = $path;
            }
        }

        if ($paths !== []) {
            $options['local_attachment_paths'] = $paths;
        }

        $messenger->sendMessage($chat->external_user_id, (string) $message->text, $options);
    }
}
