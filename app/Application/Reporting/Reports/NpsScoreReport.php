<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Application\Reporting\Contracts\ReportInterface;
use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;
use App\Domain\Reporting\ValueObjects\ReportRow;

class NpsScoreReport implements ReportInterface
{
    public function getKey(): ReportKey
    {
        return ReportKey::NpsScore;
    }

    public function getName(): string
    {
        return 'NPS score';
    }

    public function getDescription(): string
    {
        return 'Net Promoter Score.';
    }

    public function getChartType(): string
    {
        return 'gauge';
    }

    public function getColumns(): array
    {
        return ['Métrica', 'Valor'];
    }

    public function getFilters(): array
    {
        return [];
    }

    public function getData(string $tenantId, ReportRange $range): ReportData
    {
        return new ReportData(rows: [new ReportRow('NPS', 0, unit: 'score')], series: [], summary: ['placeholder' => true], metadata: ['range' => $range->toArray()]);
    }
}
