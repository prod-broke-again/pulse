<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Communication\Action\SendMessage;
use App\Application\Integration\ResolveMessengerProvider;
use App\Domains\Communication\ValueObject\SenderType;
use App\Http\Requests\Api\V1\ListMessagesRequest;
use App\Http\Requests\Api\V1\SendMessageRequest;
use App\Http\Resources\Api\V1\MessageResource;
use App\Events\MessageRead as MessageReadEvent;
use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

final class ChatMessageController extends Controller
{
    public function index(ChatModel $chat, ListMessagesRequest $request): JsonResponse
    {
        Gate::authorize('view', $chat);

        $validated = $request->validated();
        $beforeId = isset($validated['before_id']) ? (int) $validated['before_id'] : null;
        $limit = (int) ($validated['limit'] ?? $validated['per_page'] ?? 50);

        $query = MessageModel::where('chat_id', $chat->id)->with('media');

        if ($beforeId !== null) {
            $query->where('id', '<', $beforeId);
        }

        $messages = $query->orderByDesc('id')->limit($limit)->get()->reverse()->values();

        return response()->json([
            'data' => MessageResource::collection($messages),
        ]);
    }

    public function send(
        ChatModel $chat,
        SendMessageRequest $request,
        SendMessage $sendMessage,
        ResolveMessengerProvider $resolveMessenger,
    ): JsonResponse {
        Gate::authorize('update', $chat);

        /** @var User $user */
        $user = auth()->user();
        $validated = $request->validated();
        $clientMessageId = $validated['client_message_id'] ?? null;

        if ($clientMessageId !== null) {
            $cacheKey = "client_msg:{$chat->id}:{$clientMessageId}";
            $existingId = Cache::get($cacheKey);
            if ($existingId !== null) {
                $existing = MessageModel::find($existingId);
                if ($existing !== null) {
                    $data = (new MessageResource($existing))->toArray($request);
                    $data['client_message_id'] = $clientMessageId;
                    return response()->json([
                        'data' => $data,
                    ]);
                }
            }
        }

        $text = $validated['text'] ?? '';
        $attachmentPaths = $validated['attachments'] ?? [];

        $messenger = $resolveMessenger->run($chat->source_id);

        $domainMessage = $sendMessage->run(
            chatId: $chat->id,
            text: $text,
            senderType: SenderType::Moderator,
            senderId: $user->id,
            messenger: $messenger,
            payload: [],
        );

        $messageModel = MessageModel::find($domainMessage->id);

        if ($messageModel !== null && $attachmentPaths !== []) {
            foreach ($attachmentPaths as $path) {
                if (Storage::disk('local')->exists($path)) {
                    $fullPath = Storage::disk('local')->path($path);
                    $messageModel
                        ->addMedia($fullPath)
                        ->toMediaCollection('attachments');
                }
            }

            $mediaItems = $messageModel->getMedia('attachments')->map(fn ($media) => [
                'id' => $media->id,
                'name' => $media->file_name,
                'mime_type' => $media->mime_type,
                'size' => $media->size,
                'url' => $media->getUrl(),
            ])->values()->all();

            $messageModel->update([
                'payload' => array_merge($messageModel->payload ?? [], ['attachments' => $mediaItems]),
            ]);
        }

        if ($clientMessageId !== null) {
            Cache::put("client_msg:{$chat->id}:{$clientMessageId}", $domainMessage->id, now()->addHours(24));
        }

        $messageModel?->refresh();

        $data = (new MessageResource($messageModel))->toArray($request);
        if ($clientMessageId !== null) {
            $data['client_message_id'] = $clientMessageId;
        }

        return response()->json([
            'data' => $data,
        ], 201);
    }

    public function markRead(ChatModel $chat, Request $request): JsonResponse
    {
        Gate::authorize('view', $chat);

        $validated = $request->validate([
            'message_ids' => ['sometimes', 'array'],
            'message_ids.*' => ['integer'],
            'up_to_message_id' => ['sometimes', 'integer', Rule::prohibitedIf(function () use ($request) {
                return $request->has('message_ids');
            })],
        ]);

        $messageIds = $validated['message_ids'] ?? null;
        if ($messageIds === null && isset($validated['up_to_message_id'])) {
            $messageIds = MessageModel::where('chat_id', $chat->id)
                ->where('id', '<=', $validated['up_to_message_id'])
                ->pluck('id')
                ->all();
        }
        if ($messageIds === null || $messageIds === []) {
            return response()->json(['ok' => true]);
        }

        $updated = MessageModel::where('chat_id', $chat->id)
            ->whereIn('id', $messageIds)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        if ($updated > 0) {
            $ids = MessageModel::where('chat_id', $chat->id)->whereIn('id', $messageIds)->pluck('id')->all();
            event(new MessageReadEvent($chat->id, $ids));
        }

        return response()->json(['ok' => true]);
    }
}
