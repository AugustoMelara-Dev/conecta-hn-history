<?php

declare(strict_types=1);

namespace App\Application\Reporting;

use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\ValueObjects\ReportRow;

/**
 * ReportComparison - Compara datos actuales vs período previo.
 */
class ReportComparison
{
    /**
     * @return array<int, array{current: ReportRow, change_pct: ?float, trend: string}>
     */
    public function compare(ReportData $current, ReportData $previous): array
    {
        $rows = [];

        foreach ($current->rows as $idx => $currentRow) {
            $previousRow = $previous->rows[$idx] ?? null;

            $previousValue = $previousRow?->value ?? 0;
            $changePct = null;
            $trend = 'flat';

            if ($previousValue != 0 && is_numeric($currentRow->value) && is_numeric($previousValue)) {
                $changePct = (($currentRow->value - $previousValue) / abs($previousValue)) * 100;
                if ($changePct > 1) {
                    $trend = 'up';
                } elseif ($changePct < -1) {
                    $trend = 'down';
                }
            }

            $rows[] = [
                'current' => $currentRow,
                'change_pct' => $changePct !== null ? round($changePct, 2) : null,
                'trend' => $trend,
            ];
        }

        return $rows;
    }

    public function trendIcon(string $trend): string
    {
        return match ($trend) {
            'up' => 'heroicon-o-arrow-trending-up',
            'down' => 'heroicon-o-arrow-trending-down',
            default => 'heroicon-o-minus',
        };
    }

    public function trendColor(string $trend): string
    {
        return match ($trend) {
            'up' => 'success',
            'down' => 'danger',
            default => 'gray',
        };
    }
}
