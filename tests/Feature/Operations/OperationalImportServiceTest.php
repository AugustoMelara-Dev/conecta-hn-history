<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Application\Imports\OperationalImportService;
use App\Models\CatalogImportBatch;
use App\Models\CatalogImportItem;
use App\Models\Location;
use App\Models\Product;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class OperationalImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_stage_validates_source_replays_and_parses_rows_without_publishing(): void
    {
        [$tenant, $owner] = $this->tenant('operational-import@example.test');
        Queue::fake();
        $csv = implode("\n", [
            'name,price_minor,currency,stock_quantity,published',
            'Travel mug,2500,CAD,4,true',
            '=cmd|calc,1900,CAD,2,true',
            "'=cmd|calc,1800,CAD,2,true",
            'Wrong currency,1000,USD,1,true',
        ]);
        $service = app(OperationalImportService::class);

        $batchId = $service->stage(
            (string) $tenant->id,
            (string) $owner->id,
            UploadedFile::fake()->createWithContent('products.csv', $csv),
            'product',
            'operational-import:stage:1',
        );
        $replayed = $service->stage(
            (string) $tenant->id,
            (string) $owner->id,
            UploadedFile::fake()->createWithContent('products.csv', $csv),
            'product',
            'operational-import:stage:1',
        );

        self::assertSame($batchId, $replayed);
        self::assertSame(1, CatalogImportBatch::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->count());
        self::assertNotNull(CatalogImportBatch::query()
            ->withoutGlobalScopes()
            ->findOrFail($batchId)
            ->correlation_id);

        $service->process((string) $tenant->id, $batchId);
        $batch = CatalogImportBatch::query()
            ->withoutGlobalScopes()
            ->findOrFail($batchId);
        $items = CatalogImportItem::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('batch_id', $batchId)
            ->orderBy('row_number')
            ->get();
        self::assertSame(4, $items->count());
        self::assertSame('needs_review', $items[0]->status->value);
        self::assertSame('failed', $items[1]->status->value);
        self::assertSame('formula_injection', $items[1]->error_code);
        self::assertSame('failed', $items[2]->status->value);
        self::assertSame('formula_injection', $items[2]->error_code);
        self::assertSame('failed', $items[3]->status->value);
        self::assertSame('currency_mismatch', $items[3]->error_code);
        self::assertDatabaseCount('products', 0);

        try {
            $service->stage(
                (string) $tenant->id,
                (string) $owner->id,
                UploadedFile::fake()->createWithContent('fake.xlsx', 'not-a-zip'),
                'product',
                'operational-import:bad-signature',
            );
            self::fail('Spreadsheet MIME and signature mismatches must fail.');
        } catch (ValidationException) {
            self::assertSame(1, CatalogImportBatch::query()->withoutGlobalScopes()->count());
        }
    }

    public function test_review_and_publish_use_canonical_writers_with_partial_failures_and_tenant_isolation(): void
    {
        [$tenant, $owner] = $this->tenant('operational-publish@example.test');
        [$other, $otherOwner] = $this->tenant('operational-publish-other@example.test');
        Queue::fake();
        $service = app(OperationalImportService::class);
        $batchId = $service->stage(
            (string) $tenant->id,
            (string) $owner->id,
            UploadedFile::fake()->createWithContent('products.csv', implode("\n", [
                'name,price_minor,currency,stock_quantity,published',
                'Approved product,2500,CAD,3,true',
                'Rejected product,1500,CAD,2,true',
            ])),
            'product',
            'operational-import:publish:1',
        );
        $service->process((string) $tenant->id, $batchId);
        CatalogImportBatch::query()
            ->withoutGlobalScopes()
            ->findOrFail($batchId);
        $items = CatalogImportItem::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('batch_id', $batchId)
            ->orderBy('row_number')
            ->get();

        try {
            $service->review(
                (string) $other->id,
                (string) $otherOwner->id,
                $batchId,
                [],
            );
            self::fail('Cross-tenant import review must hide the batch.');
        } catch (ModelNotFoundException) {
            self::assertDatabaseCount('products', 0);
        }

        $review = $service->review(
            (string) $tenant->id,
            (string) $owner->id,
            $batchId,
            [
                (string) $items[0]->id => 'approve',
                (string) $items[1]->id => 'reject',
            ],
        );
        self::assertSame(1, $review->approved);
        self::assertSame(1, $review->rejected);
        self::assertDatabaseCount('products', 0);

        $published = $service->publish((string) $tenant->id, (string) $owner->id, $batchId);
        $replayed = $service->publish((string) $tenant->id, (string) $owner->id, $batchId);

        self::assertSame(1, $published->published);
        self::assertSame(1, $replayed->published);
        self::assertSame(1, Product::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->count());
        $this->assertDatabaseHas('products', [
            'tenant_id' => $tenant->id,
            'name' => 'Approved product',
            'price_minor' => 2_500,
            'currency' => 'CAD',
            'stock_quantity' => 3,
            'is_visible' => true,
        ]);
        $this->assertDatabaseMissing('products', ['name' => 'Rejected product']);
    }

    public function test_service_and_opportunity_rows_validate_location_timezone_and_publish_canonically(): void
    {
        [$tenant, $owner] = $this->tenant('operational-mixed@example.test');
        $location = Location::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();
        Queue::fake();
        $service = app(OperationalImportService::class);
        $serviceBatch = $service->stage(
            (string) $tenant->id,
            (string) $owner->id,
            UploadedFile::fake()->createWithContent('services.csv', implode("\n", [
                'name,description,price_minor,currency,duration_minutes,location_id,timezone,published',
                "Consulting,Strategy session,10000,CAD,60,{$location->id},America/Toronto,true",
            ])),
            'service',
            'operational-import:service:1',
        );
        $opportunityBatch = $service->stage(
            (string) $tenant->id,
            (string) $owner->id,
            UploadedFile::fake()->createWithContent('opportunities.csv', implode("\n", [
                'title,value_minor,currency,location_id,source,expected_close_date,notes',
                "Expansion,50000,CAD,{$location->id},csv,2026-09-01,Qualified",
            ])),
            'opportunity',
            'operational-import:opportunity:1',
        );

        foreach ([$serviceBatch, $opportunityBatch] as $batchId) {
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
            $service->publish((string) $tenant->id, (string) $owner->id, $batchId);
        }

        self::assertSame(1, Service::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('name', 'Consulting')
            ->count());
        $this->assertDatabaseHas('services', [
            'tenant_id' => $tenant->id,
            'name' => 'Consulting',
            'price_minor' => 10_000,
            'currency' => 'CAD',
            'duration_minutes' => 60,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('crm_deals', [
            'tenant_id' => $tenant->id,
            'title' => 'Expansion',
            'currency' => 'CAD',
            'source' => 'csv',
        ]);
    }

    public function test_spreadsheet_signature_unknown_columns_and_oversized_rows_are_governed(): void
    {
        [$tenant, $owner] = $this->tenant('operational-spreadsheet@example.test');
        Queue::fake();
        $service = app(OperationalImportService::class);
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            ['name', 'price_minor', 'currency', 'stock_quantity', 'published'],
            ['Spreadsheet product', 1200, 'CAD', 1, false],
            ['=1+1', 1200, 'CAD', 1, false],
        ]);
        $temporary = tempnam(sys_get_temp_dir(), 'dovra-import-xlsx-');
        self::assertNotFalse($temporary);
        (new Xlsx($spreadsheet))->save($temporary);
        try {
            $batchId = $service->stage(
                (string) $tenant->id,
                (string) $owner->id,
                new UploadedFile(
                    $temporary,
                    'products.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true,
                ),
                'product',
                'operational-import:xlsx:1',
            );
        } finally {
            @unlink($temporary);
            $spreadsheet->disconnectWorksheets();
        }
        $service->process((string) $tenant->id, $batchId);
        self::assertSame('needs_review', CatalogImportItem::query()
            ->withoutGlobalScopes()
            ->where('batch_id', $batchId)
            ->where('row_number', 2)
            ->firstOrFail()
            ->status->value);
        self::assertSame('formula_injection', CatalogImportItem::query()
            ->withoutGlobalScopes()
            ->where('batch_id', $batchId)
            ->where('row_number', 3)
            ->firstOrFail()
            ->error_code);

        $unknown = $service->stage(
            (string) $tenant->id,
            (string) $owner->id,
            UploadedFile::fake()->createWithContent(
                'unknown.csv',
                "name,price_minor,currency,stock_quantity,unexpected\nBad,100,CAD,1,value",
            ),
            'product',
            'operational-import:unknown:1',
        );
        $this->expectException(ValidationException::class);
        $service->process((string) $tenant->id, $unknown);
    }

    public function test_oversized_row_fails_without_blocking_the_batch_contract(): void
    {
        [$tenant, $owner] = $this->tenant('operational-oversized@example.test');
        Queue::fake();
        $service = app(OperationalImportService::class);
        $batchId = $service->stage(
            (string) $tenant->id,
            (string) $owner->id,
            UploadedFile::fake()->createWithContent(
                'oversized.csv',
                "name,price_minor,currency,stock_quantity\n"
                    .str_repeat('A', 33_000).',100,CAD,1',
            ),
            'product',
            'operational-import:oversized:1',
        );

        $service->process((string) $tenant->id, $batchId);

        $item = CatalogImportItem::query()
            ->withoutGlobalScopes()
            ->where('batch_id', $batchId)
            ->firstOrFail();
        self::assertSame('failed', $item->status->value);
        self::assertContains('row_too_large', $item->validation_errors);
        self::assertDatabaseCount('products', 0);
    }

    /** @return array{Tenant, User} */
    private function tenant(string $email): array
    {
        $owner = User::factory()->create(['email' => $email]);
        $tenant = Tenant::factory()->forUser($owner)->pro()->create([
            'business_type' => 'service',
            'commercial_mode' => 'hybrid',
        ]);
        GlobalMarketFixture::assignCanada($tenant);

        return [$tenant, $owner];
    }
}
