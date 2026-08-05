<?php

declare(strict_types=1);

namespace App\Application\Ai\DTOs;

use InvalidArgumentException;

final readonly class AiExecutionClaim
{
    private function __construct(
        public ?string $claimToken,
        public ?AiExecutionResult $replay,
    ) {
        if (($claimToken === null) === ($replay === null)) {
            throw new InvalidArgumentException('AI execution claim must be active or replayable.');
        }
    }

    public static function active(string $claimToken): self
    {
        if (! preg_match('/^[a-f0-9]{64}$/', $claimToken)) {
            throw new InvalidArgumentException('AI execution claim token is invalid.');
        }

        return new self($claimToken, null);
    }

    public static function replay(AiExecutionResult $result): self
    {
        return new self(null, $result);
    }
}
