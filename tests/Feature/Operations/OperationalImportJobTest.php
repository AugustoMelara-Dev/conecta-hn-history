<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Application\Imports\OperationalImportService;
use App\Domain\Shared\Services\TenantContext;
use App\Jobs\ProcessOperationalImport;
use App\Models\CatalogImportBatch;
use App\Models\CatalogImportItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class OperationalImportJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_carries_tenant_and_correlation_context_and_cleans_worker_state(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->pro()->create([
            'business_type' => 'service',
            'commercial_mode' => 'hybrid',
        ]);
        GlobalMarketFixture::assignCanada($tenant);
        Queue::fake();
        $service = app(OperationalImportService::class);
        $batchId = $service->stage(
            (string) $tenant->id,
            (string) $owner->id,
            UploadedFile::fake()->createWithContent(
                'products.csv',
                "name,price_minor,currency,stock_quantity\nJob product,1000,CAD,1",
            ),
            'product',
            'operational-import:job:1',
        );
        app(TenantContext::class)->reset();

        $job = new ProcessOperationalImport(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            batchId: $batchId,
            correlationId: 'corr-operational-import-1',
            idempotencyKey: 'operational-import:job:1',
        );
        $middleware = $job->middleware();
        self::assertNotEmpty($middleware);
        $middleware[0]->handle($job, fn () => $job->handle($service));

        self::assertFalse(app(TenantContext::class)->isInitialized());
        self::assertSame('needs_review', CatalogImportBatch::query()
            ->withoutGlobalScopes()
            ->findOrFail($batchId)
            ->status->value);
        self::assertSame(1, CatalogImportItem::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('batch_id', $batchId)
            ->count());
    }
}
