<?php

declare(strict_types=1);

namespace App\Domain\Growth\Enums;

enum EvidenceLabel: string
{
    case Hypothesis = 'hypothesis';
    case Implemented = 'implemented';
    case RepositoryProven = 'repository-proven';
    case ExternalInput = 'external-input';
}
