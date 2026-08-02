<?php

declare(strict_types=1);

namespace App\Application\Ai\Handoff;

final readonly class AiHandoffContinuationBlock
{
    public const string MESSAGE = 'A human follow-up has been requested.';

    public function __construct(
        public string $handoffId,
        public string $status,
    ) {}

    /** @return array{id:string,status:string,message:string} */
    public function response(): array
    {
        return [
            'id' => $this->handoffId,
            'status' => $this->status,
            'message' => self::MESSAGE,
        ];
    }
}
