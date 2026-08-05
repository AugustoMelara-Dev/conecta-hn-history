<?php

declare(strict_types=1);

namespace App\Infrastructure\Analytics;

use App\Domain\Analytics\Contracts\AnalyticsReader;
use App\Models\TenantAnalytic;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * EloquentAnalyticsReader - Eloquent Implementation of AnalyticsReader
 *
 * ADAPTER: Implements the Domain contract using Eloquent ORM.
 * MULTI-TENANT: All queries explicitly scoped by tenant_id.
 */
final class EloquentAnalyticsReader implements AnalyticsReader
{
    /**
     * {@inheritdoc}
     */
    public function getAnalyticsData(string $tenantId, Carbon $startDate, Carbon $endDate): Collection
    {
        return TenantAnalytic::where('tenant_id', $tenantId)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('date', 'asc')
            ->get()
            ->map(fn ($record) => [
                'date' => $record->date->format('Y-m-d'),
                'visits' => $record->unique_visitors,
                'total' => $record->views,
                'whatsapp_clicks' => $record->whatsapp_clicks,
            ]);
    }

    /**
     * {@inheritdoc}
     */
    public function getFilledAnalytics(string $tenantId, int $days): array
    {
        $startDate = now()->subDays($days)->startOfDay();
        $endDate = now()->endOfDay();

        $analytics = $this->getAnalyticsData($tenantId, $startDate, $endDate);

        // Fill gaps with zero values
        $filledData = [];
        $currentDate = $startDate->copy();

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $existing = $analytics->firstWhere('date', $dateStr);

            $filledData[] = [
                'date' => $dateStr,
                'visits' => $existing['visits'] ?? 0,
                'total' => $existing['total'] ?? 0,
            ];

            $currentDate->addDay();
        }

        // Calculate summary
        $totalVisits = $analytics->sum('visits');
        $avgVisits = $analytics->avg('visits') ?? 0;
        $peakDay = $analytics->sortByDesc('visits')->first();

        return [
            'data' => $filledData,
            'summary' => [
                'total_unique_visits' => $totalVisits,
                'average_daily_visits' => round($avgVisits, 1),
                'peak_day' => $peakDay ? [
                    'date' => $peakDay['date'],
                    'visits' => $peakDay['visits'],
                ] : null,
            ],
        ];
    }
}
