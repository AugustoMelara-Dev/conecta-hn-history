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
        $job = new SyncAnalyticsJob;

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertStringStartsWith('sync_analytics_', $job->uniqueId());
    }

    /**
     * @test
     */
    public function generate_product_blurhash_job_implements_should_be_unique(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $job = new GenerateProductBlurhash(productId: 100, tenantId: $tenantId);

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertEquals('blurhash_'.$tenantId.'_100', $job->uniqueId());
    }

    /**
     * @test
     */
    public function generate_pdf_report_job_implements_should_be_unique(): void
    {
        $tenantId = '00000000-0000-4000-8000-000000000001';
        $data = new ReportRequestData(
            tenantId: $tenantId,
            reportType: 'sales',
            startDate: new \DateTimeImmutable('2026-01-01'),
            endDate: new \DateTimeImmutable('2026-01-31')
        );
        $job = new GeneratePdfReportJob($data, requestorId: '00000000-0000-4000-8000-000000000101');

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertEquals('pdf_'.$tenantId.'_sales_2026-01-01', $job->uniqueId());
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // Tenant Isolation Verification (Unique Keys Don't Collide)
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * @test
     */
    public function blurhash_unique_keys_are_tenant_scoped(): void
    {
        $tenant1Job = new GenerateProductBlurhash(productId: 100, tenantId: '00000000-0000-4000-8000-000000000001');
        $tenant2Job = new GenerateProductBlurhash(productId: 100, tenantId: '00000000-0000-4000-8000-000000000002');

        // Same product ID but different tenants = different unique keys
        $this->assertNotEquals($tenant1Job->uniqueId(), $tenant2Job->uniqueId());
    }

    /**
     * @test
     */
    public function pdf_unique_keys_are_tenant_scoped(): void
    {
        $tenant1Data = new ReportRequestData(
            tenantId: '00000000-0000-4000-8000-000000000001',
            reportType: 'sales',
            startDate: new \DateTimeImmutable('2026-01-01'),
            endDate: new \DateTimeImmutable('2026-01-31')
        );
        $tenant2Data = new ReportRequestData(
            tenantId: '00000000-0000-4000-8000-000000000002',
            reportType: 'sales',
            startDate: new \DateTimeImmutable('2026-01-01'),
            endDate: new \DateTimeImmutable('2026-01-31')
        );

        $requestorId = '00000000-0000-4000-8000-000000000101';
        $tenant1Job = new GeneratePdfReportJob($tenant1Data, requestorId: $requestorId);
        $tenant2Job = new GeneratePdfReportJob($tenant2Data, requestorId: $requestorId);

        // Same report params but different tenants = different unique keys
        $this->assertNotEquals($tenant1Job->uniqueId(), $tenant2Job->uniqueId());
    }
}
