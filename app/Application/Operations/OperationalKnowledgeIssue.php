<?php

declare(strict_types=1);

namespace App\Application\Operations;

use Carbon\CarbonImmutable;

final readonly class OperationalKnowledgeIssue
{
    public function __construct(
        public string $code,
        public string $severity,
        public string $evidenceType,
        public string $evidenceId,
        public string $evidenceLabel,
        public string $message,
        public string $remediationUrl,
        public CarbonImmutable $checkedAt,
    ) {}
}
