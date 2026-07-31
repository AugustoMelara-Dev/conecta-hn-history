<?php

declare(strict_types=1);

namespace App\Application\Ai\Conversation;

use App\Application\Ai\DTOs\AiExecutionResult;
use App\Application\Ai\Registry\AiRuntimeSelection;

interface ConversationStore
{
    public function claim(AcceptConversationMessage $command): ConversationMessageClaim;

    /**
     * @param  array<string, mixed>  $answer
     * @param  list<array<string, mixed>>  $evidence
     */
    public function completeAssistantMessage(
        string $tenantId,
        string $conversationId,
        string $requestMessageId,
        AiRuntimeSelection $selection,
        ?AiExecutionResult $result,
        array $answer,
        array $evidence,
    ): ConversationMessage;

    public function failAssistantMessage(
        string $tenantId,
        string $conversationId,
        string $requestMessageId,
        string $publicCode,
        bool $retryable,
    ): void;
}
