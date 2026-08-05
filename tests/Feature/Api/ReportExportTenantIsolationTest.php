<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Contracts\DataExportInterface;
use App\Exports\SalesExport;
use App\Infrastructure\Jobs\Exports\GenerateSalesExport;
use App\Infrastructure\Jobs\Reporting\GeneratePdfReportJob;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\ExportReadyNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ReportExportTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_report_jobs_preserve_authorized_uuid_context(): void
    {
        [$user, $tenant] = $this->tenantUser();
        Sanctum::actingAs($user);
        Queue::fake();

        $this->getJson('/api/v1/reports/sales/excel?start_date=2026-07-01&end_date=2026-07-20')
            ->assertAccepted();

        Queue::assertPushed(GenerateSalesExport::class, function (GenerateSalesExport $job) use ($user, $tenant): bool {
            return $job->tenantId === (string) $tenant->id
                && $job->userId === (string) $user->id;
        });

        $this->getJson('/api/v1/reports/sales/pdf?start_date=2026-07-01&end_date=2026-07-20')
            ->assertAccepted();

        Queue::assertPushed(GeneratePdfReportJob::class, function (GeneratePdfReportJob $job) use ($user, $tenant): bool {
            return $job->data->tenantId === (string) $tenant->id
                && $job->requestorId === (string) $user->id
                && str_starts_with($job->uniqueId(), 'pdf_'.$tenant->id.'_sales_');
        });
    }

    public function test_report_and_export_requests_reject_an_unrelated_tenant_header(): void
    {
        [$user] = $this->tenantUser();
        [, $otherTenant] = $this->tenantUser();
        Sanctum::actingAs($user);
        Queue::fake();

        $this->withHeader('X-Tenant-ID', (string) $otherTenant->id)
            ->getJson('/api/v1/reports/inventory/pdf')
            ->assertForbidden();

        $this->withHeader('X-Tenant-ID', (string) $otherTenant->id)
            ->postJson('/api/v1/exports/sales')
            ->assertForbidden();

        Queue::assertNothingPushed();
    }

    public function test_sales_export_dispatches_with_uuid_tenant_and_user_ids(): void
    {
        [$user, $tenant] = $this->tenantUser();
        Sanctum::actingAs($user);
        Queue::fake();

        $this->postJson('/api/v1/exports/sales', [
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-20',
        ])->assertAccepted();

        Queue::assertPushed(GenerateSalesExport::class, function (GenerateSalesExport $job) use ($user, $tenant): bool {
            return $job->tenantId === (string) $tenant->id
                && $job->userId === (string) $user->id
                && $job->criteriaData['start_date'] === '2026-07-01';
        });
    }

    public function test_sales_export_worker_keeps_uuid_scope_in_file_and_notification(): void
    {
        [$user, $tenant] = $this->tenantUser();
        Event::fake();
        Notification::fake();

        $exporter = $this->mock(DataExportInterface::class);
        $exporter->shouldReceive('store')
            ->once()
            ->withArgs(function (object $export, string $path) use ($tenant): bool {
                return $export instanceof SalesExport
                    && $export->getTenantId()->toString() === (string) $tenant->id
                    && str_starts_with($path, 'exports/tenant_'.$tenant->id.'/sales_');
            })
            ->andReturnTrue();

        $job = new GenerateSalesExport(
            userId: (string) $user->id,
            tenantId: (string) $tenant->id,
            criteriaData: ['start_date' => '2026-07-01', 'end_date' => '2026-07-20', 'status' => null],
            ipAddress: '127.0.0.1',
            userAgent: 'Tenant safety test',
        );
        $job->handle($exporter);

        Notification::assertSentTo(
            $user,
            ExportReadyNotification::class,
            fn (ExportReadyNotification $notification): bool => str_starts_with(
                (string) $notification->toArray($user)['file_path'],
                'exports/tenant_'.$tenant->id.'/sales_',
            ),
        );
    }

    public function test_signed_export_download_cannot_cross_tenant_or_escape_its_directory(): void
    {
        [$user, $tenant] = $this->tenantUser();
        [, $otherTenant] = $this->tenantUser();
        Sanctum::actingAs($user);
        Storage::fake('local');

        $ownedPath = 'exports/tenant_'.$tenant->id.'/sales_owned.xlsx';
        $foreignPath = 'exports/tenant_'.$otherTenant->id.'/sales_foreign.xlsx';
        Storage::disk('local')->put($ownedPath, 'owned');
        Storage::disk('local')->put($foreignPath, 'foreign');

        $ownedUrl = URL::temporarySignedRoute('api.exports.download', now()->addMinute(), [
            'path' => base64_encode($ownedPath),
            'tenant' => (string) $tenant->id,
        ]);
        $this->get($ownedUrl)->assertOk();

        $foreignUrl = URL::temporarySignedRoute('api.exports.download', now()->addMinute(), [
            'path' => base64_encode($foreignPath),
            'tenant' => (string) $otherTenant->id,
        ]);
        $this->getJson($foreignUrl)->assertForbidden();

        $escapedUrl = URL::temporarySignedRoute('api.exports.download', now()->addMinute(), [
            'path' => base64_encode('exports/tenant_'.$tenant->id.'/../tenant_'.$otherTenant->id.'/sales_foreign.xlsx'),
            'tenant' => (string) $tenant->id,
        ]);
        $this->getJson($escapedUrl)->assertBadRequest();
    }

    public function test_signed_pdf_download_is_bound_to_authenticated_tenant_path(): void
    {
        [$user, $tenant] = $this->tenantUser();
        [, $otherTenant] = $this->tenantUser();
        Sanctum::actingAs($user);
        Storage::fake('local');

        $ownedPath = 'tenants/'.$tenant->id.'/reports/sales_owned.pdf';
        $foreignPath = 'tenants/'.$otherTenant->id.'/reports/sales_foreign.pdf';
        Storage::disk('local')->put($ownedPath, '%PDF-owned');
        Storage::disk('local')->put($foreignPath, '%PDF-foreign');

        $ownedUrl = URL::temporarySignedRoute('reports.download', now()->addMinute(), ['path' => $ownedPath]);
        $this->get($ownedUrl)->assertOk();

        $foreignUrl = URL::temporarySignedRoute('reports.download', now()->addMinute(), ['path' => $foreignPath]);
        $this->get($foreignUrl)->assertForbidden();
    }

    /** @return array{User, Tenant} */
    private function tenantUser(): array
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->forUser($user)->create();
        $user->forceFill(['tenant_id' => $tenant->id])->save();

        return [$user->refresh(), $tenant];
    }
}
