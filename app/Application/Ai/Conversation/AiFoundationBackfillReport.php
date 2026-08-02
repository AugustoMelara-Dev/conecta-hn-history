<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation;

final readonly class AiFoundationBackfillReport
{
    public function __construct(
        public int $registryLinked,
        public int $conversationLinked,
        public int $legacyUnresolved,
        public int $retentionClassified,
        public int $gaps,
        public int $conflicts,
        public string $phase = 'backfill',
        public ?string $batchChecksum = null,
        public ?string $nextCursor = null,
        public ?string $conversationNextCursor = null,
        public bool $hasMore = false,
        public bool $validated = false,
        public string $contractStatus = 'deferred',
        /** @var array{executions:?array{created_at:string,id:string,empty?:bool},conversations:?array{created_at:string,id:string,empty?:bool}}|null */
        public ?array $sourceSnapshot = null,
    ) {}

    /** @return array<string, int|string|bool|array<string, mixed>|null> */
    public function toArray(): array
    {
        return [
            'registry_linked' => $this->registryLinked,
            'conversation_linked' => $this->conversationLinked,
            'legacy_unresolved' => $this->legacyUnresolved,
            'retention_classified' => $this->retentionClassified,
            'gaps' => $this->gaps,
            'conflicts' => $this->conflicts,
            'phase' => $this->phase,
            'batch_checksum' => $this->batchChecksum,
            'next_cursor' => $this->nextCursor,
            'conversation_next_cursor' => $this->conversationNextCursor,
            'has_more' => $this->hasMore,
            'validated' => $this->validated,
            'contract_status' => $this->contractStatus,
            'source_snapshot' => $this->sourceSnapshot,
        ];
    }
}
