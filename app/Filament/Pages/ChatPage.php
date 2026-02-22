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
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Events\UserTyping as UserTypingEvent;
use App\Models\CannedResponse;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Attributes\On;
use Livewire\WithFileUploads;

final class ChatPage extends Page
{
    use WithFileUploads;
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static string|\UnitEnum|null $navigationGroup = 'Коммуникации';

    protected static ?string $navigationLabel = 'Чат';

    protected static ?string $title = 'Чат';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.chat';

    public ?int $selectedChatId = null;

    public string $activeTab = 'my';

    public string $chatSearch = '';

    public string $chatStatusFilter = 'open';

    /** @var array<int, array{id: int, chatId: int, senderType: string, text: string, isRead: bool, created_at: ?string, payload: array}> */
    public array $messages = [];

    public string $newMessageText = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $newAttachmentFile = null;

    /** @var array<int, string> path => display name */
    public array $pendingAttachmentPaths = [];

    public array $pendingAttachmentNames = [];

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
        $this->applySearchFilter($query);

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
        $query = ChatModel::query()
            ->with(['source', 'department', 'assignee', 'latestMessage']);

        if ($this->chatStatusFilter === 'closed') {
            $query->where('status', 'closed');
        } else {
            $query->whereIn('status', ['new', 'active']);
        }

        return $query;
    }

    private function applySearchFilter(\Illuminate\Database\Eloquent\Builder $query): void
    {
        $search = trim($this->chatSearch);
        if ($search === '') {
            return;
        }

        $term = '%'.$search.'%';
        $jsonLikeRaw = DB::connection()->getDriverName() === 'pgsql'
            ? 'user_metadata::text like ?'
            : 'user_metadata like ?';
        $query->where(function (\Illuminate\Database\Eloquent\Builder $q) use ($term, $jsonLikeRaw): void {
            $q->where('external_user_id', 'like', $term)
                ->orWhereRaw($jsonLikeRaw, [$term])
                ->orWhereHas('messages', fn (\Illuminate\Database\Eloquent\Builder $mq) => $mq->where('text', 'like', $term)->limit(1));
        });
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

    public function updatedNewAttachmentFile(): void
    {
        if ($this->newAttachmentFile === null) {
            return;
        }

        $this->validate([
            'newAttachmentFile' => ['file', 'max:20480'], // 20MB
        ]);

        if (count($this->pendingAttachmentPaths) >= 10) {
            $this->newAttachmentFile = null;

            return;
        }

        $user = auth()->user();
        $userId = $user?->id;
        if ($userId === null) {
            $this->newAttachmentFile = null;

            return;
        }

        $file = $this->newAttachmentFile;
        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $filename = Str::uuid()->toString().'.'.$extension;
        $path = $file->storeAs('uploads/pending/'.$userId, $filename, 'local');
        if ($path !== false) {
            $this->pendingAttachmentPaths[] = $path;
            $this->pendingAttachmentNames[] = $file->getClientOriginalName();
        }
        $this->newAttachmentFile = null;
    }

    public function removeAttachment(int $index): void
    {
        if (isset($this->pendingAttachmentPaths[$index])) {
            array_splice($this->pendingAttachmentPaths, $index, 1);
            array_splice($this->pendingAttachmentNames, $index, 1);
        }
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, CannedResponse> */
    public function getCannedResponsesProperty(): \Illuminate\Database\Eloquent\Collection
    {
        $selected = $this->getSelectedChatProperty();
        if ($selected === null) {
            return new \Illuminate\Database\Eloquent\Collection([]);
        }

        $sourceId = $selected->source_id;

        return CannedResponse::query()
            ->where('is_active', true)
            ->where(function ($q) use ($sourceId): void {
                $q->where('source_id', $sourceId)->orWhereNull('source_id');
            })
            ->orderBy('code')
            ->get();
    }

    public function insertCannedResponse(int $id): void
    {
        $response = CannedResponse::find($id);
        if ($response !== null) {
            $this->newMessageText = $response->text;
        }
    }

    public function broadcastTyping(): void
    {
        if ($this->selectedChatId === null) {
            return;
        }

        $selected = $this->getAccessibleSelectedChatModel();
        if ($selected === null) {
            return;
        }

        $user = auth()->user();
        if ($user === null) {
            return;
        }

        broadcast(new UserTypingEvent(
            chatId: $this->selectedChatId,
            senderType: 'moderator',
            senderName: $user->name,
        ));
    }

    public function sendMessage(): void
    {
        $text = trim($this->newMessageText);
        $hasAttachments = $this->pendingAttachmentPaths !== [];
        if (($text === '' && ! $hasAttachments) || $this->selectedChatId === null) {
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
            $persisted = $this->sendMessage->run(
                chatId: $this->selectedChatId,
                text: $text,
                senderType: SenderType::Moderator,
                senderId: $senderId,
                messenger: $messenger,
                payload: [],
            );

            $attachmentPaths = $this->pendingAttachmentPaths;
            $this->pendingAttachmentPaths = [];
            $this->pendingAttachmentNames = [];

            if ($attachmentPaths !== []) {
                $messageModel = MessageModel::find($persisted->id);
                if ($messageModel !== null) {
                    foreach ($attachmentPaths as $path) {
                        if (Storage::disk('local')->exists($path)) {
                            $fullPath = Storage::disk('local')->path($path);
                            $messageModel->addMedia($fullPath)->toMediaCollection('attachments');
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
            }
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
            'pendingAttachmentNames' => $this->pendingAttachmentNames,
            'cannedResponses' => $this->getCannedResponsesProperty(),
        ];
    }
}
