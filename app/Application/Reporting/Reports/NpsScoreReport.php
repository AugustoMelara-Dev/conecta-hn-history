<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Application\Reporting\Contracts\ReportInterface;
use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;

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
        return ReportAvailability::unavailable(
            $range,
            'Las reseñas de 1 a 5 estrellas no son respuestas NPS de 0 a 10 y no deben convertirse artificialmente.',
            ['nps_responses.score_0_to_10'],
        );
    }
}
