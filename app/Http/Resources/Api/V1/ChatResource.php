<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1;

use App\Domains\Integration\ValueObject\SourceType;
use App\Models\User;
use App\Support\DepartmentIcons;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** @mixin \App\Infrastructure\Persistence\Eloquent\ChatModel */
final class ChatResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $channel = null;
        $channelLabel = null;
        if ($this->relationLoaded('source') && $this->source !== null) {
            $channel = $this->source->type;
            try {
                $channelLabel = SourceType::from($this->source->type)->label();
            } catch (\ValueError) {
                $channelLabel = $this->source->type;
            }
        }

        $categoryCode = null;
        $categoryLabel = null;
        $aiEnabled = null;
        if ($this->relationLoaded('department') && $this->department !== null) {
            $cat = $this->department->category;
            $categoryCode = $cat instanceof \BackedEnum ? $cat->value : (string) $cat;
            $categoryLabel = is_object($cat) && method_exists($cat, 'label') ? $cat->label() : (string) $categoryCode;
            $aiEnabled = (bool) $this->department->ai_enabled;
        }

        $latest = $this->relationLoaded('latestMessage') ? $this->latestMessage : null;
        $preview = $latest?->text;
        if ($preview !== null) {
            $preview = Str::limit($preview, 500);
        }

        return [
            'id' => $this->id,
            'source_id' => $this->source_id,
            'department_id' => $this->department_id,
            'ai_suggested_department_id' => $this->ai_suggested_department_id,
            'ai_department_confidence' => $this->ai_department_confidence !== null
                ? (float) $this->ai_department_confidence
                : null,
            'ai_department_assigned_at' => $this->ai_department_assigned_at?->toIso8601String(),
            'external_user_id' => $this->external_user_id,
            'user_metadata' => $this->user_metadata,
            'status' => $this->status,
            'assigned_to' => $this->assigned_to,
            'topic' => $this->topic,
            'category_code' => $categoryCode,
            'category_label' => $categoryLabel,
            'ai_enabled' => $aiEnabled,
            'ai_badge' => $aiEnabled,
            'channel' => $channel,
            'channel_label' => $channelLabel,
            'unread_count' => (int) ($this->unread_count ?? 0),
            'muted_until' => $this->mutedUntilForUser($request->user()),
            'last_message_preview' => $preview,
            'last_message_at' => $latest?->created_at?->toIso8601String(),
            'source' => $this->whenLoaded('source', fn () => [
                'id' => $this->source->id,
                'name' => $this->source->name,
                'type' => $this->source->type,
            ]),
            'department' => $this->whenLoaded('department', function () {
                $cat = $this->department->category;

                return [
                    'id' => $this->department->id,
                    'name' => $this->department->name,
                    'category' => $cat instanceof \BackedEnum ? $cat->value : (string) $cat,
                    'ai_enabled' => (bool) $this->department->ai_enabled,
                    'icon' => DepartmentIcons::normalize($this->department->icon),
                ];
            }),
            'assignee' => $this->whenLoaded('assignee', fn () => $this->assignee ? [
                'id' => $this->assignee->id,
                'name' => $this->assignee->name,
                'avatar_url' => $this->assignee->avatar_url,
            ] : null),
            'latest_message' => $this->whenLoaded('latestMessage', fn () => $this->latestMessage ? [
                'id' => $this->latestMessage->id,
                'text' => $this->latestMessage->text,
                'sender_type' => $this->latestMessage->sender_type,
                'created_at' => $this->latestMessage->created_at?->toIso8601String(),
            ] : null),
            'is_urgent' => $this->isUrgent(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    private function mutedUntilForUser(?User $user): ?string
    {
        if ($user === null) {
            return null;
        }
        if (! $this->relationLoaded('userReadStates')) {
            return null;
        }
        $state = $this->userReadStates->firstWhere('user_id', $user->id);

        return $state?->muted_until?->toIso8601String();
    }
}
