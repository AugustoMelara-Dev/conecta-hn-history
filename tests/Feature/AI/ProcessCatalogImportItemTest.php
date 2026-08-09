<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\Application\Ai\Catalog\CatalogVisionImage;
use App\Application\Ai\Catalog\CatalogVisionIngressGuard;
use App\Application\Ai\Catalog\CatalogVisionProviderInterface;
use App\Application\Ai\Catalog\CatalogVisionProviderResult;
use App\Application\Privacy\Dovra\DataClassificationPolicy;
use App\Application\Privacy\Dovra\DovraDataIngressGuard;
use App\Application\Shared\Contracts\SchemaValidatorInterface;
use App\Domain\Catalog\Enums\CatalogImportStatus;
use App\Domain\Privacy\Dovra\SensitivePayloadRedactor;
use App\Domain\Shared\Services\TenantContext;
use App\Infrastructure\Jobs\AI\ProcessCatalogImportItem;
use App\Infrastructure\Privacy\Dovra\DovraIntegrationSeamRegistry;
use App\Models\CatalogImportBatch;
use App\Models\CatalogImportItem;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class ProcessCatalogImportItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_provider_result_becomes_reviewable_draft_without_publishing_product(): void
    {
        [$tenant, $item] = $this->queuedItem();
        $provider = new class implements CatalogVisionProviderInterface
        {
            public function analyze(CatalogVisionImage $image): CatalogVisionProviderResult
            {
                return new CatalogVisionProviderResult(
                    payload: [
                        'name' => '  Café <script>alert(1)</script> Premium  ',
                        'description' => '<p>Café hondureño</p><script>hostile()</script>',
                        'short_description' => '<b>Café premium</b><script>short()</script>',
                        'category_suggestion' => ' Bebidas ',
                        'tags' => [' <b>café</b> ', 'artesanal<script>tag()</script>'],
                        'colors' => [' Café '],
                        'material' => '<em>Papel</em>',
                        'dimensions' => null,
                        'variations' => [[
                            'name' => '<b>Tamaño</b>',
                            'options' => ['12 oz<script>option()</script>'],
                        ]],
                        'condition' => 'new',
                        'warnings' => ['<script>warning()</script>Revisar etiqueta'],
                        'price' => null,
                        'stock_quantity' => null,
                        'unit' => null,
                        'field_confidence' => [
                            'name' => 0.95,
                            'description' => 0.88,
                            'short_description' => 0.82,
                            'category_suggestion' => 0.74,
                            'tags' => 0.7,
                            'colors' => 0.6,
                            'material' => 0.5,
                            'dimensions' => 0.0,
                            'variations' => 0.5,
                            'condition' => 0.8,
                            'warnings' => 0.6,
                            'price' => 0.0,
                            'stock_quantity' => 0.0,
                            'unit' => 0.0,
                        ],
                        'overall_confidence' => 0.74,
                        'review_reasons' => ['price_missing', 'stock_missing'],
                    ],
                    provider: 'fake-vision',
                    model: 'fake-v1',
                    inputTokens: 120,
                    outputTokens: 80,
                    costUsd: 0.0012,
                    durationMs: 45,
                    rawResponse: ['secret_audit_marker' => 'raw-provider-only'],
                );
            }
        };

        (new ProcessCatalogImportItem((string) $tenant->id, (string) $item->id))->handle(
            $provider,
            app(SchemaValidatorInterface::class),
            app(CatalogVisionIngressGuard::class),
        );

        $item->refresh();
        self::assertSame(CatalogImportStatus::NeedsReview, $item->status);
        self::assertSame('Café alert(1) Premium', $item->normalized_payload['name']);
        self::assertSame('Café hondureñohostile()', $item->normalized_payload['description']);
        self::assertSame('Café premiumshort()', $item->normalized_payload['short_description']);
        self::assertSame(['café', 'artesanaltag()'], $item->normalized_payload['tags']);
        self::assertSame(['Café'], $item->normalized_payload['colors']);
        self::assertSame('Papel', $item->normalized_payload['material']);
        self::assertSame('Tamaño', $item->normalized_payload['variations'][0]['name']);
        self::assertSame(['12 ozoption()'], $item->normalized_payload['variations'][0]['options']);
        self::assertSame(['warning()Revisar etiqueta'], $item->normalized_payload['warnings']);
        self::assertNull($item->normalized_payload['price']);
        self::assertNull($item->normalized_payload['stock_quantity']);
        self::assertSame('fake-vision', $item->provider);
        self::assertSame('fake-v1', $item->provider_model);
        self::assertSame(0.74, (float) $item->overall_confidence);
        self::assertNotNull($item->processed_at);
        self::assertIsString($item->provider_response_ciphertext);
        self::assertStringNotContainsString('raw-provider-only', $item->provider_response_ciphertext);
        self::assertSame(0, Product::withoutGlobalScopes()->count());
    }

    public function test_a_late_provider_result_cannot_revive_an_item_cancelled_while_processing(): void
    {
        [$tenant, $item] = $this->queuedItem();
        $provider = new class($item) implements CatalogVisionProviderInterface
        {
            public function __construct(private readonly CatalogImportItem $item) {}

            public function analyze(CatalogVisionImage $image): CatalogVisionProviderResult
            {
                CatalogImportItem::query()->whereKey($this->item->id)->update([
                    'status' => CatalogImportStatus::Rejected->value,
                    'error_code' => 'batch_cancelled',
                ]);
                CatalogImportBatch::query()->whereKey($this->item->batch_id)->update([
                    'status' => CatalogImportStatus::Rejected->value,
                    'cancelled_at' => now(),
                ]);

                return new CatalogVisionProviderResult(
                    payload: [
                        'name' => 'Resultado tardío',
                        'description' => 'No debe persistirse.',
                        'short_description' => null,
                        'category_suggestion' => null,
                        'tags' => [],
                        'colors' => [],
                        'material' => null,
                        'dimensions' => null,
                        'variations' => [],
                        'condition' => null,
                        'warnings' => [],
                        'price' => null,
                        'stock_quantity' => null,
                        'unit' => null,
                        'field_confidence' => [
                            'name' => 0.9,
                            'description' => 0.8,
                            'short_description' => 0.0,
                            'category_suggestion' => 0.0,
                            'tags' => 0.0,
                            'colors' => 0.0,
                            'material' => 0.0,
                            'dimensions' => 0.0,
                            'variations' => 0.0,
                            'condition' => 0.0,
                            'warnings' => 0.0,
                            'price' => 0.0,
                            'stock_quantity' => 0.0,
                            'unit' => 0.0,
                        ],
                        'overall_confidence' => 0.8,
                        'review_reasons' => ['cancelled'],
                    ],
                    provider: 'fake-vision',
                    model: 'fake-v1',
                    inputTokens: 10,
                    outputTokens: 10,
                    costUsd: 0.0001,
                    durationMs: 10,
                    rawResponse: ['late' => true],
                );
            }
        };

        (new ProcessCatalogImportItem((string) $tenant->id, (string) $item->id))->handle(
            $provider,
            app(SchemaValidatorInterface::class),
            app(CatalogVisionIngressGuard::class),
        );

        $item->refresh();
        $item->batch->refresh();
        self::assertSame(CatalogImportStatus::Rejected, $item->status);
        self::assertNull($item->normalized_payload);
        self::assertSame(CatalogImportStatus::Rejected, $item->batch->status);
        self::assertNotNull($item->batch->cancelled_at);
    }

    public function test_job_cannot_process_an_item_using_another_tenants_context(): void
    {
        [$tenant, $item] = $this->queuedItem();
        $otherOwner = User::factory()->tenantOwner()->create();
        $otherTenant = Tenant::factory()->pro()->forUser($otherOwner)->create();
        $provider = new class implements CatalogVisionProviderInterface
        {
            public int $calls = 0;

            public function analyze(CatalogVisionImage $image): CatalogVisionProviderResult
            {
                $this->calls++;

                throw new \RuntimeException('Provider must not be called for a foreign tenant item.');
            }
        };

        (new ProcessCatalogImportItem((string) $otherTenant->id, (string) $item->id))->handle(
            $provider,
            app(SchemaValidatorInterface::class),
            app(CatalogVisionIngressGuard::class),
        );

        $item->refresh();
        self::assertSame(0, $provider->calls);
        self::assertSame(CatalogImportStatus::Queued, $item->status);
        self::assertSame((string) $tenant->id, (string) $item->tenant_id);
        self::assertSame(0, $item->attempts);
    }

    public function test_malformed_provider_result_fails_safely_and_preserves_encrypted_observability(): void
    {
        [$tenant, $item] = $this->queuedItem();
        $provider = new class implements CatalogVisionProviderInterface
        {
            public function analyze(CatalogVisionImage $image): CatalogVisionProviderResult
            {
                return new CatalogVisionProviderResult(
                    payload: ['name' => 'Incomplete payload'],
                    provider: 'fake-malformed',
                    model: 'broken-v2',
                    inputTokens: 33,
                    outputTokens: 9,
                    costUsd: 0.0042,
                    durationMs: 72,
                    rawResponse: ['secret_malformed_marker' => 'audit-only'],
                );
            }
        };

        (new ProcessCatalogImportItem((string) $tenant->id, (string) $item->id))->handle(
            $provider,
            app(SchemaValidatorInterface::class),
            app(CatalogVisionIngressGuard::class),
        );

        $item->refresh();
        self::assertSame(CatalogImportStatus::Failed, $item->status);
        self::assertSame('analysis_failed', $item->error_code);
        self::assertSame('fake-malformed', $item->provider);
        self::assertSame('broken-v2', $item->provider_model);
        self::assertSame(33, $item->input_tokens);
        self::assertSame(9, $item->output_tokens);
        self::assertSame(72, $item->duration_ms);
        self::assertNotNull($item->provider_response_ciphertext);
        self::assertStringNotContainsString('secret_malformed_marker', $item->provider_response_ciphertext);
        self::assertNull($item->normalized_payload);
        self::assertSame(0, Product::withoutGlobalScopes()->count());
        self::assertSame(CatalogImportStatus::Failed, $item->batch()->firstOrFail()->status);
    }

    public function test_prohibited_descriptor_fails_generically_before_storage_or_provider(): void
    {
        [$tenant, $item] = $this->queuedItem();
        $item->forceFill(['original_name' => 'sk-private-catalog-secret.jpg'])->save();
        Storage::disk('local')->delete($item->source_path);
        $provider = new CatalogVisionRecordingProvider;

        (new ProcessCatalogImportItem((string) $tenant->id, (string) $item->id))->handle(
            $provider,
            app(SchemaValidatorInterface::class),
            app(CatalogVisionIngressGuard::class),
        );

        $item->refresh();
        self::assertSame(0, $provider->calls);
        self::assertSame(CatalogImportStatus::Failed, $item->status);
        self::assertSame('catalog_vision_input_not_allowed', $item->error_code);
        self::assertSame('Catalog image input is not allowed.', $item->error_message);
        self::assertStringNotContainsString('sk-private', (string) $item->error_message);
        self::assertStringNotContainsString('secret_prohibited', (string) $item->error_message);
    }

    public function test_guard_exception_and_malformed_redaction_fail_unavailable_without_provider(): void
    {
        foreach ([
            new CatalogIngressRedactorStub(throwOnScrub: true),
            new CatalogIngressRedactorStub([
                'filename' => 'item.jpg',
                'mime' => 'image/jpeg',
                'size_bytes' => 16,
                'sha256' => hash('sha256', 'fake-image-bytes'),
                'unexpected' => 'raw-fallback',
            ]),
        ] as $redactor) {
            [$tenant, $item] = $this->queuedItem();
            Storage::disk('local')->delete($item->source_path);
            $provider = new CatalogVisionRecordingProvider;
            $ingress = new CatalogVisionIngressGuard(
                new DovraDataIngressGuard(
                    new DataClassificationPolicy($redactor),
                    new DovraIntegrationSeamRegistry,
                ),
            );

            (new ProcessCatalogImportItem((string) $tenant->id, (string) $item->id))->handle(
                $provider,
                app(SchemaValidatorInterface::class),
                $ingress,
            );

            $item->refresh();
            self::assertSame(0, $provider->calls);
            self::assertSame(CatalogImportStatus::Failed, $item->status);
            self::assertSame('catalog_vision_ingress_unavailable', $item->error_code);
            self::assertSame('Catalog image validation is not available.', $item->error_message);
            self::assertStringNotContainsString('RAW_INGRESS_EXCEPTION', (string) $item->error_message);
            self::assertStringNotContainsString('raw-fallback', (string) $item->error_message);

            CatalogImportItem::query()->whereKey($item->id)->delete();
            CatalogImportBatch::query()->whereKey($item->batch_id)->delete();
        }
    }

    public function test_provider_receives_only_redacted_descriptor_name_and_mime_with_stored_bytes(): void
    {
        [$tenant, $item] = $this->queuedItem();
        $provider = new CatalogVisionRecordingProvider;
        $redacted = [
            'filename' => 'redacted-name.jpg',
            'mime' => 'image/png',
            'size_bytes' => 16,
            'sha256' => hash('sha256', 'fake-image-bytes'),
        ];
        $ingress = new CatalogVisionIngressGuard(
            new DovraDataIngressGuard(
                new DataClassificationPolicy(new CatalogIngressRedactorStub($redacted)),
                new DovraIntegrationSeamRegistry,
            ),
        );

        (new ProcessCatalogImportItem((string) $tenant->id, (string) $item->id))->handle(
            $provider,
            app(SchemaValidatorInterface::class),
            $ingress,
        );

        self::assertSame(1, $provider->calls);
        self::assertInstanceOf(CatalogVisionImage::class, $provider->image);
        self::assertSame('redacted-name.jpg', $provider->image->originalName);
        self::assertSame('image/png', $provider->image->mimeType);
        self::assertSame('fake-image-bytes', $provider->image->contents);
        self::assertNotSame($item->original_name, $provider->image->originalName);
    }

    /** @return array{Tenant, CatalogImportItem} */
    private function queuedItem(): array
    {
        Storage::fake('local');
        $owner = User::factory()->tenantOwner()->create();
        $tenant = Tenant::factory()->pro()->forUser($owner)->create();
        $context = app(TenantContext::class);
        $context->reset();
        $context->setTenant((string) $tenant->id);
        app()->instance('currentTenant', $tenant);

        $batch = new CatalogImportBatch;
        $batch->forceFill([
            'tenant_id' => (string) $tenant->id,
            'created_by' => (string) $owner->id,
            'status' => CatalogImportStatus::Queued,
            'total_items' => 1,
        ])->save();

        $path = "catalog-imports/{$tenant->id}/{$batch->id}/item.jpg";
        Storage::disk('local')->put($path, 'fake-image-bytes');
        $item = new CatalogImportItem;
        $item->forceFill([
            'tenant_id' => (string) $tenant->id,
            'batch_id' => (string) $batch->id,
            'status' => CatalogImportStatus::Queued,
            'source_disk' => 'local',
            'source_path' => $path,
            'original_name' => 'item.jpg',
            'mime_type' => 'image/jpeg',
            'size_bytes' => 16,
            'sha256' => hash('sha256', 'fake-image-bytes'),
        ])->save();

        self::assertSame(1, DB::table('catalog_import_items')->count());

        return [$tenant, $item];
    }
}

final class CatalogVisionRecordingProvider implements CatalogVisionProviderInterface
{
    public int $calls = 0;

    public ?CatalogVisionImage $image = null;

    public function analyze(CatalogVisionImage $image): CatalogVisionProviderResult
    {
        $this->calls++;
        $this->image = $image;

        throw new \RuntimeException('Provider recording stop.');
    }
}

final class CatalogIngressRedactorStub implements SensitivePayloadRedactor
{
    /** @param array<string, mixed>|null $replacement */
    public function __construct(
        private readonly ?array $replacement = null,
        private readonly bool $throwOnScrub = false,
    ) {}

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    public function scrub(array $payload): array
    {
        if ($this->throwOnScrub) {
            throw new \RuntimeException('RAW_INGRESS_EXCEPTION');
        }

        return $this->replacement ?? $payload;
    }
}
