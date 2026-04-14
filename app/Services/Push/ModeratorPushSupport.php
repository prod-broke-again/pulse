<?php

declare(strict_types=1);

namespace App\Services\Push;

use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Shared recipient resolution and copy/payload for moderator-facing push transports (Web Push, FCM, future providers).
 */
final class ModeratorPushSupport
{
    /**
     * If chat is assigned, notify only that moderator. Otherwise admins + moderators of the source.
     *
     * @return list<int>
     */
    public function moderatorUserIdsForChat(ChatModel $chat): array
    {
        if ($chat->assigned_to !== null) {
            return [$chat->assigned_to];
        }

        $sourceId = $chat->source_id;
        $admins = User::role('admin')->pluck('id')->all();
        $moderatorsBySource = User::role('moderator')
            ->whereHas('sources', fn ($q) => $q->where('source_id', $sourceId))
            ->pluck('id')
            ->all();

        return array_values(array_unique([...$admins, ...$moderatorsBySource]));
    }

    /**
     * @param  array<string, mixed>  $userMeta
     * @return array{title: string, body: string, data: array<string, string>, tag: string, jsonPayload: string, image: ?string}
     */
    public function buildNotificationPayload(ChatModel $chat, int $chatId, int $messageId, string $text): array
    {
        $sourceName = $chat->source?->name ?? config('app.name');
        $userMeta = is_array($chat->user_metadata) ? $chat->user_metadata : [];
        $guestName = $this->guestDisplayName($userMeta, $chat->external_user_id);
        $title = $sourceName.': '.$guestName;
        $body = Str::limit($text, 100);
        $isUnassigned = $chat->assigned_to === null;
        $avatarUrl = $this->guestAvatarUrlForNotification($userMeta);
        $data = [
            'chat_id' => (string) $chatId,
            'message_id' => (string) $messageId,
            'action' => 'new_message',
            'url' => '/chat?chat='.$chatId,
            'icon_url' => $avatarUrl ?? '',
            'image_url' => $avatarUrl ?? '',
        ];
        $tag = $isUnassigned ? 'unassigned-chat-'.$chatId : 'chat-'.$chatId;
        $pushBody = [
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'tag' => $tag,
        ];
        if ($avatarUrl !== null && $avatarUrl !== '') {
            $pushBody['icon'] = $avatarUrl;
            $pushBody['image'] = $avatarUrl;
        }
        $jsonPayload = json_encode($pushBody, JSON_THROW_ON_ERROR);

        return [
            'title' => $title,
            'body' => $body,
            'data' => $data,
            'tag' => $tag,
            'jsonPayload' => $jsonPayload,
            'image' => $avatarUrl,
        ];
    }

    /**
     * Absolute URL for notification icon (guest avatar from widget metadata).
     *
     * @param  array<string, mixed>  $userMeta
     */
    public function guestAvatarUrlForNotification(array $userMeta): ?string
    {
        foreach (['avatar_url', 'photo_url', 'avatar'] as $key) {
            if (! isset($userMeta[$key]) || ! is_string($userMeta[$key])) {
                continue;
            }
            $raw = trim($userMeta[$key]);
            if ($raw === '') {
                continue;
            }
            if (str_starts_with($raw, 'http://') || str_starts_with($raw, 'https://')) {
                return $raw;
            }
            if (str_starts_with($raw, '/')) {
                return url($raw);
            }
        }

        return null;
    }

    /** @param array<string, mixed> $userMeta */
    public function guestDisplayName(array $userMeta, string $externalUserId): string
    {
        $name = isset($userMeta['name']) && is_scalar($userMeta['name'])
            ? trim((string) $userMeta['name'])
            : '';

        if ($name !== '' && ! $this->isPlaceholderGuestName($name)) {
            return $name;
        }

        return $externalUserId !== '' ? $externalUserId : 'Гость';
    }

    private function isPlaceholderGuestName(string $value): bool
    {
        return in_array(mb_strtolower(trim($value)), ['гость', 'guest', 'клиент', 'client'], true);
    }
}
