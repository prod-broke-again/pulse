<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Ai\Dto\AiChatKickoffDto;
use App\Application\Ai\Dto\AiSuggestedReplyDto;
use App\Application\Communication\Action\ChangeChatDepartment;
use App\Contracts\Ai\AiProviderInterface;
use App\Domains\Communication\Entity\Chat;
use App\Domains\Communication\Repository\ChatRepositoryInterface;
use App\Domains\Integration\Entity\Department;
use App\Domains\Integration\Repository\DepartmentRepositoryInterface;
use App\Events\ChatTopicGenerated;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Support\ChatAiKickoffCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class GenerateChatTopicJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Максимум отделов в user-сообщении kickoff (входные токены). */
    private const KICKOFF_DEPARTMENTS_MAX = 30;

    public int $tries = 2;

    public int $timeout = 30;

    public function __construct(
        public int $chatId,
    ) {}

    public function handle(
        ChatRepositoryInterface $chatRepository,
        AiProviderInterface $ai,
        DepartmentRepositoryInterface $departmentRepository,
        ChangeChatDepartment $changeChatDepartment,
    ): void {
        $chat = $chatRepository->findById($this->chatId);
        if ($chat === null || ($chat->topic !== null && $chat->topic !== '')) {
            return;
        }

        $clientMessages = MessageModel::where('chat_id', $this->chatId)
            ->where('sender_type', 'client')
            ->orderBy('id')
            ->limit(2)
            ->pluck('text')
            ->all();

        $messagesText = Str::limit(
            implode("\n", array_map('trim', $clientMessages)),
            1500,
        );

        if ($messagesText === '') {
            return;
        }

        $aiDepartmentAlreadyDone = $chat->aiSuggestedDepartmentId !== null
            && $chat->aiDepartmentConfidence !== null;

        if ($aiDepartmentAlreadyDone) {
            $kickoff = $this->kickoffDtoFromCacheOrEmpty();
        } else {
            $departmentsForKickoff = [];
            if (config('features.ai_department_assignment')) {
                $filtered = array_values(array_filter(
                    $departmentRepository->listBySourceId($chat->sourceId),
                    static fn (Department $d) => $d->isActive && $d->aiEnabled,
                ));
                $departmentsForKickoff = array_slice($filtered, 0, self::KICKOFF_DEPARTMENTS_MAX);
            }

            $kickoff = $ai->generateKickoffFromClientMessages($messagesText, $departmentsForKickoff);

            $sealMessageId = (int) MessageModel::query()->where('chat_id', $this->chatId)->max('id');
            $hasKickoffPayload = ($kickoff->topic !== null && $kickoff->topic !== '')
                || $kickoff->summary !== ''
                || $kickoff->intentTag !== null
                || $kickoff->replies !== [];
            if ($sealMessageId > 0 && $hasKickoffPayload) {
                ChatAiKickoffCache::put($this->chatId, $sealMessageId, $kickoff);
            }

            if (config('features.ai_department_assignment')
                && $kickoff->suggestedDepartmentId !== null
                && $kickoff->confidence !== null) {
                $validIds = array_map(static fn (Department $d) => $d->id, $departmentsForKickoff);
                if (! in_array($kickoff->suggestedDepartmentId, $validIds, true)) {
                    Log::warning('AI suggested invalid department id', [
                        'chat_id' => $this->chatId,
                        'suggested_department_id' => $kickoff->suggestedDepartmentId,
                        'valid_ids' => $validIds,
                    ]);
                } else {
                    $chat = $this->applyAiDepartmentAuditAndAssign(
                        $chat,
                        $kickoff,
                        $chatRepository,
                        $changeChatDepartment,
                    );
                }
            }
        }

        $topic = $kickoff->topic;
        if ($topic === null || $topic === '') {
            return;
        }

        $latest = $chatRepository->findById($this->chatId) ?? $chat;
        $chatRepository->persist(new Chat(
            id: $latest->id,
            sourceId: $latest->sourceId,
            departmentId: $latest->departmentId,
            externalUserId: $latest->externalUserId,
            userMetadata: $latest->userMetadata,
            status: $latest->status,
            assignedTo: $latest->assignedTo,
            topic: $topic,
            aiSuggestedDepartmentId: $latest->aiSuggestedDepartmentId,
            aiDepartmentConfidence: $latest->aiDepartmentConfidence,
            aiDepartmentAssignedAt: $latest->aiDepartmentAssignedAt,
            departmentReassignedByUserId: $latest->departmentReassignedByUserId,
        ));

        event(new ChatTopicGenerated(
            chatId: $this->chatId,
            topic: $topic,
            assignedModeratorUserId: $latest->assignedTo,
        ));
    }

    /**
     * При retry после успешного audit: тема и replies подтягиваются из kickoff-кеша, если он ещё свежий.
     */
    private function kickoffDtoFromCacheOrEmpty(): AiChatKickoffDto
    {
        $cached = ChatAiKickoffCache::getIfFresh($this->chatId);
        if ($cached === null) {
            return new AiChatKickoffDto;
        }

        $replies = [];
        foreach ($cached['replies'] ?? [] as $i => $row) {
            if (! is_array($row)) {
                continue;
            }
            $id = isset($row['id']) ? (string) $row['id'] : 'r'.($i + 1);
            $text = isset($row['text']) ? trim((string) $row['text']) : '';
            if ($text !== '') {
                $replies[] = new AiSuggestedReplyDto(id: $id, text: $text);
            }
        }

        $intentRaw = $cached['intent_tag'] ?? null;
        $intentStr = is_string($intentRaw) ? trim($intentRaw) : '';
        $intentTag = $intentStr !== '' ? $intentStr : null;

        $topicRaw = $cached['topic'] ?? null;
        $topic = is_string($topicRaw) && trim($topicRaw) !== '' ? trim($topicRaw) : null;

        return new AiChatKickoffDto(
            topic: $topic,
            summary: isset($cached['summary']) ? trim((string) $cached['summary']) : '',
            intentTag: $intentTag,
            replies: $replies,
        );
    }

    private function applyAiDepartmentAuditAndAssign(
        Chat $chat,
        AiChatKickoffDto $kickoff,
        ChatRepositoryInterface $chatRepository,
        ChangeChatDepartment $changeChatDepartment,
    ): Chat {
        $suggestedId = $kickoff->suggestedDepartmentId;
        $confidence = $kickoff->confidence;
        if ($suggestedId === null || $confidence === null) {
            return $chat;
        }

        $chat = $chatRepository->persist(new Chat(
            id: $chat->id,
            sourceId: $chat->sourceId,
            departmentId: $chat->departmentId,
            externalUserId: $chat->externalUserId,
            userMetadata: $chat->userMetadata,
            status: $chat->status,
            assignedTo: $chat->assignedTo,
            topic: $chat->topic,
            aiSuggestedDepartmentId: $suggestedId,
            aiDepartmentConfidence: $confidence,
            aiDepartmentAssignedAt: $chat->aiDepartmentAssignedAt,
            departmentReassignedByUserId: $chat->departmentReassignedByUserId,
        ));

        $threshold = (float) config('features.ai_department_confidence_threshold', 0.7);
        if ($confidence >= $threshold && $chat->departmentId !== $suggestedId) {
            try {
                $afterMove = $changeChatDepartment->runAsSystem($this->chatId, $suggestedId);
                $chat = $chatRepository->persist(new Chat(
                    id: $afterMove->id,
                    sourceId: $afterMove->sourceId,
                    departmentId: $afterMove->departmentId,
                    externalUserId: $afterMove->externalUserId,
                    userMetadata: $afterMove->userMetadata,
                    status: $afterMove->status,
                    assignedTo: $afterMove->assignedTo,
                    topic: $afterMove->topic,
                    aiSuggestedDepartmentId: $afterMove->aiSuggestedDepartmentId,
                    aiDepartmentConfidence: $afterMove->aiDepartmentConfidence,
                    aiDepartmentAssignedAt: \DateTimeImmutable::createFromInterface(now()),
                    departmentReassignedByUserId: $afterMove->departmentReassignedByUserId,
                ));
            } catch (\Throwable $e) {
                Log::warning('AI dept auto-assign failed', [
                    'chat_id' => $this->chatId,
                    'suggested' => $suggestedId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $chat;
    }
}
