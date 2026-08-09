<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\ValueObjects\ReportRange;
use App\Domain\Reporting\ValueObjects\ReportRow;

class ConversionFunnelReport implements ReportInterface
{
    public function getKey(): ReportKey
    {
        return ReportKey::ConversionFunnel;
    }

    public function getName(): string
    {
        return 'Embudo de conversión';
    }

    public function getDescription(): string
    {
        return 'Embudo lifecycle ? won.';
    }

    public function getChartType(): string
    {
        return 'funnel';
    }

    public function getColumns(): array
    {
        return ['Etapa', 'Contactos', 'Conversión %'];
    }

    public function getFilters(): array
    {
        return [];
    }

    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        $stages = ['lead', 'qualified', 'proposal', 'negotiation', 'won'];
        $rows = [];
        $prev = null;
        foreach ($stages as $stage) {
            $count = DB::table('crm_contacts')->where('tenant_id', $tenantId)->where('lifecycle_stage', $stage)->count();
            $conv = $prev !== null && $prev > 0 ? ($count / $prev) * 100 : 100;
            $rows[] = new ReportRow(ucfirst($stage), $count, unit: '#', metadata: ['conversion_pct' => round($conv, 1)]);
            $prev = $count > 0 ? $count : $prev;
        }

        return new ReportData(rows: $rows, series: [], summary: [], metadata: ['range' => $range->toArray()]);
    }
}
