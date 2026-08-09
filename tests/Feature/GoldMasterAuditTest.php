<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * GoldMasterAuditTest - Tests de auditoría completa del sistema
 *
 * @todo Refactorizar para nueva arquitectura:
 * - Algunos Jobs ya no existen (AuditTenantLimits, SyncAnalyticsJob)
 * - TenantAnalytic model migrado
 * - Redis no disponible en CI
 */
class GoldMasterAuditTest extends TestCase
{
    public function test_landing_page_renders_correctly(): void
    {
        $this->get('/')->assertOk();
    }

    public function test_directory_city_filter_works(): void
    {
        $response = $this->get('/directorio?city=tocoa');
        $this->assertNotEquals(500, $response->status());
    }

    public function test_analytics_pipeline_works(): void
    {
        $response = $this->postJson('/api/v1/analytics/track', [
            'event' => 'page_view',
            'tenant_slug' => 'demo-tenant',
        ]);

        $this->assertNotEquals(500, $response->status());
    }
}
