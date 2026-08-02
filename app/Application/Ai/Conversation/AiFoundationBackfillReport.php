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
    ) {}

    /** @return array<string, int> */
    public function toArray(): array
    {
        return [
            'registry_linked' => $this->registryLinked,
            'conversation_linked' => $this->conversationLinked,
            'legacy_unresolved' => $this->legacyUnresolved,
            'retention_classified' => $this->retentionClassified,
            'gaps' => $this->gaps,
            'conflicts' => $this->conflicts,
        ];
    }
}
