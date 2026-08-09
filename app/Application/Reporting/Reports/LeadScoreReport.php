<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\ValueObjects\ReportRange;
use App\Domain\Reporting\ValueObjects\ReportRow;

class LeadScoreReport implements ReportInterface
{
    public function getKey(): ReportKey
    {
        return ReportKey::LeadScore;
    }

    public function getName(): string
    {
        return 'Distribución de lead score';
    }

    public function getDescription(): string
    {
        return 'Distribución de contactos por score.';
    }

    public function getChartType(): string
    {
        return 'pie';
    }

    public function getColumns(): array
    {
        return ['Tier', 'Contactos', '%'];
    }

    public function getFilters(): array
    {
        return [];
    }

    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        $tiers = ['hot' => 0, 'warm' => 0, 'qualified' => 0, 'cold' => 0, 'unqualified' => 0];
        $contacts = DB::table('crm_contacts')->where('tenant_id', $tenantId)->whereBetween('created_at', [$range->start, $range->end])->get();
        foreach ($contacts as $c) {
            $score = 0;
            if ($c->lifecycle_stage === 'qualified') {
                $score += 30;
            }
            if ($c->lifecycle_stage === 'won') {
                $score += 100;
            }
            if ($c->email) {
                $score += 30;
            }
            if ($c->phone) {
                $score += 25;
            }
            if ($c->source === 'referral') {
                $score += 100;
            }
            $score = min(100, $score);
            $tier = match (true) {
                $score >= 80 => 'hot', $score >= 60 => 'warm', $score >= 40 => 'qualified', $score >= 20 => 'cold', default => 'unqualified'
            };
            $tiers[$tier]++;
        }
        $total = array_sum($tiers);
        $rows = [];
        foreach ($tiers as $tier => $count) {
            $pct = $total > 0 ? ($count / $total) * 100 : 0;
            $rows[] = new ReportRow($tier, $count, unit: '#', metadata: ['pct' => round($pct, 1)]);
        }

        return new ReportData(rows: $rows, series: [], summary: [], metadata: ['range' => $range->toArray()]);
    }
}
