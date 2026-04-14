<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Communication\Action\AssignChatToModerator;
use App\Application\Communication\Action\ChangeChatDepartment;
use App\Application\Integration\Action\SyncChatHistoryFromProvider;
use App\Application\Communication\Query\ListChatsQuery;
use App\Domains\Communication\Repository\ChatRepositoryInterface;
use App\Domains\Communication\ValueObject\ChatStatus;
use App\Events\UserTyping as UserTypingEvent;
use App\Http\Requests\Api\V1\ChangeChatDepartmentRequest;
use App\Http\Requests\Api\V1\ListChatsRequest;
use App\Http\Requests\Api\V1\MuteChatRequest;
use App\Http\Resources\Api\V1\ChatResource;
use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Infrastructure\Persistence\Eloquent\ChatUserReadStateModel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

final class ChatController extends Controller
{
    public function show(ChatModel $chat): JsonResponse
    {
        Gate::authorize('view', $chat);

        /** @var User $user */
        $user = auth()->user();
        $chat->loadMissing(['source', 'department', 'assignee', 'latestMessage']);
        $chat->load([
            'userReadStates' => function ($q) use ($user): void {
                $q->where('user_id', $user->id);
            },
        ]);
        $chat->loadUnreadCountForUser($user);

        return response()->json([
            'data' => new ChatResource($chat),
        ]);
    }

    public function index(ListChatsRequest $request, ListChatsQuery $listChats): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = auth()->user();
        /** @var array<string, mixed> $filters */
        $filters = $request->validated();
        $perPage = (int) ($filters['per_page'] ?? 20);

        $paginator = $listChats->run($user, $filters, $perPage);

        return ChatResource::collection($paginator);
    }

    public function tabCounts(ListChatsRequest $request, ListChatsQuery $listChats): JsonResponse
    {
        /** @var User $user */
        $user = auth()->user();
        /** @var array<string, mixed> $filters */
        $filters = $request->validated();
        unset($filters['tab'], $filters['page'], $filters['per_page']);

        return response()->json([
            'data' => $listChats->tabCounts($user, $filters),
        ]);
    }

    public function assignMe(ChatModel $chat, AssignChatToModerator $assignChat): JsonResponse
    {
        Gate::authorize('update', $chat);

        /** @var User $user */
        $user = auth()->user();

        $assignChat->run($chat->id, $user->id);

        $chat->refresh()->loadMissing(['source', 'department', 'assignee', 'latestMessage']);
        $chat->load([
            'userReadStates' => function ($q) use ($user): void {
                $q->where('user_id', $user->id);
            },
        ]);
        $chat->loadUnreadCountForUser($user);

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
            topic: $domainChat->topic,
        ));

        /** @var User $user */
        $user = auth()->user();
        $chat->refresh()->loadMissing(['source', 'department', 'assignee', 'latestMessage']);
        $chat->load([
            'userReadStates' => function ($q) use ($user): void {
                $q->where('user_id', $user->id);
            },
        ]);
        $chat->loadUnreadCountForUser($user);

        return response()->json([
            'data' => new ChatResource($chat),
        ]);
    }

    public function mute(ChatModel $chat, MuteChatRequest $request): JsonResponse
    {
        Gate::authorize('view', $chat);

        /** @var User $user */
        $user = auth()->user();
        $mode = $request->validated('mode');

        $muteUntil = match ($mode) {
            '1h' => now()->addHour(),
            '8h' => now()->addHours(8),
            'forever' => now()->addYears(100),
            'unmute' => null,
        };

        ChatUserReadStateModel::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'chat_id' => $chat->id,
            ],
            [
                'muted_until' => $muteUntil,
            ],
        );

        $chat->refresh()->loadMissing(['source', 'department', 'assignee', 'latestMessage']);
        $chat->load([
            'userReadStates' => function ($q) use ($user): void {
                $q->where('user_id', $user->id);
            },
        ]);
        $chat->loadUnreadCountForUser($user);

        return response()->json([
            'data' => new ChatResource($chat),
        ]);
    }

    public function syncHistory(ChatModel $chat, SyncChatHistoryFromProvider $sync): JsonResponse
    {
        Gate::authorize('update', $chat);

        $data = $sync->run($chat);

        return response()->json([
            'data' => $data,
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

    public function changeDepartment(
        ChatModel $chat,
        ChangeChatDepartmentRequest $request,
        ChangeChatDepartment $action,
    ): JsonResponse {
        Gate::authorize('update', $chat);

        /** @var User $user */
        $user = auth()->user();

        $action->run($chat->id, (int) $request->validated('department_id'), $user);

        $chat->refresh()->loadMissing(['source', 'department', 'assignee', 'latestMessage']);
        $chat->load([
            'userReadStates' => function ($q) use ($user): void {
                $q->where('user_id', $user->id);
            },
        ]);
        $chat->loadUnreadCountForUser($user);

        return response()->json([
            'data' => new ChatResource($chat),
        ]);
    }
}
