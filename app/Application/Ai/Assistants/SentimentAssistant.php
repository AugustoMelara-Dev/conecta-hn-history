<?php

declare(strict_types=1);

namespace App\Application\Ai\Assistants;

use App\Application\Ai\Contracts\AssistantResult;
use App\Application\Ai\DTOs\AssistantExecutionContext;

class SentimentAssistant extends BaseAssistant
{
    public function key(): string
    {
        return 'sentiment';
    }

    public function name(): string
    {
        return 'Sentiment Analysis';
    }

    public function description(): string
    {
        return 'Analiza sentiment + risk score.';
    }

    public function estimateCost(): float
    {
        return 0.01;
    }

    public function category(): string
    {
        return 'Communications';
    }

    public function execute(array $input, ?AssistantExecutionContext $context = null): AssistantResult
    {
        if (! isset($input['message_id'])) {
            return AssistantResult::failure('Missing message_id');
        }

        return $this->executeCanonical($input, $context);
    }
}
