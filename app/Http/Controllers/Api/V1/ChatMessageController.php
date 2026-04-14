<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Communication\Action\DeliverOutboundMessageToMessenger;
use App\Application\Communication\Action\SendMessage;
use App\Application\Integration\ResolveMessengerProvider;
use App\Domains\Communication\ValueObject\SenderType;
use App\Events\MessageRead as MessageReadEvent;
use App\Http\Requests\Api\V1\ListMessagesRequest;
use App\Http\Requests\Api\V1\MarkChatReadRequest;
use App\Http\Requests\Api\V1\SendMessageRequest;
use App\Http\Resources\Api\V1\MessageResource;
use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Infrastructure\Persistence\Eloquent\ChatUserReadStateModel;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

final class ChatMessageController extends Controller
{
    public function index(ChatModel $chat, ListMessagesRequest $request): JsonResponse
    {
        Gate::authorize('view', $chat);

        $validated = $request->validated();
        $beforeId = isset($validated['before_id']) ? (int) $validated['before_id'] : null;
        $afterId = isset($validated['after_id']) ? (int) $validated['after_id'] : null;
        $limit = (int) ($validated['limit'] ?? $validated['per_page'] ?? 50);

        $query = MessageModel::where('chat_id', $chat->id)->with(['media', 'replyTo']);

        if ($afterId !== null) {
            $messages = (clone $query)
                ->where('id', '>', $afterId)
                ->orderBy('id')
                ->limit($limit)
                ->get()
                ->values();
        } elseif ($beforeId !== null) {
            $messages = $query
                ->where('id', '<', $beforeId)
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->reverse()
                ->values();
        } else {
            $messages = $query->orderByDesc('id')->limit($limit)->get()->reverse()->values();
        }

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

        $replyToMessageId = isset($validated['reply_to_message_id']) ? (int) $validated['reply_to_message_id'] : null;

        /** @var list<array{text: string, url: string}>|null */
        $replyMarkup = null;
        if (isset($validated['reply_markup']) && is_array($validated['reply_markup']) && $validated['reply_markup'] !== []) {
            $replyMarkup = array_values(array_map(
                static fn (array $row): array => [
                    'text' => (string) $row['text'],
                    'url' => (string) $row['url'],
                ],
                $validated['reply_markup'],
            ));
        }

        $deferMessengerDelivery = $attachmentPaths !== [];

        $domainMessage = $sendMessage->run(
            chatId: $chat->id,
            text: $text,
            senderType: SenderType::Moderator,
            senderId: $user->id,
            messenger: $messenger,
            payload: [],
            replyToMessageId: $replyToMessageId,
            replyMarkup: $replyMarkup,
            deliverToMessenger: ! $deferMessengerDelivery,
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

        if ($messageModel !== null && $deferMessengerDelivery) {
            app(DeliverOutboundMessageToMessenger::class)->run($messageModel, $messenger, $chat);
        }

        if ($clientMessageId !== null) {
            Cache::put("client_msg:{$chat->id}:{$clientMessageId}", $domainMessage->id, now()->addHours(24));
        }

        $messageModel?->refresh()->loadMissing('replyTo');

        $data = (new MessageResource($messageModel))->toArray($request);
        if ($clientMessageId !== null) {
            $data['client_message_id'] = $clientMessageId;
        }

        return response()->json([
            'data' => $data,
        ], 201);
    }

    public function readChat(ChatModel $chat, MarkChatReadRequest $request): JsonResponse
    {
        Gate::authorize('view', $chat);

        /** @var User $user */
        $user = auth()->user();
        $lastMessageId = (int) $request->validated()['last_message_id'];

        MessageModel::where('chat_id', $chat->id)->whereKey($lastMessageId)->firstOrFail();

        /** @var list<int> */
        $idsToBroadcast = [];

        DB::transaction(function () use ($chat, $user, $lastMessageId, &$idsToBroadcast): void {
            $state = ChatUserReadStateModel::query()->firstOrNew([
                'user_id' => $user->id,
                'chat_id' => $chat->id,
            ]);

            $previous = $state->last_read_message_id ?? 0;
            if ($lastMessageId > $previous) {
                $state->last_read_message_id = $lastMessageId;
                $state->save();
            }

            $idsToMark = MessageModel::query()
                ->where('chat_id', $chat->id)
                ->where('sender_type', 'client')
                ->where('id', '<=', $lastMessageId)
                ->where('is_read', false)
                ->pluck('id')
                ->all();

            if ($idsToMark !== []) {
                MessageModel::whereIn('id', $idsToMark)->update(['is_read' => true]);
                $idsToBroadcast = $idsToMark;
            }
        });

        if ($idsToBroadcast !== []) {
            event(new MessageReadEvent($chat->id, $idsToBroadcast));
        }

        return response()->json([
            'data' => ['ok' => true],
        ]);
    }
}
