<?php

declare(strict_types=1);

namespace App\Application\Crm;

use App\Domain\Crm\Enums\DealStatus;
use Illuminate\Support\Facades\DB;

/**
 * WinLossAnalysisService - Analiza razones de won/lost deals.
 */
class WinLossAnalysisService
{
    /**
     * @return array<string, int> lost_reason => count
     */
    public function topLostReasons(string $tenantId, int $limit = 10): array
    {
        $rows = DB::table('crm_deals')
            ->where('tenant_id', $tenantId)
            ->where('status', DealStatus::Lost->value)
            ->whereNotNull('lost_reason')
            ->selectRaw('lost_reason, COUNT(*) as count')
            ->groupBy('lost_reason')
            ->orderByDesc('count')
            ->limit($limit)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $result[$row->lost_reason] = (int) $row->count;
        }

        return $result;
    }

    public function winRate(string $tenantId): float
    {
        $total = DB::table('crm_deals')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [DealStatus::Won->value, DealStatus::Lost->value])
            ->count();

        if ($total === 0) {
            return 0.0;
        }

        $won = DB::table('crm_deals')
            ->where('tenant_id', $tenantId)
            ->where('status', DealStatus::Won->value)
            ->count();

        return round(($won / $total) * 100, 2);
    }

    /**
     * @return array<int, array{month: string, won_count: int, lost_count: int, won_value: float, lost_value: float}>
     */
    public function monthlyWinLoss(string $tenantId, int $months = 12): array
    {
        return DB::table('crm_deals')
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [DealStatus::Won->value, DealStatus::Lost->value])
            ->where('created_at', '>=', now()->subMonths($months))
            ->selectRaw("TO_CHAR(created_at, 'YYYY-MM') as month, status, COUNT(*) as count, SUM(value) as total_value")
            ->groupBy('month', 'status')
            ->orderBy('month')
            ->get()
            ->groupBy('month')
            ->map(fn ($group, $month) => [
                'month' => $month,
                'won_count' => (int) ($group->firstWhere('status', DealStatus::Won->value)?->count ?? 0),
                'lost_count' => (int) ($group->firstWhere('status', DealStatus::Lost->value)?->count ?? 0),
                'won_value' => (float) ($group->firstWhere('status', DealStatus::Won->value)?->total_value ?? 0),
                'lost_value' => (float) ($group->firstWhere('status', DealStatus::Lost->value)?->total_value ?? 0),
            ])
            ->values()
            ->all();
    }
}
