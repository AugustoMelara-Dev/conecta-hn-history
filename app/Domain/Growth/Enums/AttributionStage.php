<?php

declare(strict_types=1);

namespace App\Domain\Growth\Enums;

enum AttributionStage: string
{
    case Prepared = 'prepared';
    case Influenced = 'influenced';
    case Verified = 'verified';
}
