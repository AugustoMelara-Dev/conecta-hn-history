<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class CrmRoutesSnapshotTest extends TestCase
{
    public function test_crm_route_surface_remains_stable(): void
    {
        Artisan::call('route:list', [
            '--path' => 'api/v1/crm',
        ]);

        $output = Artisan::output();

        $this->assertStringContainsString('api.crm.pipelines.index', $output);
        $this->assertStringContainsString('api.crm.pipeline-summary.show', $output);
        $this->assertStringContainsString('api.crm.deals.index', $output);
        $this->assertStringContainsString('api.crm.deals.store', $output);
        $this->assertStringContainsString('api.crm.deals.import', $output);
        $this->assertStringContainsString('api.crm.deals.export', $output);
        $this->assertStringContainsString('api.crm.leads.convert-to-deal', $output);
        $this->assertStringContainsString('api.crm.pipelines.store', $output);
        $this->assertStringContainsString('api.crm.pipelines.update', $output);
        $this->assertStringContainsString('api.crm.pipelines.destroy', $output);
        $this->assertStringContainsString('api.crm.pipelines.stages.store', $output);
        $this->assertStringContainsString('api.crm.stages.update', $output);
        $this->assertStringContainsString('api.crm.stages.destroy', $output);
        $this->assertStringContainsString('api.crm.deals.update', $output);
        $this->assertStringContainsString('api.crm.deals.stage.update', $output);
        $this->assertStringContainsString('api.crm.deals.destroy', $output);
    }
}
