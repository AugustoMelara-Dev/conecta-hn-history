<?php

declare(strict_types=1);

namespace App\Application\Ai\Assistants;

use App\Application\Ai\Contracts\AssistantResult;
use App\Application\Ai\DTOs\AssistantExecutionContext;

class EmailReplyAssistant extends BaseAssistant
{
    public function key(): string
    {
        return 'email_reply';
    }

    public function name(): string
    {
        return 'Email Reply Draft';
    }

    public function description(): string
    {
        return 'Genera reply a mensaje de inbox.';
    }

    public function estimateCost(): float
    {
        return 0.04;
    }

    public function category(): string
    {
        return 'Communications';
    }

    public function execute(array $input, ?AssistantExecutionContext $context = null): AssistantResult
    {
        if (! isset($input['inbox_message_id'])) {
            return AssistantResult::failure('Missing inbox_message_id');
        }

        return $this->executeCanonical($input, $context);
    }
}
