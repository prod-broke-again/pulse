<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Communication\Action\CreateMessage;
use App\Application\Communication\Action\HandleAiClientControlAction;
use App\Domains\Communication\ValueObject\SenderType;
use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Infrastructure\Persistence\Eloquent\DepartmentModel;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Infrastructure\Persistence\Eloquent\SourceModel;
use App\Models\WidgetConfig;
use App\Services\MaybeSendOfflineAutoReply;
use App\Services\ModeratorPresenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\ValidationException;

final class WidgetApiController extends Controller
{
    private const PENDING_TOKEN_KEY = 'wp';

    public function __construct(
        private readonly ModeratorPresenceService $moderatorPresenceService,
        private readonly MaybeSendOfflineAutoReply $maybeSendOfflineAutoReply,
        private readonly CreateMessage $createMessage,
        private readonly HandleAiClientControlAction $handleAiClientControl,
    ) {}

    public function config(): JsonResponse
    {
        $driver = config('broadcasting.default');
        $reverb = config('broadcasting.connections.reverb');
        if ($driver !== 'reverb' || empty($reverb['key'])) {
            return response()->json([
                'reverb' => null,
                'message' => 'WebSockets not configured',
            ]);
        }

        $host = $reverb['options']['host'] ?? 'localhost';
        $port = (int) ($reverb['options']['port'] ?? 8080);
        $scheme = $reverb['options']['scheme'] ?? 'http';
        if (in_array($host, ['localhost', '127.0.0.1'], true)) {
            $scheme = 'http';
            $host = '127.0.0.1';
        }

        return response()->json([
            'reverb' => [
                'key' => $reverb['key'],
                'cluster' => 'reverb',
                'host' => $host,
                'port' => $port,
                'scheme' => $scheme,
                'wsPath' => '', // Pusher JS builds path as wsPath + "/app/" + key; empty avoids /app/app/app-key
            ],
        ]);
    }

    public function configUi(Request $request): JsonResponse
    {
        $request->validate([
            'source' => ['required', 'string', 'max:255'],
        ]);
        $sourceIdentifier = (string) $request->query('source');

        $data = Cache::remember(
            WidgetConfig::cacheKey($sourceIdentifier),
            WidgetConfig::CACHE_TTL_SECONDS,
            function () use ($sourceIdentifier): array {
                $config = WidgetConfig::query()
                    ->where('source_identifier', $sourceIdentifier)
                    ->first();

                return $config !== null
                    ? $config->toUiArray()
                    : WidgetConfig::defaults();
            }
        );

        $allowedOrigins = config('widget.allowed_origins', []);
        if ($allowedOrigins === []) {
            $appUrl = rtrim((string) config('app.url'), '/');
            if ($appUrl !== '') {
                $allowedOrigins = [$appUrl];
            }
        }
        $data['allowed_origins'] = $allowedOrigins;

        return response()->json($data);
    }

    public function session(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source_identifier' => ['required', 'string', 'max:255'],
            'visitor_id' => ['required', 'string', 'max:191'],
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'department_slug' => ['nullable', 'string', 'max:255'],
            'meta' => ['nullable', 'array'],
        ]);

        $source = SourceModel::query()
            ->where('identifier', $data['source_identifier'])
            ->where('type', 'web')
            ->first();
        if ($source === null) {
            return response()->json(['ok' => false, 'error' => 'Source not found'], 404);
        }

        $department = $this->resolveDepartment($source->id, $data['department_slug'] ?? null);
        if ($department === null) {
            return response()->json(['ok' => false, 'error' => 'No department configured'], 422);
        }

        $visitorId = (string) $data['visitor_id'];
        $chat = ChatModel::query()
            ->where('source_id', $source->id)
            ->where('external_user_id', $visitorId)
            ->where('status', '!=', 'closed')
            ->orderByDesc('id')
            ->first();
        $lastAny = null;
        if ($chat === null) {
            $lastAny = ChatModel::query()
                ->where('source_id', $source->id)
                ->where('external_user_id', $visitorId)
                ->orderByDesc('id')
                ->first();
        }

        $metadata = [
            'name' => $data['name'] ?? null,
            'email' => $data['email'] ?? null,
            'meta' => $data['meta'] ?? [],
        ];

        if ($chat === null) {
            $userMeta = array_filter($metadata, static fn ($v) => $v !== null && $v !== []);
            $chatToken = $this->makePendingWidgetToken(
                $source->id,
                $visitorId,
                $department->id,
                $userMeta,
                $lastAny?->id,
            );
            $guestName = isset($userMeta['name']) ? (string) $userMeta['name'] : null;
            $guestEmail = isset($userMeta['email']) ? (string) $userMeta['email'] : null;
            $isOnline = $this->moderatorPresenceService->anyModeratorOnlineForSource($source->id);

            return response()->json([
                'ok' => true,
                'chat_token' => $chatToken,
                'is_online' => $isOnline,
                'chat' => [
                    'id' => null,
                    'status' => null,
                    'department' => $department->name,
                    'guest_name' => $guestName,
                    'guest_email' => $guestEmail,
                    'is_online' => $isOnline,
                ],
            ]);
        } else {
            $chat->department_id = $department->id;
            $previousMeta = $chat->user_metadata ?? [];
            $chat->user_metadata = array_filter(
                array_merge($chat->user_metadata ?? [], $metadata),
                static fn ($v) => $v !== null
            );
            $chat->save();
            $nameOrEmailUpdated = ($metadata['name'] ?? null) !== null || ($metadata['email'] ?? null) !== null;
            if ($nameOrEmailUpdated) {
                $userMeta = $chat->user_metadata ?? [];
                event(new \App\Events\ChatGuestUpdated($chat->id, [
                    'name' => $userMeta['name'] ?? null,
                    'email' => $userMeta['email'] ?? null,
                ]));
            }
        }

        $userMeta = $chat->user_metadata ?? [];
        $guestName = isset($userMeta['name']) ? (string) $userMeta['name'] : null;
        $guestEmail = isset($userMeta['email']) ? (string) $userMeta['email'] : null;

        $isOnline = $this->moderatorPresenceService->anyModeratorOnlineForSource($source->id);

        return response()->json([
            'ok' => true,
            'chat_token' => $this->makeChatToken($chat),
            'is_online' => $isOnline,
            'chat' => [
                'id' => $chat->id,
                'status' => $chat->status,
                'department' => $chat->department?->name,
                'guest_name' => $guestName,
                'guest_email' => $guestEmail,
                'is_online' => $isOnline,
            ],
        ]);
    }

    public function messages(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chat_token' => ['required', 'string'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $parsed = $this->parseChatToken($data['chat_token']);
        if ($parsed['type'] === 'pending') {
            $isOnline = $this->moderatorPresenceService->anyModeratorOnlineForSource(
                (int) $parsed['source_id'],
            );

            return response()->json([
                'ok' => true,
                'is_online' => $isOnline,
                'messages' => [],
            ]);
        }
        $chat = $parsed['chat'];
        $limit = (int) ($data['limit'] ?? 50);

        $messages = MessageModel::query()
            ->where('chat_id', $chat->id)
            ->orderByDesc('id')
            ->with(['sender' => function ($q): void {
                $q->select('id', 'name', 'avatar_url');
            }])
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(function (MessageModel $m): array {
                $row = [
                    'id' => $m->id,
                    'sender_type' => $m->sender_type,
                    'text' => $m->text,
                    'reply_markup' => $m->reply_markup,
                    'payload' => $m->payload ?? [],
                    'created_at' => $m->created_at?->toISOString(),
                    'is_read' => $m->is_read,
                ];
                if ($m->sender_type === 'moderator' && $m->relationLoaded('sender') && $m->sender !== null) {
                    $n = trim((string) $m->sender->name);
                    $row['sender_name'] = $n !== '' ? $n : 'Модератор';
                    $a = $m->sender->avatar_url;
                    $row['sender_avatar_url'] = is_string($a) && $a !== '' ? $a : null;
                } else {
                    $row['sender_name'] = null;
                    $row['sender_avatar_url'] = null;
                }

                return $row;
            })
            ->all();

        $isOnline = $this->moderatorPresenceService->anyModeratorOnlineForSource($chat->source_id);

        return response()->json([
            'ok' => true,
            'is_online' => $isOnline,
            'messages' => $messages,
        ]);
    }

    public function send(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chat_token' => ['required', 'string'],
            'text' => ['required', 'string', 'max:4000'],
            'payload' => ['nullable', 'array'],
        ]);

        $parsed = $this->parseChatToken($data['chat_token']);
        $fromPending = $parsed['type'] === 'pending';
        if ($fromPending) {
            $chat = $this->createChatFromPendingToken($parsed);
        } else {
            $chat = $parsed['chat'];
        }
        $oldChatId = (int) $chat->id;
        $text = trim((string) $data['text']);
        if ($text === '') {
            throw ValidationException::withMessages(['text' => 'Message text is required.']);
        }

        if ($chat->status === 'closed') {
            $lastAny = ChatModel::query()
                ->where('source_id', $chat->source_id)
                ->where('external_user_id', $chat->external_user_id)
                ->orderByDesc('id')
                ->first();
            $chat = ChatModel::create([
                'source_id' => $chat->source_id,
                'department_id' => $chat->department_id,
                'external_user_id' => $chat->external_user_id,
                'user_metadata' => $chat->user_metadata ?? [],
                'status' => 'new',
                'assigned_to' => null,
                'previous_chat_id' => $lastAny?->id,
            ]);
        }

        $message = $this->createMessage->run(
            chatId: $chat->id,
            text: $text,
            senderType: SenderType::Client,
            senderId: null,
            payload: $data['payload'] ?? [],
        );

        $chat->refresh();
        $this->maybeSendOfflineAutoReply->run($chat);

        $isOnline = $this->moderatorPresenceService->anyModeratorOnlineForSource($chat->source_id);

        $msgRow = MessageModel::query()->find($message->id);

        $payload = [
            'ok' => true,
            'is_online' => $isOnline,
            'message' => [
                'id' => $message->id,
                'sender_type' => $message->senderType->value,
                'text' => $message->text,
                'reply_markup' => $message->replyMarkup,
                'payload' => $message->payload,
                'created_at' => $msgRow?->created_at?->toISOString(),
            ],
        ];
        if ($fromPending || (int) $chat->id !== $oldChatId) {
            $payload['chat_token'] = $this->makeChatToken($chat);
            $payload['chat'] = [
                'id' => $chat->id,
                'status' => $chat->status,
            ];
        }

        return response()->json($payload);
    }

    public function chatAction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chat_token' => ['required', 'string'],
            'action' => ['required', 'string', 'in:'.implode(',', [
                \App\Support\AiClientActionPayload::A_HUMAN,
                \App\Support\AiClientActionPayload::A_RESOLVED_YES,
                \App\Support\AiClientActionPayload::A_RESOLVED_NO,
            ])],
        ]);
        $parsed = $this->parseChatToken($data['chat_token']);
        if ($parsed['type'] === 'pending') {
            return response()->json(['ok' => false, 'error' => 'Chat not yet started'], 422);
        }
        $chat = $parsed['chat'];
        $r = $this->handleAiClientControl->run(
            (int) $chat->source_id,
            $chat->id,
            $data['action'],
        );
        if (! $r['ok']) {
            return response()->json(['ok' => false, 'error' => $r['error'] ?? 'unknown'], 422);
        }
        $chat->refresh();

        return response()->json(['ok' => true, 'status' => $chat->status]);
    }

    public function typing(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chat_token' => ['required', 'string'],
        ]);

        $parsed = $this->parseChatToken($data['chat_token']);
        if ($parsed['type'] === 'pending') {
            return response()->json(['ok' => true]);
        }
        $chat = $parsed['chat'];
        $userMeta = $chat->user_metadata ?? [];
        $guestName = isset($userMeta['name']) ? (string) $userMeta['name'] : null;

        event(new \App\Events\UserTyping(
            chatId: $chat->id,
            senderType: 'client',
            senderName: $guestName,
        ));

        return response()->json(['ok' => true]);
    }

    public function markRead(Request $request): JsonResponse
    {
        $data = $request->validate([
            'chat_token' => ['required', 'string'],
            'message_ids' => ['sometimes', 'array'],
            'message_ids.*' => ['integer'],
            'up_to_message_id' => ['sometimes', 'integer'],
        ]);

        $parsed = $this->parseChatToken($data['chat_token']);
        if ($parsed['type'] === 'pending') {
            return response()->json(['ok' => true]);
        }
        $chat = $parsed['chat'];
        $messageIds = $data['message_ids'] ?? null;
        if ($messageIds === null && isset($data['up_to_message_id'])) {
            $messageIds = MessageModel::where('chat_id', $chat->id)
                ->where('sender_type', 'moderator')
                ->where('id', '<=', $data['up_to_message_id'])
                ->pluck('id')
                ->all();
        }
        if ($messageIds === null || $messageIds === []) {
            return response()->json(['ok' => true]);
        }

        $updated = MessageModel::where('chat_id', $chat->id)
            ->whereIn('id', $messageIds)
            ->where('sender_type', 'moderator')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        if ($updated > 0) {
            $ids = MessageModel::where('chat_id', $chat->id)->whereIn('id', $messageIds)->pluck('id')->all();
            event(new \App\Events\MessageRead($chat->id, $ids));
        }

        return response()->json(['ok' => true]);
    }

    private function resolveDepartment(int $sourceId, ?string $slug): ?DepartmentModel
    {
        if ($slug !== null && $slug !== '') {
            $department = DepartmentModel::query()
                ->where('source_id', $sourceId)
                ->where('slug', $slug)
                ->where('is_active', true)
                ->first();
            if ($department !== null) {
                return $department;
            }
        }

        return DepartmentModel::query()
            ->where('source_id', $sourceId)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();
    }

    private function makeChatToken(ChatModel $chat): string
    {
        $payload = implode('|', [
            (string) $chat->id,
            (string) $chat->source_id,
            (string) $chat->external_user_id,
        ]);

        return Crypt::encryptString($payload);
    }

    private function makePendingWidgetToken(
        int $sourceId,
        string $visitorId,
        int $departmentId,
        array $userMetadata,
        ?int $previousChatId,
    ): string {
        $payload = [
            't' => self::PENDING_TOKEN_KEY,
            'source_id' => $sourceId,
            'external_user_id' => $visitorId,
            'department_id' => $departmentId,
            'user_metadata' => $userMetadata,
            'previous_chat_id' => $previousChatId,
        ];

        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array{
     *     type: 'pending',
     *     source_id: int,
     *     external_user_id: string,
     *     department_id: int,
     *     user_metadata: array,
     *     previous_chat_id: int|null
     * }|array{type: 'chat', chat: ChatModel}
     */
    private function parseChatToken(string $token): array
    {
        try {
            $decrypted = Crypt::decryptString($token);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['chat_token' => 'Invalid chat token.']);
        }

        $trim = trim($decrypted);
        if ($trim !== '' && $trim[0] === '{') {
            $json = json_decode($decrypted, true);
            if (is_array($json) && ($json['t'] ?? null) === self::PENDING_TOKEN_KEY) {
                if (! isset($json['source_id'], $json['external_user_id'], $json['department_id'])) {
                    throw ValidationException::withMessages(['chat_token' => 'Malformed chat token.']);
                }

                $prev = $json['previous_chat_id'] ?? null;

                return [
                    'type' => 'pending',
                    'source_id' => (int) $json['source_id'],
                    'external_user_id' => (string) $json['external_user_id'],
                    'department_id' => (int) $json['department_id'],
                    'user_metadata' => is_array($json['user_metadata'] ?? null) ? $json['user_metadata'] : [],
                    'previous_chat_id' => $prev === null || $prev === '' ? null : (int) $prev,
                ];
            }
            if (is_array($json) && array_key_exists('t', $json)) {
                throw ValidationException::withMessages(['chat_token' => 'Malformed chat token.']);
            }
        }

        [$chatId, $sourceId, $externalUserId] = array_pad(explode('|', $decrypted, 3), 3, null);
        if ($chatId === null || $sourceId === null || $externalUserId === null) {
            throw ValidationException::withMessages(['chat_token' => 'Malformed chat token.']);
        }

        $chat = ChatModel::query()
            ->whereKey((int) $chatId)
            ->where('source_id', (int) $sourceId)
            ->where('external_user_id', (string) $externalUserId)
            ->first();

        if ($chat === null) {
            throw ValidationException::withMessages(['chat_token' => 'Chat not found.']);
        }

        return ['type' => 'chat', 'chat' => $chat];
    }

    /**
     * @param  array{
     *     type: 'pending',
     *     source_id: int,
     *     external_user_id: string,
     *     department_id: int,
     *     user_metadata: array,
     *     previous_chat_id: int|null
     * }  $pending
     */
    private function createChatFromPendingToken(array $pending): ChatModel
    {
        $source = SourceModel::query()
            ->whereKey($pending['source_id'])
            ->where('type', 'web')
            ->first();
        if ($source === null) {
            throw ValidationException::withMessages(['chat_token' => 'Source not found.']);
        }

        $department = DepartmentModel::query()
            ->whereKey($pending['department_id'])
            ->where('source_id', $source->id)
            ->where('is_active', true)
            ->first();
        if ($department === null) {
            throw ValidationException::withMessages(['chat_token' => 'Invalid department.']);
        }

        $userMeta = is_array($pending['user_metadata'] ?? null) ? $pending['user_metadata'] : [];
        $chat = ChatModel::create([
            'source_id' => $source->id,
            'department_id' => $department->id,
            'external_user_id' => $pending['external_user_id'],
            'user_metadata' => array_filter(
                $userMeta,
                static fn ($v) => $v !== null && $v !== []
            ),
            'status' => 'new',
            'assigned_to' => null,
            'previous_chat_id' => $pending['previous_chat_id'] ?? null,
        ]);

        $um = $chat->user_metadata ?? [];
        if (($um['name'] ?? null) !== null || ($um['email'] ?? null) !== null) {
            event(new \App\Events\ChatGuestUpdated($chat->id, [
                'name' => $um['name'] ?? null,
                'email' => $um['email'] ?? null,
            ]));
        }

        return $chat;
    }
}
