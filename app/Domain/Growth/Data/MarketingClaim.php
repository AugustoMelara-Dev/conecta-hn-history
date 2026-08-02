<?php

declare(strict_types=1);

namespace App\Domain\Growth\Data;

use App\Domain\Growth\Enums\EvidenceLabel;

final readonly class MarketingClaim
{
    /**
     * @param  list<string>  $evidencePaths
     */
    public function __construct(
        public string $title,
        public string $statement,
        public EvidenceLabel $label,
        public array $evidencePaths,
    ) {}
}
