<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation;

use App\Application\Ai\DTOs\AiExecutionResult;
use InvalidArgumentException;

final readonly class ConversationEngineResult
{
    private const array FAILURE_CODES = [
        'ai_response_invalid',
        'ai_evidence_insufficient',
        'ai_handoff_required',
    ];

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
        public ?string $failureCode = null,
    ) {
        if ((! $fallback && ($execution === null || $failureCode !== null))
            || ($failureCode !== null && ! in_array($failureCode, self::FAILURE_CODES, true))
            || ($fallback && $execution !== null && $failureCode === null)) {
            throw new InvalidArgumentException('AI conversation engine result is invalid.');
        }
    }
}
