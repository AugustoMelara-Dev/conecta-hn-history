<?php

declare(strict_types=1);

namespace App\Application\Ai\Registry;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class AiRuntimeSelection
{
    /** @param list<string> $requiredCapabilities */
    public function __construct(
        public string $providerVersionId,
        public string $modelVersionId,
        public string $agentVersionId,
        public string $promptVersionId,
        public string $providerKey,
        public string $adapterKey,
        public string $secretReference,
        public string $modelKey,
        public string $systemPrompt,
        public string $outputSchema,
        public array $requiredCapabilities,
        public int $maxOutputTokens,
        public string $releaseDecisionId,
    ) {
        foreach ([
            $providerVersionId,
            $modelVersionId,
            $agentVersionId,
            $promptVersionId,
            $releaseDecisionId,
        ] as $id) {
            if (! Str::isUuid($id)) {
                throw new InvalidArgumentException('AI runtime selection version IDs must be UUIDs.');
            }
        }

        if (
            trim($providerKey) === ''
            || trim($adapterKey) === ''
            || trim($secretReference) === ''
            || trim($modelKey) === ''
            || trim($systemPrompt) === ''
            || ! preg_match('/^[a-z][a-z0-9_.-]{2,99}$/', $outputSchema)
        ) {
            throw new InvalidArgumentException('AI runtime selection is incomplete.');
        }

        if (
            ! array_is_list($requiredCapabilities)
            || $requiredCapabilities === []
            || count(array_unique($requiredCapabilities)) !== count($requiredCapabilities)
            || array_filter(
                $requiredCapabilities,
                static fn (mixed $capability): bool => is_string($capability) && trim($capability) !== '',
            ) !== $requiredCapabilities
        ) {
            throw new InvalidArgumentException('AI runtime capabilities are invalid.');
        }

        if ($maxOutputTokens < 1 || $maxOutputTokens > 4096) {
            throw new InvalidArgumentException('AI runtime output token limit is invalid.');
        }
    }
}
