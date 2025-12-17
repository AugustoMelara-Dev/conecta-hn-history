<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Skip;
use Tests\TestCase;

/**
 * GoldMasterAuditTest - Tests de auditoría completa del sistema
 *
 * @todo Refactorizar para nueva arquitectura:
 * - Algunos Jobs ya no existen (AuditTenantLimits, SyncAnalyticsJob)
 * - TenantAnalytic model migrado
 * - Redis no disponible en CI
 */
#[Skip('Pendiente refactorización: Jobs y Models han cambiado')]
class GoldMasterAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders_correctly(): void
    {
        $this->markTestSkipped('Pendiente refactorización');
    }

    public function test_directory_city_filter_works(): void
    {
        $this->markTestSkipped('Pendiente refactorización');
    }

    public function test_analytics_pipeline_works(): void
    {
        $this->markTestSkipped('Pendiente refactorización');
    }
}
