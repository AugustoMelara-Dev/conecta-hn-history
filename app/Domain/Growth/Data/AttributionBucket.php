<?php

declare(strict_types=1);

namespace App\Domain\Growth\Data;

use App\Domain\Growth\Enums\AttributionStage;
use LogicException;

final readonly class AttributionBucket
{
    /**
     * @param  array<string, int>  $revenueByCurrencyMinor
     */
    public function __construct(
        public AttributionStage $stage,
        public int $count,
        public array $revenueByCurrencyMinor,
        public string $definition,
        public string $evidenceSource,
    ) {
        if ($this->count < 0) {
            throw new LogicException('Attribution counts cannot be negative.');
        }

        $expectedEvidenceSources = [
            AttributionStage::Prepared->value => 'interactions.type',
            AttributionStage::Influenced->value => 'interactions.utm_*',
            AttributionStage::Verified->value => 'verified_commercial_outcomes',
        ];

        if ($this->evidenceSource !== $expectedEvidenceSources[$this->stage->value]) {
            throw new LogicException("Attribution stage [{$this->stage->value}] has an invalid evidence source.");
        }

        if ($this->stage !== AttributionStage::Verified && $this->revenueByCurrencyMinor !== []) {
            throw new LogicException('Only verified attribution may carry revenue.');
        }
    }
}
