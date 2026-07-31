<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation;

use App\Exceptions\Ai\AiCircuitOpenException;
use App\Exceptions\Ai\AiConversationConflictException;
use App\Exceptions\Ai\AiExecutionDeniedException;
use App\Exceptions\Ai\AiExecutionFailedException;
use App\Exceptions\Ai\AiMalformedResponseException;
use App\Exceptions\Ai\AiProviderResourceLimitException;
use App\Exceptions\Ai\AiProviderUnavailableException;
use App\Exceptions\InsufficientAiCreditsException;
use InvalidArgumentException;
use JsonException;
use Throwable;

final class ConversationFailureMapper
{
    /** @return array{code:string,retryable:bool} */
    public function map(Throwable $exception): array
    {
        if ($exception instanceof AiConversationConflictException) {
            return ['code' => 'ai_request_conflict', 'retryable' => false];
        }

        if ($exception instanceof InsufficientAiCreditsException) {
            return ['code' => 'ai_budget_exhausted', 'retryable' => false];
        }

        if ($exception instanceof AiProviderResourceLimitException) {
            return ['code' => 'ai_rate_limited', 'retryable' => true];
        }

        if ($exception instanceof AiMalformedResponseException || $exception instanceof JsonException) {
            return ['code' => 'ai_response_invalid', 'retryable' => false];
        }

        if ($exception instanceof AiExecutionDeniedException) {
            return $this->denial($exception->reason);
        }

        if ($exception instanceof AiCircuitOpenException
            || $exception instanceof AiProviderUnavailableException
            || $exception instanceof AiExecutionFailedException) {
            return ['code' => 'ai_temporarily_unavailable', 'retryable' => true];
        }

        throw new InvalidArgumentException('Unsupported AI conversation failure type.');
    }

    /** @return array{code:string,retryable:bool} */
    private function denial(string $reason): array
    {
        return match ($reason) {
            'idempotency_conflict' => ['code' => 'ai_request_conflict', 'retryable' => false],
            'budget', 'budget_reservation_failed', 'insufficient_credits' => [
                'code' => 'ai_budget_exhausted',
                'retryable' => false,
            ],
            'rate_limit', 'rate_limited' => ['code' => 'ai_rate_limited', 'retryable' => true],
            'ai_access_denied', 'tenant_context' => ['code' => 'ai_access_denied', 'retryable' => false],
            'prompt_bounds' => ['code' => 'ai_response_invalid', 'retryable' => false],
            'execution_in_progress' => ['code' => 'ai_temporarily_unavailable', 'retryable' => true],
            default => ['code' => 'ai_configuration_unavailable', 'retryable' => false],
        };
    }
}
