<?php

declare(strict_types=1);

namespace App\Application\Ai\DTOs;

use App\Application\Ai\Registry\AiRuntimeSelection;
use InvalidArgumentException;

final readonly class AiExecutionRequest
{
    public string $systemPrompt;

    public string $outputSchema;

    public int $maxOutputTokens;

    /** @param list<string> $requestedTools */
    public function __construct(
        public AiExecutionContext $context,
        public AiRuntimeSelection $selection,
        public string $operation,
        public string $userInput,
        public array $requestedTools = [],
    ) {
        $this->systemPrompt = $selection->systemPrompt;
        $this->outputSchema = $selection->outputSchema;
        $this->maxOutputTokens = $selection->maxOutputTokens;

        if (! preg_match('/^[a-z][a-z0-9_.-]{1,99}$/', $operation)) {
            throw new InvalidArgumentException('AI operation is invalid.');
        }

        if (trim($this->systemPrompt) === '' || trim($userInput) === '') {
            throw new InvalidArgumentException('AI prompts cannot be empty.');
        }

        if (! preg_match('/^[a-z][a-z0-9_.-]{2,99}$/', $this->outputSchema)) {
            throw new InvalidArgumentException('AI output schema identifier is invalid.');
        }

    }
}
