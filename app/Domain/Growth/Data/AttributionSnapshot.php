<?php

declare(strict_types=1);

namespace App\Domain\Growth\Data;

use App\Domain\Growth\Enums\AttributionStage;
use LogicException;

final readonly class AttributionSnapshot
{
    /**
     * @param  list<AttributionBucket>  $buckets
     * @param  list<string>  $limitations
     */
    public function __construct(
        public array $buckets,
        public array $limitations,
    ) {
        $this->prepared = $this->findBucket(AttributionStage::Prepared);
        $this->influenced = $this->findBucket(AttributionStage::Influenced);
        $this->verified = $this->findBucket(AttributionStage::Verified);
    }

    public AttributionBucket $prepared;

    public AttributionBucket $influenced;

    public AttributionBucket $verified;

    public function bucket(AttributionStage $stage): AttributionBucket
    {
        return match ($stage) {
            AttributionStage::Prepared => $this->prepared,
            AttributionStage::Influenced => $this->influenced,
            AttributionStage::Verified => $this->verified,
        };
    }

    private function findBucket(AttributionStage $stage): AttributionBucket
    {
        foreach ($this->buckets as $bucket) {
            if ($bucket->stage === $stage) {
                return $bucket;
            }
        }

        throw new LogicException("Missing attribution bucket [{$stage->value}].");
    }
}
