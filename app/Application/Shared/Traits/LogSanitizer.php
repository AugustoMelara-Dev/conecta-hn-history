<?php

declare(strict_types=1);

namespace App\Application\Shared\Traits;

use App\Infrastructure\Logging\SensitiveDataScrubber;

/**
 * LogSanitizer - Trait for easy PII scrubbing
 */
trait LogSanitizer
{
    protected function sanitizePayload(array|object $payload): array
    {
        /** @var SensitiveDataScrubber $scrubber */
        $scrubber = app(SensitiveDataScrubber::class);

        return $scrubber->scrub($payload);
    }
}
