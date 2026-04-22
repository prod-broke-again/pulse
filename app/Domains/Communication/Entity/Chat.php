<?php

declare(strict_types=1);

namespace App\Domains\Communication\Entity;

use App\Domains\Communication\ValueObject\ChatStatus;

final readonly class Chat
{
    public function __construct(
        public int $id,
        public int $sourceId,
        public int $departmentId,
        public string $externalUserId,
        /** @var array<string, mixed> */
        public array $userMetadata,
        public ChatStatus $status,
        public ?int $assignedTo,
        public ?string $topic = null,
        public ?int $aiSuggestedDepartmentId = null,
        public ?float $aiDepartmentConfidence = null,
        public ?\DateTimeImmutable $aiDepartmentAssignedAt = null,
        public ?int $departmentReassignedByUserId = null,
        public ?string $externalBusinessConnectionId = null,
        public ?\DateTimeImmutable $lastActivityAt = null,
        public ?int $previousChatId = null,
        public int $aiAutoRepliesCount = 0,
        public bool $awaitingClientFeedback = false,
    ) {}

    /**
     * @param  array<string, mixed>  $overrides
     */
    public function withOverrides(array $overrides): self
    {
        $status = $this->status;
        if (\array_key_exists('status', $overrides)) {
            $s = $overrides['status'];
            $status = $s instanceof ChatStatus ? $s : ChatStatus::from((string) $s);
        }

        return new self(
            id: (int) ($overrides['id'] ?? $this->id),
            sourceId: (int) ($overrides['sourceId'] ?? $this->sourceId),
            departmentId: (int) ($overrides['departmentId'] ?? $this->departmentId),
            externalUserId: (string) ($overrides['externalUserId'] ?? $this->externalUserId),
            userMetadata: (array) ($overrides['userMetadata'] ?? $this->userMetadata),
            status: $status,
            assignedTo: \array_key_exists('assignedTo', $overrides) ? $overrides['assignedTo'] : $this->assignedTo,
            topic: \array_key_exists('topic', $overrides) ? $overrides['topic'] : $this->topic,
            aiSuggestedDepartmentId: \array_key_exists('aiSuggestedDepartmentId', $overrides) ? $overrides['aiSuggestedDepartmentId'] : $this->aiSuggestedDepartmentId,
            aiDepartmentConfidence: \array_key_exists('aiDepartmentConfidence', $overrides) ? $overrides['aiDepartmentConfidence'] : $this->aiDepartmentConfidence,
            aiDepartmentAssignedAt: \array_key_exists('aiDepartmentAssignedAt', $overrides) ? $overrides['aiDepartmentAssignedAt'] : $this->aiDepartmentAssignedAt,
            departmentReassignedByUserId: \array_key_exists('departmentReassignedByUserId', $overrides) ? $overrides['departmentReassignedByUserId'] : $this->departmentReassignedByUserId,
            externalBusinessConnectionId: \array_key_exists('externalBusinessConnectionId', $overrides) ? $overrides['externalBusinessConnectionId'] : $this->externalBusinessConnectionId,
            lastActivityAt: \array_key_exists('lastActivityAt', $overrides) ? $overrides['lastActivityAt'] : $this->lastActivityAt,
            previousChatId: \array_key_exists('previousChatId', $overrides) ? $overrides['previousChatId'] : $this->previousChatId,
            aiAutoRepliesCount: (int) ($overrides['aiAutoRepliesCount'] ?? $this->aiAutoRepliesCount),
            awaitingClientFeedback: (bool) ($overrides['awaitingClientFeedback'] ?? $this->awaitingClientFeedback),
        );
    }
}
