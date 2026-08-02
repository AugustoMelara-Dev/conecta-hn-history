<?php

declare(strict_types=1);

namespace App\Infrastructure\Privacy\Dovra;

use App\Domain\Privacy\Dovra\SensitivePayloadRedactor;
use App\Infrastructure\Logging\SensitiveDataScrubber;

final class LegacySensitivePayloadRedactor implements SensitivePayloadRedactor
{
    public function __construct(
        private readonly SensitiveDataScrubber $scrubber,
    ) {}

    public function scrub(array $payload): array
    {
        return $this->scrubber->scrub($payload);
    }
}
