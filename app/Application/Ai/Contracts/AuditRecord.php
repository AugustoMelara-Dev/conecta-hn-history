<?php

declare(strict_types=1);

namespace App\Application\Ai\Contracts;

/**
 * AuditRecord - Inmutable log de una ejecución de assistant.
 */
final readonly class AuditRecord
{
    public function __construct(
        public string $assistantKey,
        public string $tenantId,
        public ?string $userId,
        public array $input,
        public AssistantResult $result,
        public \DateTimeImmutable $timestamp,
    ) {}

    public function toArray(): array
    {
        return [
            'assistant_key' => $this->assistantKey,
            'tenant_id' => $this->tenantId,
            'user_id' => $this->userId,
            'input' => $this->input,
            'result' => $this->result->toArray(),
            'timestamp' => $this->timestamp->format(\DateTimeInterface::ATOM),
        ];
    }
}


