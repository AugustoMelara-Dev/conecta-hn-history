<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Domain\Identity\Enums\UserRole;
use App\Infrastructure\Jobs\Reporting\GeneratePdfReportJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class LegacyWorkspaceCompatibilityTest extends TestCase
{
    use DatabaseTransactions;

    public function test_legacy_sales_pdf_endpoint_queues_the_official_report_job_and_redirects_to_growth_center(): void
    {
        Queue::fake();

        [$user, $tenant] = $this->makeTenantOwner('cafe-norte-reportes');

        $response = $this->actingAs($user)->get(route('reports.sales-pdf', [
            'start_date' => '2026-03-01',
            'end_date' => '2026-03-20',
        ], false));

        $response
            ->assertRedirect(route('filament.app.pages.growth-center', ['tenant' => $tenant->slug], false))
            ->assertSessionHas('success');

        Queue::assertPushed(GeneratePdfReportJob::class, function (GeneratePdfReportJob $job) use ($tenant, $user): bool {
            return $job->data->tenantId === (string) $tenant->getKey()
                && $job->data->reportType === 'sales'
                && $job->data->startDate->format('Y-m-d') === '2026-03-01'
                && $job->data->endDate->format('Y-m-d') === '2026-03-20'
                && (string) $job->requestorId === (string) $user->getAuthIdentifier()
                && $job->queue === 'reports';
        });
    }

    public function test_legacy_inventory_pdf_endpoint_queues_the_official_report_job_and_redirects_to_growth_center(): void
    {
        Queue::fake();

        [$user, $tenant] = $this->makeTenantOwner('cafe-norte-inventario');

        $response = $this->actingAs($user)->get(route('reports.inventory-pdf', [], false));

        $response
            ->assertRedirect(route('filament.app.pages.growth-center', ['tenant' => $tenant->slug], false))
            ->assertSessionHas('success');

        Queue::assertPushed(GeneratePdfReportJob::class, function (GeneratePdfReportJob $job) use ($tenant, $user): bool {
            return $job->data->tenantId === (string) $tenant->getKey()
                && $job->data->reportType === 'inventory'
                && (string) $job->requestorId === (string) $user->getAuthIdentifier()
                && $job->queue === 'reports';
        });
    }

    /**
     * @return array{0: User, 1: Tenant}
     */
    private function makeTenantOwner(string $slug): array
    {
        $user = User::factory()->create([
            'role' => UserRole::TENANT_OWNER,
        ]);

        $tenant = Tenant::factory()->create([
            'user_id' => $user->id,
            'slug' => $slug,
        ]);

        $user->forceFill(['tenant_id' => $tenant->id])->save();

        return [$user, $tenant];
    }
}
