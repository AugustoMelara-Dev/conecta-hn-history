<?php

declare(strict_types=1);

namespace App\Application\Reporting\Contracts;

use App\Application\Reporting\ValueObjects\ReportData;
use App\Domain\Reporting\Enums\ReportKey;
use App\Domain\Reporting\ValueObjects\ReportRange;

/**
 * ReportInterface - Cada Report class implementa este contrato.
 *
 * Reports son tenant-scoped: getData recibe tenantId.
 * Implementaciones purgan cache cuando hay writes.
 */
interface ReportInterface
{
    public function getKey(): ReportKey;

    public function getName(): string;

    public function getDescription(): string;

    public function getChartType(): string;

    public function getColumns(): array;

    /**
     * @return array<string, string>
     */
    public function getFilters(): array;

    public function getData(string $tenantId, ReportRange $range): ReportData;
}
