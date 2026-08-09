<?php

declare(strict_types=1);

namespace App\Filament\App\Widgets\Reports;

use App\Application\Reporting\ReportExecutor;
use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;
use Filament\Facades\Filament;

final class ReportWidgetData
{
    public static function report(ReportKey $key): ?ReportData
    {
        $tenantId = Filament::getTenant()?->getKey();
        if (! is_string($tenantId) || $tenantId === '') {
            return null;
        }

        return app(ReportExecutor::class)->execute($key, $tenantId, ReportRange::last30Days());
    }

    /** @return array{datasets: array<int, array<string, mixed>>, labels: array<int, string>} */
    public static function chart(
        ReportKey $key,
        string $fallbackLabel,
        string $color = '#4F46E5',
    ): array {
        $data = self::report($key);
        if ($data === null || ($data->metadata['status'] ?? null) === 'unavailable') {
            return ['datasets' => [], 'labels' => []];
        }

        if ($data->series !== []) {
            return [
                'datasets' => array_map(static fn ($series): array => [
                    'label' => $series->name,
                    'data' => $series->values,
                    'borderColor' => $series->color ?? $color,
                    'backgroundColor' => $series->color ?? $color,
                ], $data->series),
                'labels' => $data->series[0]->labels,
            ];
        }

        return [
            'datasets' => [[
                'label' => $fallbackLabel,
                'data' => array_map(static fn ($row): float|int|string => $row->value, $data->rows),
                'backgroundColor' => $color,
                'borderColor' => $color,
            ]],
            'labels' => array_map(static fn ($row): string => $row->label, $data->rows),
        ];
    }
}
