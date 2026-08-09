<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\ValueObjects\ReportRange;

final class ReportAvailability
{
    /**
     * @param  array<int, string>  $requiredSources
     */
    public static function unavailable(
        ReportRange $range,
        string $reason,
        array $requiredSources,
    ): ReportData {
        return new ReportData(
            rows: [],
            series: [],
            summary: [
                'available' => false,
                'reason' => $reason,
            ],
            metadata: [
                'status' => 'unavailable',
                'reason' => $reason,
                'required_sources' => $requiredSources,
                'range' => $range->toArray(),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    public static function metadata(ReportRange $range, array $metadata = []): array
    {
        return [
            'status' => 'available',
            'range' => $range->toArray(),
            ...$metadata,
        ];
    }
}
