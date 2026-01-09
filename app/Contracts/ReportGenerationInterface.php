<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Tenant;

interface ReportGenerationInterface
{
    /**
     * Generates a raw PDF string for the sales report.
     *
     * @param  Tenant  $tenant  The tenant requesting the report.
     * @param  array  $filters  Filters applied to the report (date range, status, etc).
     * @return string Raw binary PDF content.
     */
    public function generateSalesPdf(Tenant $tenant, array $filters): string;

    /**
     * Generates a raw PDF string for the inventory report.
     *
     * @param  Tenant  $tenant  The tenant requesting the report.
     * @return string Raw binary PDF content.
     */
    public function generateInventoryPdf(Tenant $tenant): string;

    /**
     * Generates a raw PDF string for the kitchen report.
     *
     * @param  Tenant  $tenant  The tenant requesting the report.
     * @return string Raw binary PDF content.
     */
    public function generateKitchenPdf(Tenant $tenant): string;
}
