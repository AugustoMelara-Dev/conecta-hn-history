<?php

declare(strict_types=1);

namespace App\Application\Ai\Grounding;

final readonly class GroundedAnswer
{
    /**
     * @param  array<string, mixed>  $payload
     * @param  list<EvidenceSnapshot>  $selectedEvidence
     */
    public function __construct(
        public array $payload,
        public array $selectedEvidence,
    ) {}
}
