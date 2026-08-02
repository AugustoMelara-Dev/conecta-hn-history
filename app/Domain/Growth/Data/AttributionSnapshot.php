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
    ) {}

    public function bucket(AttributionStage $stage): AttributionBucket
    {
        foreach ($this->buckets as $bucket) {
            if ($bucket->stage === $stage) {
                return $bucket;
            }
        }

        throw new LogicException("Missing attribution bucket [{$stage->value}].");
    }
}
