<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Application\Communication\Action\AssignChatToModerator;
use App\Application\Communication\Action\SendMessage;
use App\Application\Integration\ResolveMessengerProvider;
use App\Domains\Communication\Repository\ChatRepositoryInterface;
use App\Domains\Communication\Repository\MessageRepositoryInterface;
use App\Domains\Communication\ValueObject\ChatStatus;
use App\Domains\Communication\ValueObject\SenderType;
use App\Infrastructure\Persistence\Eloquent\ChatModel;
use Filament\Pages\Page;
use Livewire\Attributes\On;

final class ChatPage extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'Коммуникации';

    protected static ?string $navigationLabel = 'Чат';

    protected static ?string $title = 'Чат';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.chat';

    public ?int $selectedChatId = null;

    public string $activeTab = 'my';

    /** @var array<int, array{id: int, chatId: int, senderType: string, text: string, isRead: bool, created_at: ?string, payload: array}> */
    public array $messages = [];

    public string $newMessageText = '';

    public bool $loadingOlder = false;

    public ?int $oldestLoadedId = null;

    protected MessageRepositoryInterface $messageRepository;

    protected ChatRepositoryInterface $chatRepository;

    protected SendMessage $sendMessage;

    protected AssignChatToModerator $assignChatToModerator;

    protected ResolveMessengerProvider $resolveMessenger;

    public function boot(
        MessageRepositoryInterface $messageRepository,
        ChatRepositoryInterface $chatRepository,
        SendMessage $sendMessage,
        AssignChatToModerator $assignChatToModerator,
        ResolveMessengerProvider $resolveMessenger,
    ): void {
        $this->messageRepository = $messageRepository;
        $this->chatRepository = $chatRepository;
        $this->sendMessage = $sendMessage;
        $this->assignChatToModerator = $assignChatToModerator;
        $this->resolveMessenger = $resolveMessenger;
    }

    public function assignToMe(): void
    {
        $selectedChat = $this->getAccessibleSelectedChatModel();
        if ($selectedChat === null) {
            return;
        }
        $userId = auth()->id();
        if ($userId === null) {
            return;
        }
        try {
            $this->assignChatToModerator->run($selectedChat->id, $userId);
            $this->activeTab = 'my';
        } catch (\Throwable) {
            // ignore
        }
    }

    public function closeChat(): void
    {
        $selectedChat = $this->getAccessibleSelectedChatModel();
        if ($selectedChat === null) {
            return;
        }

        $chat = $this->chatRepository->findById($selectedChat->id);
        if ($chat === null) {
            return;
        }
        $this->chatRepository->persist(new \App\Domains\Communication\Entity\Chat(
            id: $chat->id,
            sourceId: $chat->sourceId,
            departmentId: $chat->departmentId,
            externalUserId: $chat->externalUserId,
            userMetadata: $chat->userMetadata,
            status: ChatStatus::Closed,
            assignedTo: $chat->assignedTo,
        ));
        $this->selectedChatId = null;
    }

    private function getAccessibleSelectedChatModel(): ?ChatModel
    {
        if ($this->selectedChatId === null) {
            return null;
        }

        return $this->getSelectedChatProperty();
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, ChatModel> */
    public function getFilteredChatsProperty(): \Illuminate\Database\Eloquent\Collection
    {
        $user = auth()->user();
        $userId = $user?->id;

        $query = $this->baseChatsQuery();
        $this->applyModeratorVisibilityScope($query, $user);

        match ($this->activeTab) {
            'my' => $query->where('assigned_to', $userId),
            'unassigned' => $this->applyUnassignedFilter($query),
            'all' => null,
            default => $query->where('assigned_to', $userId),
        };

        return $query->orderByDesc('updated_at')->limit(100)->get();
    }

    private function baseChatsQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return ChatModel::query()
            ->with(['source', 'department', 'assignee', 'latestMessage'])
            ->whereIn('status', ['new', 'active']);
    }

    private function applyModeratorVisibilityScope(\Illuminate\Database\Eloquent\Builder $query, ?\App\Models\User $user): void
    {
        if ($user === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($user->isAdmin()) {
            return;
        }

        $sourceIds = $this->getModeratorSourceIds($user);
        if ($sourceIds === []) {
            // Strict isolation: moderator without projects should not see chats.
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereIn('source_id', $sourceIds);

        $deptIds = $this->getModeratorDepartmentIds($user);
        if ($deptIds !== []) {
            $query->whereIn('department_id', $deptIds);
        }
    }

    private function applyUnassignedFilter(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $query->whereNull('assigned_to');
    }

    /** @return list<int> */
    private function getModeratorSourceIds(?\App\Models\User $user): array
    {
        if ($user === null || $user->isAdmin()) {
            return [];
        }

        return $user->sources()->pluck('id')->values()->all();
    }

    /** @return list<int> */
    private function getModeratorDepartmentIds(?\App\Models\User $user): array
    {
        if ($user === null || $user->isAdmin()) {
            return [];
        }
        return $user->departments()->pluck('departments.id')->values()->all();
    }

    public function getMyChatsCountProperty(): int
    {
        $user = auth()->user();
        $query = $this->baseChatsQuery();
        $this->applyModeratorVisibilityScope($query, $user);

        return $query->where('assigned_to', auth()->id())->count();
    }

    public function getUnassignedChatsCountProperty(): int
    {
        $user = auth()->user();
        $query = $this->baseChatsQuery();
        $this->applyModeratorVisibilityScope($query, $user);
        $query->whereNull('assigned_to');

        return $query->count();
    }

    public function getAllChatsCountProperty(): int
    {
        $user = auth()->user();
        $query = $this->baseChatsQuery();
        $this->applyModeratorVisibilityScope($query, $user);

        return $query->count();
    }

    public function getIsAdminProperty(): bool
    {
        return auth()->user()?->isAdmin() ?? false;
    }

    /** @return array<string, \Illuminate\Database\Eloquent\Collection<int, ChatModel>> */
    public function getGroupedChatsProperty(): array
    {
        $chats = $this->getFilteredChatsProperty();
        $grouped = [];
        foreach ($chats as $chat) {
            $sourceName = $chat->source?->name ?? 'Без источника';
            if (! isset($grouped[$sourceName])) {
                $grouped[$sourceName] = collect();
            }
            $grouped[$sourceName]->push($chat);
        }
        return $grouped;
    }

    /** @return list<string> Moderator's department names for display. */
    public function getModeratorDepartmentNamesProperty(): array
    {
        $user = auth()->user();
        if ($user === null || $user->isAdmin()) {
            return [];
        }
        return $user->departments()->pluck('name')->values()->all();
    }

    /** @return list<string> Moderator's source names for display. */
    public function getModeratorSourceNamesProperty(): array
    {
        $user = auth()->user();
        if ($user === null || $user->isAdmin()) {
            return [];
        }

        return $user->sources()->pluck('name')->values()->all();
    }

    public function getSelectedChatProperty(): ?ChatModel
    {
        if ($this->selectedChatId === null) {
            return null;
        }

        $user = auth()->user();
        $query = ChatModel::query()
            ->with(['source', 'department', 'assignee']);
        $this->applyModeratorVisibilityScope($query, $user);

        return $query->whereKey($this->selectedChatId)->first();
    }

    public function selectChat(int $chatId): void
    {
        if (! $this->canAccessChatId($chatId)) {
            return;
        }

        $this->selectedChatId = $chatId;
        $this->oldestLoadedId = null;
        $this->loadMessages();
    }

    public function loadMessages(): void
    {
        if ($this->selectedChatId === null) {
            $this->messages = [];

            return;
        }
        if ($this->getSelectedChatProperty() === null) {
            $this->messages = [];
            $this->selectedChatId = null;

            return;
        }

        $list = $this->messageRepository->listByChatIdPaginated(
            $this->selectedChatId,
            50,
            $this->oldestLoadedId,
        );

        $next = array_map(fn ($m) => [
            'id' => $m->id,
            'chatId' => $m->chatId,
            'senderType' => $m->senderType->value,
            'text' => $m->text,
            'isRead' => $m->isRead,
            'created_at' => $m->createdAt?->format('Y-m-d H:i:s'),
            'payload' => $m->payload,
        ], $list);

        if ($this->oldestLoadedId === null) {
            $this->messages = $next;
        } else {
            $this->messages = array_merge($next, $this->messages);
        }

        $first = $list[0] ?? null;
        $this->oldestLoadedId = $first ? $first->id : null;
    }

    public function loadOlderMessages(): void
    {
        if ($this->selectedChatId === null || $this->loadingOlder) {
            return;
        }

        $oldestInView = $this->messages[0]['id'] ?? null;
        if ($oldestInView === null) {
            return;
        }

        $this->loadingOlder = true;
        $this->oldestLoadedId = $oldestInView;
        $this->loadMessages();
        $this->loadingOlder = false;
    }

    public function sendMessage(): void
    {
        $text = trim($this->newMessageText);
        if ($text === '' || $this->selectedChatId === null) {
            return;
        }

        $selectedChat = $this->getAccessibleSelectedChatModel();
        if ($selectedChat === null) {
            return;
        }

        $chat = $this->chatRepository->findById($selectedChat->id);
        if ($chat === null) {
            return;
        }

        $user = auth()->user();
        $senderId = $user?->id;

        try {
            $messenger = $this->resolveMessenger->run($chat->sourceId);
            $this->sendMessage->run(
                chatId: $this->selectedChatId,
                text: $text,
                senderType: SenderType::Moderator,
                senderId: $senderId,
                messenger: $messenger,
                payload: [],
            );
        } catch (\Throwable) {
            $this->newMessageText = $text;

            return;
        }

        $this->newMessageText = '';
        $this->loadMessages();
    }

    public function refreshMessages(): void
    {
        if ($this->selectedChatId !== null) {
            $this->oldestLoadedId = null;
            $this->loadMessages();
        }
    }

    #[On('chat-realtime-refresh')]
    public function refreshMessagesRealtime(?int $chatId = null): void
    {
        if ($this->selectedChatId === null) {
            return;
        }

        if ($chatId !== null && $chatId !== $this->selectedChatId) {
            return;
        }

        $this->refreshMessages();
    }

    private function canAccessChatId(int $chatId): bool
    {
        $user = auth()->user();
        $query = ChatModel::query();
        $this->applyModeratorVisibilityScope($query, $user);

        return $query->whereKey($chatId)->exists();
    }

    public function getViewData(): array
    {
        return [
            'groupedChats' => $this->getGroupedChatsProperty(),
            'selectedChat' => $this->getSelectedChatProperty(),
            'activeTab' => $this->activeTab,
            'myChatsCount' => $this->getMyChatsCountProperty(),
            'unassignedChatsCount' => $this->getUnassignedChatsCountProperty(),
            'allChatsCount' => $this->getAllChatsCountProperty(),
            'isAdmin' => $this->getIsAdminProperty(),
            'moderatorDepartmentNames' => $this->getModeratorDepartmentNamesProperty(),
            'moderatorSourceNames' => $this->getModeratorSourceNamesProperty(),
        ];
    }
}
