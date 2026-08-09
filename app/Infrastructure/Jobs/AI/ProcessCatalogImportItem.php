<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\AI;

use App\Application\Ai\Catalog\CatalogVisionImage;
use App\Application\Ai\Catalog\CatalogVisionIngressGuard;
use App\Application\Ai\Catalog\CatalogVisionProviderInterface;
use App\Application\Ai\Catalog\CatalogVisionProviderResult;
use App\Application\Shared\Contracts\SchemaValidatorInterface;
use App\Domain\Catalog\Enums\CatalogImportStatus;
use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use App\Models\CatalogImportBatch;
use App\Models\CatalogImportItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class ProcessCatalogImportItem implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $itemId,
    ) {}

    /** @return array<int, TenantQueueMiddleware> */
    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->tenantId)];
    }

    public function handle(
        CatalogVisionProviderInterface $provider,
        SchemaValidatorInterface $schemaValidator,
        CatalogVisionIngressGuard $catalogIngress,
    ): void {
        /** @var array{item:CatalogImportItem,actor_id:string}|null $work */
        $work = DB::transaction(function (): ?array {
            $candidate = CatalogImportItem::query()
                ->where('tenant_id', $this->tenantId)
                ->whereKey($this->itemId)
                ->lockForUpdate()
                ->first();

            if ($candidate === null || $candidate->status !== CatalogImportStatus::Queued) {
                return null;
            }

            $batch = CatalogImportBatch::query()
                ->where('tenant_id', $this->tenantId)
                ->whereKey((string) $candidate->batch_id)
                ->lockForUpdate()
                ->first();
            if ($batch === null || $batch->cancelled_at !== null) {
                return null;
            }

            $candidate->forceFill([
                'status' => CatalogImportStatus::Processing,
                'attempts' => $candidate->attempts + 1,
                'error_code' => null,
                'error_message' => null,
            ])->save();

            return [
                'item' => $candidate,
                'actor_id' => (string) $batch->created_by,
            ];
        });

        if ($work === null) {
            return;
        }
        $item = $work['item'];
        $descriptor = [
            'filename' => $item->original_name,
            'mime' => $item->mime_type,
            'size_bytes' => $item->size_bytes,
            'sha256' => $item->sha256,
        ];

        try {
            $decision = $catalogIngress->inspect(
                $this->tenantId,
                $work['actor_id'],
                $descriptor,
            );
        } catch (Throwable) {
            $this->persistIngressFailure(
                $item,
                'catalog_vision_ingress_unavailable',
                'Catalog image validation is not available.',
            );

            return;
        }
        if (! $decision->allowed) {
            $unavailable = array_intersect(
                ['entrypoint_not_registered', 'tenant_context_required'],
                $decision->reasons,
            ) !== [];
            $this->persistIngressFailure(
                $item,
                $unavailable ? 'catalog_vision_ingress_unavailable' : 'catalog_vision_input_not_allowed',
                $unavailable ? 'Catalog image validation is not available.' : 'Catalog image input is not allowed.',
            );

            return;
        }
        $redactedDescriptor = $catalogIngress->validatedDescriptor($decision);
        if ($redactedDescriptor === null) {
            $this->persistIngressFailure(
                $item,
                'catalog_vision_ingress_unavailable',
                'Catalog image validation is not available.',
            );

            return;
        }

        $result = null;
        try {
            $contents = Storage::disk($item->source_disk)->get($item->source_path);
            $result = $provider->analyze(new CatalogVisionImage(
                tenantId: $this->tenantId,
                itemId: $this->itemId,
                originalName: $redactedDescriptor['filename'],
                mimeType: $redactedDescriptor['mime'],
                contents: $contents,
            ));

            $schemaValidator->validate('catalog.product.vision.v1', $result->payload);
            $payload = $this->sanitizePayload($result->payload);
            $schemaValidator->validate('catalog.product.vision.v1', $payload);

            $ciphertext = Crypt::encryptString((string) json_encode(
                $result->rawResponse,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
            ));
            $persisted = DB::transaction(function () use ($ciphertext, $item, $payload, $result): bool {
                $current = CatalogImportItem::query()
                    ->where('tenant_id', $this->tenantId)
                    ->whereKey($this->itemId)
                    ->lockForUpdate()
                    ->first();
                $batch = CatalogImportBatch::query()
                    ->where('tenant_id', $this->tenantId)
                    ->whereKey((string) $item->batch_id)
                    ->lockForUpdate()
                    ->first();

                if ($current === null
                    || $current->status !== CatalogImportStatus::Processing
                    || $batch === null
                    || $batch->cancelled_at !== null) {
                    return false;
                }

                $current->forceFill([
                    'status' => CatalogImportStatus::NeedsReview,
                    'normalized_payload' => $payload,
                    'field_confidence' => $payload['field_confidence'],
                    'overall_confidence' => $payload['overall_confidence'],
                    'provider' => Str::limit($result->provider, 80, ''),
                    'provider_model' => Str::limit($result->model, 120, ''),
                    'input_tokens' => max(0, $result->inputTokens),
                    'output_tokens' => max(0, $result->outputTokens),
                    'cost_usd' => max(0, $result->costUsd),
                    'duration_ms' => max(0, $result->durationMs),
                    'provider_response_ciphertext' => $ciphertext,
                    'processed_at' => now(),
                ])->save();

                return true;
            });
        } catch (Throwable $exception) {
            /** @var array<string, mixed> $failureTelemetry */
            $failureTelemetry = [];
            if ($result instanceof CatalogVisionProviderResult) {
                $encodedRawResponse = json_encode($result->rawResponse, JSON_UNESCAPED_UNICODE);
                $failureTelemetry = [
                    'provider' => Str::limit($result->provider, 80, ''),
                    'provider_model' => Str::limit($result->model, 120, ''),
                    'input_tokens' => max(0, $result->inputTokens),
                    'output_tokens' => max(0, $result->outputTokens),
                    'cost_usd' => max(0, $result->costUsd),
                    'duration_ms' => max(0, $result->durationMs),
                    'provider_response_ciphertext' => is_string($encodedRawResponse)
                        ? Crypt::encryptString($encodedRawResponse)
                        : null,
                ];
            }

            $persisted = DB::transaction(function () use ($exception, $failureTelemetry, $item): bool {
                $current = CatalogImportItem::query()
                    ->where('tenant_id', $this->tenantId)
                    ->whereKey($this->itemId)
                    ->lockForUpdate()
                    ->first();
                $batch = CatalogImportBatch::query()
                    ->where('tenant_id', $this->tenantId)
                    ->whereKey((string) $item->batch_id)
                    ->lockForUpdate()
                    ->first();

                if ($current === null
                    || $current->status !== CatalogImportStatus::Processing
                    || $batch === null
                    || $batch->cancelled_at !== null) {
                    return false;
                }

                $current->forceFill(array_merge([
                    'status' => CatalogImportStatus::Failed,
                    'error_code' => 'analysis_failed',
                    'error_message' => Str::limit($exception->getMessage(), 1000, ''),
                    'processed_at' => now(),
                ], $failureTelemetry))->save();

                return true;
            });
        }

        if (! $persisted) {
            return;
        }

        $this->refreshBatch((string) $item->batch_id);
    }

    private function persistIngressFailure(CatalogImportItem $item, string $code, string $message): void
    {
        $persisted = DB::transaction(function () use ($code, $item, $message): bool {
            $current = CatalogImportItem::query()
                ->where('tenant_id', $this->tenantId)
                ->whereKey($this->itemId)
                ->lockForUpdate()
                ->first();
            $batch = CatalogImportBatch::query()
                ->where('tenant_id', $this->tenantId)
                ->whereKey((string) $item->batch_id)
                ->lockForUpdate()
                ->first();

            if ($current === null
                || $current->status !== CatalogImportStatus::Processing
                || $batch === null
                || $batch->cancelled_at !== null) {
                return false;
            }

            $current->forceFill([
                'status' => CatalogImportStatus::Failed,
                'error_code' => $code,
                'error_message' => $message,
                'processed_at' => now(),
            ])->save();

            return true;
        });

        if ($persisted) {
            $this->refreshBatch((string) $item->batch_id);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizePayload(array $payload): array
    {
        $payload['name'] = $this->plainText((string) $payload['name'], 160);
        $payload['description'] = $this->plainText((string) $payload['description'], 2000);
        $payload['short_description'] = $payload['short_description'] === null
            ? null
            : $this->nullablePlainText((string) $payload['short_description'], 320);
        $payload['category_suggestion'] = $payload['category_suggestion'] === null
            ? null
            : $this->nullablePlainText((string) $payload['category_suggestion'], 100);
        $payload['tags'] = $this->plainTextList($payload['tags'], 50, 20);
        $payload['colors'] = $this->plainTextList($payload['colors'], 40, 12);
        $payload['material'] = $payload['material'] === null
            ? null
            : $this->nullablePlainText((string) $payload['material'], 100);
        $payload['variations'] = array_map(fn (array $variation): array => [
            'name' => $this->plainText((string) $variation['name'], 60),
            'options' => $this->plainTextList($variation['options'], 60, 20),
        ], $payload['variations']);
        $payload['warnings'] = $this->plainTextList($payload['warnings'], 160, 20);
        $payload['unit'] = $payload['unit'] === null
            ? null
            : $this->nullablePlainText((string) $payload['unit'], 30);
        $payload['review_reasons'] = $this->plainTextList($payload['review_reasons'], 80, 20);

        return $payload;
    }

    private function plainText(string $value, int $limit): string
    {
        return Str::limit(trim((string) preg_replace('/\s+/u', ' ', strip_tags($value))), $limit, '');
    }

    private function nullablePlainText(string $value, int $limit): ?string
    {
        $clean = $this->plainText($value, $limit);

        return $clean === '' ? null : $clean;
    }

    /**
     * @param  array<int, mixed>  $values
     * @return array<int, string>
     */
    private function plainTextList(array $values, int $itemLimit, int $listLimit): array
    {
        $sanitized = array_map(
            fn (mixed $value): string => $this->plainText((string) $value, $itemLimit),
            array_slice($values, 0, $listLimit),
        );

        return array_values(array_unique(array_filter(
            $sanitized,
            static fn (string $value): bool => $value !== '',
        )));
    }

    private function refreshBatch(string $batchId): void
    {
        $batch = CatalogImportBatch::query()
            ->where('tenant_id', $this->tenantId)
            ->whereKey($batchId)
            ->first();

        if ($batch === null) {
            return;
        }

        if ($batch->cancelled_at !== null) {
            return;
        }

        $processed = $batch->items()
            ->whereIn('status', [
                CatalogImportStatus::NeedsReview->value,
                CatalogImportStatus::Approved->value,
                CatalogImportStatus::Published->value,
                CatalogImportStatus::Failed->value,
                CatalogImportStatus::Rejected->value,
            ])
            ->count();
        $failed = $batch->items()->where('status', CatalogImportStatus::Failed->value)->count();
        $reviewable = $batch->items()
            ->whereIn('status', [
                CatalogImportStatus::NeedsReview->value,
                CatalogImportStatus::Approved->value,
            ])
            ->count();
        $published = $batch->items()->where('status', CatalogImportStatus::Published->value)->count();
        $rejected = $batch->items()->where('status', CatalogImportStatus::Rejected->value)->count();
        $pending = $batch->items()
            ->whereIn('status', [
                CatalogImportStatus::Uploaded->value,
                CatalogImportStatus::Queued->value,
                CatalogImportStatus::Processing->value,
            ])
            ->exists();
        $status = match (true) {
            $pending => CatalogImportStatus::Processing,
            $reviewable > 0 => CatalogImportStatus::NeedsReview,
            $failed > 0 => CatalogImportStatus::Failed,
            $published > 0 => CatalogImportStatus::Published,
            $rejected >= $batch->total_items => CatalogImportStatus::Rejected,
            default => CatalogImportStatus::NeedsReview,
        };

        $batch->forceFill([
            'status' => $status,
            'processed_items' => $processed,
            'failed_items' => $failed,
            'completed_at' => $pending ? null : now(),
        ])->save();
    }
}
