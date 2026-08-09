<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Application\Imports\OperationalImportService;
use App\Models\CatalogImportBatch;
use App\Models\CatalogImportItem;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class OperationalImportPublicationFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_publication_failure_is_redacted_and_not_replayed(): void
    {
        $rawMarker = 'RAW_OPERATIONAL_IMPORT_SECRET';
        $owner = User::factory()->create(['email' => 'operational-publication-failure@example.test']);
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
                "name,price_minor,currency,stock_quantity,published\nFailure product,63900,CAD,2,true",
            ),
            'product',
            'operational-import:publication-failure:1',
        );
        $service->process((string) $tenant->id, $batchId);
        $item = CatalogImportItem::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('batch_id', $batchId)
            ->firstOrFail();
        $service->review(
            (string) $tenant->id,
            (string) $owner->id,
            $batchId,
            [(string) $item->id => 'approve'],
        );

        DB::unprepared(<<<SQL
            CREATE TRIGGER operational_import_product_failure
            BEFORE INSERT ON products
            BEGIN
                SELECT RAISE(ABORT, '{$rawMarker}');
            END
            SQL);
        try {
            $report = $service->publish((string) $tenant->id, (string) $owner->id, $batchId);
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS operational_import_product_failure');
        }

        self::assertSame(1, $report->total);
        self::assertSame(1, $report->failed);
        self::assertSame(0, $report->published);
        self::assertSame(0, Product::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        $failedItem = CatalogImportItem::query()->withoutGlobalScopes()->findOrFail($item->id);
        self::assertSame('failed', $failedItem->status->value);
        self::assertSame('publication_failed', $failedItem->error_code);
        self::assertSame('Operational import item could not be published.', $failedItem->error_message);
        self::assertNull($failedItem->aggregate_id);
        self::assertNull($failedItem->product_id);
        self::assertNull($failedItem->published_at);
        self::assertStringNotContainsString($rawMarker, json_encode($failedItem->toArray(), JSON_THROW_ON_ERROR));

        $batch = CatalogImportBatch::query()->withoutGlobalScopes()->findOrFail($batchId);
        self::assertSame('published', $batch->status->value);
        self::assertSame(1, $batch->total_items);
        self::assertSame(1, $batch->processed_items);
        self::assertSame(1, $batch->failed_items);
        self::assertNotNull($batch->completed_at);

        $replayed = $service->publish((string) $tenant->id, (string) $owner->id, $batchId);
        self::assertSame(1, $replayed->total);
        self::assertSame(1, $replayed->failed);
        self::assertSame(0, $replayed->published);
        self::assertSame(0, Product::withoutGlobalScopes()->where('tenant_id', $tenant->id)->count());
        self::assertSame(1, CatalogImportItem::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('batch_id', $batchId)
            ->count());
    }
}
