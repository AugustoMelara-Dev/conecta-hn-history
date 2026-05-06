<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Application\DTOs\ReportRequestData;
use App\Infrastructure\Jobs\Analytics\SyncAnalyticsJob;
use App\Infrastructure\Jobs\Media\GenerateProductBlurhash;
use App\Infrastructure\Jobs\Reporting\GeneratePdfReportJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Tests\TestCase;

/**
 * JobIdempotencyTest - VC-AUDIT-004 Verification
 *
 * PROVES: All critical jobs implement ShouldBeUnique with deterministic uniqueId.
 */
class JobIdempotencyTest extends TestCase
{
    // ═══════════════════════════════════════════════════════════════════════════
    // ShouldBeUnique Interface Verification
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * @test
     */
    public function sync_analytics_job_implements_should_be_unique(): void
    {
        $job = new SyncAnalyticsJob();

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertIsString($job->uniqueId());
        $this->assertStringStartsWith('sync_analytics_', $job->uniqueId());
    }

    /**
     * @test
     */
    public function generate_product_blurhash_job_implements_should_be_unique(): void
    {
        $job = new GenerateProductBlurhash(productId: 100, tenantId: 1);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertEquals('blurhash_1_100', $job->uniqueId());
    }

    /**
     * @test
     */
    public function generate_pdf_report_job_implements_should_be_unique(): void
    {
        $data = new ReportRequestData(
            tenantId: 1,
            reportType: 'sales',
            startDate: new \DateTimeImmutable('2026-01-01'),
            endDate: new \DateTimeImmutable('2026-01-31')
        );
        $job = new GeneratePdfReportJob($data, requestorId: 1);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertEquals('pdf_1_sales_2026-01-01', $job->uniqueId());
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Tenant Isolation Verification (Unique Keys Don't Collide)
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * @test
     */
    public function blurhash_unique_keys_are_tenant_scoped(): void
    {
        $tenant1Job = new GenerateProductBlurhash(productId: 100, tenantId: 1);
        $tenant2Job = new GenerateProductBlurhash(productId: 100, tenantId: 2);

        // Same product ID but different tenants = different unique keys
        $this->assertNotEquals($tenant1Job->uniqueId(), $tenant2Job->uniqueId());
    }

    /**
     * @test
     */
    public function pdf_unique_keys_are_tenant_scoped(): void
    {
        $tenant1Data = new ReportRequestData(
            tenantId: 1,
            reportType: 'sales',
            startDate: new \DateTimeImmutable('2026-01-01'),
            endDate: new \DateTimeImmutable('2026-01-31')
        );
        $tenant2Data = new ReportRequestData(
            tenantId: 2,
            reportType: 'sales',
            startDate: new \DateTimeImmutable('2026-01-01'),
            endDate: new \DateTimeImmutable('2026-01-31')
        );

        $tenant1Job = new GeneratePdfReportJob($tenant1Data, requestorId: 1);
        $tenant2Job = new GeneratePdfReportJob($tenant2Data, requestorId: 1);

        // Same report params but different tenants = different unique keys
        $this->assertNotEquals($tenant1Job->uniqueId(), $tenant2Job->uniqueId());
    }
}
