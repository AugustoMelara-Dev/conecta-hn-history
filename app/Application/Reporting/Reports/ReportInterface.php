<?php

declare(strict_types=1);

namespace App\Application\Reporting\Reports;

use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\ValueObjects\ReportRange;

interface ReportInterface
{
    public function getKey(): ReportKey;

    public function getName(): string;

    public function getDescription(): string;

    public function getChartType(): string;

    public function getColumns(): array;

    public function getFilters(): array;

    public function getData(string $tenantId, ReportRange $range): ReportData;
}
