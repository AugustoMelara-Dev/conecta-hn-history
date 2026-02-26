<?php

declare(strict_types=1);

namespace App\Application\Shared\Traits;

use App\Application\Shared\Contracts\DataScrubberInterface;

/**
 * LogSanitizer - Trait for easy PII scrubbing
 *
 * Requires the consuming class to inject DataScrubberInterface
 * and pass it explicitly to sanitizePayload().
 */
trait LogSanitizer
{
    protected function sanitizePayload(array|object $payload, DataScrubberInterface $scrubber): array
    {
        return $scrubber->scrub($payload);
    }
}
