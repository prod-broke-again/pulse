<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Ai\Dto\AiSuggestedReplyDto;
use App\Contracts\Ai\AiProviderInterface;
use App\Infrastructure\Persistence\Eloquent\ChatModel;
use App\Infrastructure\Persistence\Eloquent\MessageModel;
use App\Support\ChatAiKickoffCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Gate;

final class ChatAiController extends Controller
{
    public function summary(ChatModel $chat, AiProviderInterface $ai): JsonResponse
    {
        Gate::authorize('view', $chat);

        $cached = ChatAiKickoffCache::getIfFresh($chat->id);
        if ($cached !== null) {
            $summary = trim((string) ($cached['summary'] ?? ''));
            $intentRaw = $cached['intent_tag'] ?? null;
            $intent = is_string($intentRaw) ? trim($intentRaw) : '';
            $intentTag = $intent !== '' ? $intent : null;
            if ($summary !== '' || $intentTag !== null) {
                return response()->json([
                    'data' => [
                        'summary' => $summary,
                        'intent_tag' => $intentTag,
                    ],
                ]);
            }
        }

        $context = $this->buildContext($chat);

        $dto = $ai->summarizeThread($context);

        return response()->json([
            'data' => [
                'summary' => $dto->summary,
                'intent_tag' => $dto->intentTag,
            ],
        ]);
    }

    public function suggestions(ChatModel $chat, AiProviderInterface $ai): JsonResponse
    {
        Gate::authorize('view', $chat);

        $cached = ChatAiKickoffCache::getIfFresh($chat->id);
        if ($cached !== null && ($cached['replies'] ?? []) !== []) {
            return response()->json([
                'data' => [
                    'replies' => $cached['replies'],
                ],
            ]);
        }

        $context = $this->buildContext($chat);
        $replies = $ai->suggestReplies($context);

        return response()->json([
            'data' => [
                'replies' => array_map(
                    static fn (AiSuggestedReplyDto $r) => ['id' => $r->id, 'text' => $r->text],
                    $replies,
                ),
            ],
        ]);
    }

    private function buildContext(ChatModel $chat): string
    {
        $lines = MessageModel::query()
            ->where('chat_id', $chat->id)
            ->orderBy('id')
            ->limit(80)
            ->get(['sender_type', 'text']);

        return $lines->map(fn ($m) => $m->sender_type.': '.$m->text)->implode("\n");
    }
}
