<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Application\Communication\Action\ChangeChatDepartment;
use App\Application\Communication\Action\ProcessClientAiAutoreply;
use App\Contracts\Ai\AiProviderInterface;
use App\Domains\Communication\Repository\ChatRepositoryInterface;
use App\Domains\Integration\Entity\Department;
use App\Domains\Integration\Repository\DepartmentRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Support\ChatAiKickoffCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Follow-up AI autoreply after a topic is already set (client messages 3+ or multi-turn).
 */
final class ProcessAiAutoreplyTurnJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
        ProcessClientAiAutoreply $processClientAiAutoreply,
    ): void {
        $lock = Cache::lock('chat-kickoff:'.$this->chatId, 30);
        $lock->block(25, function () use ($chatRepository, $ai, $departmentRepository, $changeChatDepartment, $processClientAiAutoreply): void {
            $this->doTurn($chatRepository, $ai, $departmentRepository, $changeChatDepartment, $processClientAiAutoreply);
        });
    }

    private function doTurn(
        ChatRepositoryInterface $chatRepository,
        AiProviderInterface $ai,
        DepartmentRepositoryInterface $departmentRepository,
        ChangeChatDepartment $changeChatDepartment,
        ProcessClientAiAutoreply $processClientAiAutoreply,
    ): void {
        $chat = $chatRepository->findById($this->chatId);
        if ($chat === null) {
            return;
        }
        if ($chat->topic === null || $chat->topic === '') {
            return;
        }
        if (! config('features.ai_client_autoreply', true)) {
            return;
        }

        $lines = MessageModel::query()
            ->where('chat_id', $this->chatId)
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->reverse();
        $parts = [];
        foreach ($lines as $m) {
            $t = trim((string) $m->text);
            if ($t === '') {
                continue;
            }
            $role = match ((string) $m->sender_type) {
                'client' => 'клиент',
                'system' => 'ассистент',
                'moderator' => 'модератор',
                default => (string) $m->sender_type,
            };
            $parts[] = $role.': '.$t;
        }
        $transcript = Str::limit(implode("\n", $parts), 4000, '');
        if ($transcript === '') {
            return;
        }

        $departmentsForKickoff = [];
        if (config('features.ai_department_assignment')) {
            $filtered = array_values(array_filter(
                $departmentRepository->listBySourceId($chat->sourceId),
                static fn (Department $d) => $d->isActive && $d->aiEnabled,
            ));
            $departmentsForKickoff = array_slice($filtered, 0, self::KICKOFF_DEPARTMENTS_MAX);
        }
        $kickoff = $ai->generateKickoffFromClientMessages($transcript, $departmentsForKickoff);

        $sealMessageId = (int) MessageModel::query()->where('chat_id', $this->chatId)->max('id');
        if ($sealMessageId > 0) {
            ChatAiKickoffCache::put($this->chatId, $sealMessageId, $kickoff);
        }

        if (config('features.ai_department_assignment')
            && $kickoff->suggestedDepartmentId !== null
            && $kickoff->confidence !== null) {
            $validIds = array_map(static fn (Department $d) => $d->id, $departmentsForKickoff);
            if (in_array($kickoff->suggestedDepartmentId, $validIds, true)) {
                $this->applyDepartmentIfNeeded(
                    $chat,
                    $kickoff,
                    $chatRepository,
                    $changeChatDepartment,
                );
            }
        }

        $processClientAiAutoreply->fromKickoff($this->chatId, $kickoff);
    }

    private function applyDepartmentIfNeeded(
        \App\Domains\Communication\Entity\Chat $chat,
        \App\Application\Ai\Dto\AiChatKickoffDto $kickoff,
        ChatRepositoryInterface $chatRepository,
        ChangeChatDepartment $changeChatDepartment,
    ): void {
        $suggestedId = $kickoff->suggestedDepartmentId;
        $confidence = $kickoff->confidence;
        if ($suggestedId === null || $confidence === null) {
            return;
        }
        $threshold = (float) config('features.ai_department_confidence_threshold', 0.7);
        if ($confidence < $threshold || $chat->departmentId === $suggestedId) {
            return;
        }
        try {
            $changeChatDepartment->runAsSystem($this->chatId, $suggestedId);
        } catch (\Throwable) {
        }
    }
}
