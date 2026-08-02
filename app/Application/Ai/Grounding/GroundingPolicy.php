<?php

declare(strict_types=1);

namespace App\Application\Ai\Grounding;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class GroundingPolicy
{
    /** @param list<string> $allowedClaimTypes */
    public function __construct(
        public string $tenantId,
        public string $visibility,
        public string $currency,
        public CarbonImmutable $freshnessCutoff,
        public array $allowedClaimTypes,
    ) {
        if (! Str::isUuid($tenantId)
            || ! in_array($visibility, ['public', 'private'], true)
            || preg_match('/^[A-Z]{3}$/', $currency) !== 1
            || ! array_is_list($allowedClaimTypes) || $allowedClaimTypes === []) {
            throw new InvalidArgumentException('AI grounding policy is invalid.');
        }
        foreach ($allowedClaimTypes as $type) {
            if (! is_string($type) || preg_match('/^[a-z][a-z0-9_]{0,31}$/', $type) !== 1) {
                throw new InvalidArgumentException('AI grounding policy is invalid.');
            }
        }
    }
}
