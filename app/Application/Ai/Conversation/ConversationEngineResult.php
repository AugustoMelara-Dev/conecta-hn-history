<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation;

use App\Application\Ai\DTOs\AiExecutionResult;

final readonly class ConversationEngineResult
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>  $evidence
     */
    public function __construct(
        public array $payload,
        public array $evidence,
        public ?AiExecutionResult $execution,
        public bool $fallback,
        public ?string $handoffTrigger = null,
    ) {}
}
