<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Communication\Action\AssignChatToModerator;
use App\Application\Communication\Query\ListChatsQuery;
use App\Domains\Communication\Repository\ChatRepositoryInterface;
use App\Domains\Communication\ValueObject\ChatStatus;
use App\Events\UserTyping as UserTypingEvent;
use App\Http\Requests\Api\V1\ListChatsRequest;
use App\Http\Resources\Api\V1\ChatResource;
use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

final class ChatController extends Controller
{
    public function index(ListChatsRequest $request, ListChatsQuery $listChats): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = auth()->user();
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 20);

        $paginator = $listChats->run($user, $filters, $perPage);

        return ChatResource::collection($paginator);
    }

    public function assignMe(ChatModel $chat, AssignChatToModerator $assignChat): JsonResponse
    {
        Gate::authorize('update', $chat);

        /** @var User $user */
        $user = auth()->user();

        $assignChat->run($chat->id, $user->id);

        $chat->refresh()->loadMissing(['source', 'department', 'assignee', 'latestMessage']);

        return response()->json([
            'data' => new ChatResource($chat),
        ]);
    }

    public function close(ChatModel $chat, ChatRepositoryInterface $chatRepository): JsonResponse
    {
        Gate::authorize('update', $chat);

        $domainChat = $chatRepository->findById($chat->id);
        if ($domainChat === null) {
            return response()->json(['message' => 'Chat not found.', 'code' => 'NOT_FOUND'], 404);
        }

        $chatRepository->persist(new \App\Domains\Communication\Entity\Chat(
            id: $domainChat->id,
            sourceId: $domainChat->sourceId,
            departmentId: $domainChat->departmentId,
            externalUserId: $domainChat->externalUserId,
            userMetadata: $domainChat->userMetadata,
            status: ChatStatus::Closed,
            assignedTo: $domainChat->assignedTo,
        ));

        $chat->refresh()->loadMissing(['source', 'department', 'assignee', 'latestMessage']);

        return response()->json([
            'data' => new ChatResource($chat),
        ]);
    }

    public function typing(ChatModel $chat): JsonResponse
    {
        Gate::authorize('view', $chat);

        /** @var User $user */
        $user = auth()->user();

        broadcast(new UserTypingEvent(
            chatId: $chat->id,
            senderType: 'moderator',
            senderName: $user->name,
        ));

        return response()->json([
            'data' => ['message' => 'OK'],
        ]);
    }
}
