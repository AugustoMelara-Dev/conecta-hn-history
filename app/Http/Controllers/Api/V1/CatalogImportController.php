<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Application\Ai\Catalog\CatalogImportPublisherInterface;
use App\Application\Products\CanonicalProductWriter;
use App\Application\Products\Commands\CreateCanonicalProductCommand;
use App\Domain\Access\Enums\Permission;
use App\Domain\Catalog\Enums\CatalogImportStatus;
use App\Domain\Shared\Services\TenantContext;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Domain\TenantGovernance\Services\PlanFeatureFlagService;
use App\Http\Controllers\Controller;
use App\Infrastructure\Jobs\AI\ProcessCatalogImportItem;
use App\Infrastructure\Security\SafeCatalogImageNormalizer;
use App\Models\CatalogImportBatch;
use App\Models\CatalogImportItem;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

final class CatalogImportController extends Controller
{
    public function __construct(
        private readonly TenantContext $tenantContext,
        private readonly TenantPermissionGuardInterface $permissionGuard,
        private readonly CatalogImportPublisherInterface $publisher,
        private readonly CanonicalProductWriter $productWriter,
        private readonly PlanFeatureFlagService $planFeatures,
        private readonly SafeCatalogImageNormalizer $imageNormalizer,
    ) {}

    public function show(Request $request, string $batchId): JsonResponse
    {
        $tenant = $this->authorizedTenant($request, Permission::CATALOG_VIEW);
        $batch = CatalogImportBatch::query()
            ->whereKey($batchId)
            ->where('tenant_id', (string) $tenant->getKey())
            ->with(['items' => static fn ($query) => $query->orderBy('created_at')->orderBy('id')])
            ->firstOrFail();

        return response()->json(['data' => $this->batchDetailPayload($batch)]);
    }

    public function source(Request $request, string $batchId, string $itemId): StreamedResponse
    {
        $tenant = $this->authorizedTenant($request, Permission::CATALOG_VIEW);
        $tenantId = (string) $tenant->getKey();
        $batch = CatalogImportBatch::query()
            ->whereKey($batchId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();
        $item = CatalogImportItem::query()
            ->whereKey($itemId)
            ->where('batch_id', (string) $batch->getKey())
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        abort_unless(
            $item->source_disk === 'local'
            && str_starts_with($item->source_path, "catalog-imports/{$tenantId}/{$batch->getKey()}/")
            && in_array($item->mime_type, ['image/jpeg', 'image/png', 'image/webp'], true)
            && Storage::disk('local')->exists($item->source_path),
            404,
        );

        $filename = preg_replace('/[^A-Za-z0-9._-]/', '-', Str::ascii(basename($item->original_name))) ?: 'imagen';

        return Storage::disk('local')->response($item->source_path, $filename, [
            'Content-Type' => $item->mime_type,
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $tenant = $this->authorizedTenant($request, Permission::AI_USE, Capability::AI_CONTENT);
        $tenantId = (string) $tenant->getKey();
        // INFRA_TENANT_FILTER: capacity counts hidden drafts too and remains bound to the authorized tenant UUID.
        $productCount = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->count();
        $draftCount = CatalogImportItem::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', [
                CatalogImportStatus::Uploaded->value,
                CatalogImportStatus::Queued->value,
                CatalogImportStatus::Processing->value,
                CatalogImportStatus::NeedsReview->value,
            ])
            ->count();
        $remainingCapacity = max(0, $this->planFeatures->getMaxProductLimit($tenant) - $productCount - $draftCount);
        $requestLimit = min(20, $this->planFeatures->getMaxMediaUploads($tenant), $remainingCapacity);
        if ($requestLimit === 0) {
            throw ValidationException::withMessages([
                'images' => ['Tu negocio alcanzó el límite de productos de su plan.'],
            ]);
        }

        $validated = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:'.$requestLimit],
            'images.*' => ['required', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:10240'],
        ]);

        $userId = (string) $request->user()->getAuthIdentifier();
        /** @var array<int, UploadedFile> $images */
        $images = $validated['images'];
        /** @var array<int, string> $storedPaths */
        $storedPaths = [];

        try {
            /** @var CatalogImportBatch $batch */
            $batch = DB::transaction(function () use ($images, $tenantId, $userId, &$storedPaths): CatalogImportBatch {
                $batch = new CatalogImportBatch;
                $batch->forceFill([
                    'tenant_id' => $tenantId,
                    'created_by' => $userId,
                    'status' => CatalogImportStatus::Uploaded,
                    'total_items' => count($images),
                ])->save();

                foreach ($images as $index => $image) {
                    $itemId = (string) Str::uuid();
                    try {
                        $normalizedImage = $this->imageNormalizer->normalize($image->getContent());
                    } catch (\InvalidArgumentException) {
                        throw ValidationException::withMessages([
                            "images.{$index}" => ['El archivo no contiene una imagen segura y válida.'],
                        ]);
                    }
                    $contents = $normalizedImage['contents'];
                    $detectedMime = $normalizedImage['mime'];
                    $extension = match ($detectedMime) {
                        'image/jpeg' => 'jpg',
                        'image/png' => 'png',
                        'image/webp' => 'webp',
                        default => throw ValidationException::withMessages([
                            "images.{$index}" => ['El archivo no contiene una imagen JPEG, PNG o WebP válida.'],
                        ]),
                    };
                    $path = "catalog-imports/{$tenantId}/{$batch->getKey()}/{$itemId}.{$extension}";
                    Storage::disk('local')->put($path, $contents);
                    $storedPaths[] = $path;

                    $sha256 = hash('sha256', $contents);
                    $duplicate = CatalogImportItem::query()
                        ->where('tenant_id', $tenantId)
                        ->where('sha256', $sha256)
                        ->whereNotIn('status', [
                            CatalogImportStatus::Failed->value,
                            CatalogImportStatus::Rejected->value,
                        ])
                        ->first(['id']);

                    $item = new CatalogImportItem;
                    $item->setAttribute('id', $itemId);
                    $item->forceFill([
                        'tenant_id' => $tenantId,
                        'batch_id' => $batch->getKey(),
                        'duplicate_of_id' => $duplicate?->getKey(),
                        'status' => $duplicate === null
                            ? CatalogImportStatus::Queued
                            : CatalogImportStatus::Rejected,
                        'source_disk' => 'local',
                        'source_path' => $path,
                        'original_name' => Str::limit($image->getClientOriginalName(), 255, ''),
                        'mime_type' => $detectedMime,
                        'size_bytes' => strlen($contents),
                        'sha256' => $sha256,
                        'error_code' => $duplicate === null ? null : 'duplicate_image',
                        'error_message' => $duplicate === null ? null : 'La imagen ya existe en este catálogo.',
                    ])->save();
                }

                $batch->forceFill(['status' => CatalogImportStatus::Queued])->save();

                return $batch;
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        $batch->load('items');
        foreach ($batch->items as $item) {
            if ($item->status !== CatalogImportStatus::Queued) {
                continue;
            }

            ProcessCatalogImportItem::dispatch($tenantId, (string) $item->getKey());
        }

        return response()->json(['data' => $this->batchPayload($batch)], 201);
    }

    public function update(Request $request, string $batchId, string $itemId): JsonResponse
    {
        $tenant = $this->authorizedTenant($request, Permission::CATALOG_UPDATE);
        $tenantId = (string) $tenant->getKey();
        $batch = CatalogImportBatch::query()
            ->whereKey($batchId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();
        $item = CatalogImportItem::query()
            ->whereKey($itemId)
            ->where('batch_id', (string) $batch->getKey())
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        abort_unless($item->status === CatalogImportStatus::NeedsReview, 409, 'El producto ya no está pendiente de revisión.');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'short_description' => ['nullable', 'string', 'max:320'],
            'category_id' => [
                'nullable',
                'integer',
                Rule::exists('categories', 'id')->where('is_active', true),
            ],
            'category_suggestion' => ['nullable', 'string', 'max:100'],
            'tags' => ['sometimes', 'array', 'max:20'],
            'tags.*' => ['required', 'string', 'max:50', 'distinct'],
            'colors' => ['sometimes', 'array', 'max:12'],
            'colors.*' => ['required', 'string', 'max:40', 'distinct'],
            'material' => ['nullable', 'string', 'max:100'],
            'dimensions' => ['nullable', 'array:width,height,depth,unit'],
            'dimensions.width' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'dimensions.height' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'dimensions.depth' => ['nullable', 'numeric', 'gt:0', 'max:100000'],
            'dimensions.unit' => ['nullable', Rule::in(['mm', 'cm', 'm', 'in'])],
            'variations' => ['sometimes', 'array', 'max:12'],
            'variations.*.name' => ['required', 'string', 'max:60'],
            'variations.*.options' => ['required', 'array', 'min:1', 'max:20'],
            'variations.*.options.*' => ['required', 'string', 'max:60', 'distinct'],
            'condition' => ['nullable', Rule::in(['new', 'used', 'refurbished'])],
            'warnings' => ['sometimes', 'array', 'max:20'],
            'warnings.*' => ['required', 'string', 'max:160', 'distinct'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:9999999.99'],
            'stock_quantity' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'unit' => ['nullable', 'string', 'max:30'],
        ]);

        $basePayload = is_array($item->review_payload)
            ? $item->review_payload
            : (is_array($item->normalized_payload) ? $item->normalized_payload : []);
        $review = [
            'name' => $this->sanitizeText((string) $validated['name'], 160),
            'description' => $this->sanitizeNullableText($validated['description'] ?? null, 2000),
            'short_description' => $this->sanitizeNullableText(
                $validated['short_description'] ?? ($basePayload['short_description'] ?? null),
                320,
            ),
            'category_id' => $validated['category_id'] ?? null,
            'category_suggestion' => $this->sanitizeNullableText($validated['category_suggestion'] ?? null, 100),
            'tags' => $this->sanitizeTextList($validated['tags'] ?? ($basePayload['tags'] ?? []), 50, 20),
            'colors' => $this->sanitizeTextList($validated['colors'] ?? ($basePayload['colors'] ?? []), 40, 12),
            'material' => $this->sanitizeNullableText(
                $validated['material'] ?? ($basePayload['material'] ?? null),
                100,
            ),
            'dimensions' => $this->normalizeDimensions($validated['dimensions'] ?? ($basePayload['dimensions'] ?? null)),
            'variations' => $this->normalizeVariations($validated['variations'] ?? ($basePayload['variations'] ?? [])),
            'condition' => $validated['condition'] ?? ($basePayload['condition'] ?? null),
            'warnings' => $this->sanitizeTextList($validated['warnings'] ?? ($basePayload['warnings'] ?? []), 160, 20),
            'price_minor' => isset($validated['price'])
                ? $this->minorHnl((string) $validated['price'])
                : null,
            'stock_quantity' => isset($validated['stock_quantity']) ? (int) $validated['stock_quantity'] : null,
            'unit' => $this->sanitizeNullableText($validated['unit'] ?? null, 30),
        ];

        $item->forceFill([
            'review_payload' => $review,
            'reviewed_by' => (string) $request->user()->getAuthIdentifier(),
            'reviewed_at' => now(),
        ])->save();

        return response()->json([
            'data' => [
                'id' => (string) $item->getKey(),
                'status' => $item->status->value,
                'review' => $this->reviewResponsePayload($review),
            ],
        ]);
    }

    public function approve(Request $request, string $batchId, string $itemId): JsonResponse
    {
        $tenant = $this->authorizedTenant($request, Permission::CATALOG_CREATE);
        $tenantId = (string) $tenant->getKey();
        $batch = CatalogImportBatch::query()
            ->whereKey($batchId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();
        $item = CatalogImportItem::query()
            ->whereKey($itemId)
            ->where('batch_id', (string) $batch->getKey())
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        /** @var CatalogImportItem $item */
        $item = DB::transaction(function () use ($batch, $itemId, $request, $tenantId): CatalogImportItem {
            $lockedItem = CatalogImportItem::query()
                ->whereKey($itemId)
                ->where('batch_id', (string) $batch->getKey())
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedItem->status === CatalogImportStatus::Approved && $lockedItem->product_id !== null) {
                return $lockedItem;
            }

            abort_unless($lockedItem->status === CatalogImportStatus::NeedsReview, 409, 'El producto ya no está pendiente de revisión.');

            $lockedReview = $lockedItem->review_payload;
            $lockedErrors = $this->commercialReviewErrors($lockedReview);
            if ($lockedErrors !== []) {
                throw ValidationException::withMessages($lockedErrors);
            }

            /** @var array<string, mixed> $lockedReview */
            $name = (string) $lockedReview['name'];
            $product = $this->productWriter->create(new CreateCanonicalProductCommand(
                tenantId: $tenantId,
                idempotencyKey: 'catalog-import:'.$batch->getKey().':'.$lockedItem->getKey(),
                name: $name,
                priceMinor: isset($lockedReview['price_minor'])
                    ? (int) $lockedReview['price_minor']
                    : $this->minorHnl((string) $lockedReview['price']),
                initialStockQuantity: (int) $lockedReview['stock_quantity'],
                attributes: [
                    'category_id' => $lockedReview['category_id'] ?? null,
                    'description' => $lockedReview['description'] ?? null,
                    'short_description' => $lockedReview['short_description'] ?? null,
                    'tags' => $lockedReview['tags'] ?? [],
                    'colors' => $lockedReview['colors'] ?? [],
                    'material' => $lockedReview['material'] ?? null,
                    'dimensions' => $lockedReview['dimensions'] ?? null,
                    'variants' => $lockedReview['variations'] ?? [],
                    'condition' => $lockedReview['condition'] ?? null,
                    'warnings' => $lockedReview['warnings'] ?? [],
                    'unit' => $lockedReview['unit'] ?? null,
                    'is_new' => true,
                ],
            ));

            $lockedItem->forceFill([
                'product_id' => $product->getKey(),
                'status' => CatalogImportStatus::Approved,
                'reviewed_by' => (string) $request->user()->getAuthIdentifier(),
                'reviewed_at' => now(),
            ])->save();

            return $lockedItem;
        });

        return response()->json([
            'data' => [
                'id' => (string) $item->getKey(),
                'status' => $item->status->value,
                'product_id' => $item->product_id,
            ],
        ]);
    }

    public function publish(Request $request, string $batchId): JsonResponse
    {
        $tenant = $this->authorizedTenant($request, Permission::CATALOG_PUBLISH);
        $result = $this->publisher->publish((string) $tenant->getKey(), $batchId);

        return response()->json([
            'data' => [
                'id' => $result->batchId,
                'status' => $result->status->value,
                'published_items' => $result->publishedItems,
            ],
        ]);
    }

    public function reject(Request $request, string $batchId, string $itemId): JsonResponse
    {
        $tenant = $this->authorizedTenant($request, Permission::CATALOG_UPDATE);
        $tenantId = (string) $tenant->getKey();
        $batch = CatalogImportBatch::query()
            ->whereKey($batchId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        /** @var CatalogImportItem $item */
        $item = DB::transaction(function () use ($batch, $itemId, $tenantId): CatalogImportItem {
            $lockedItem = CatalogImportItem::query()
                ->whereKey($itemId)
                ->where('batch_id', (string) $batch->getKey())
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedItem->status === CatalogImportStatus::Rejected) {
                return $lockedItem;
            }

            abort_unless(in_array($lockedItem->status, [
                CatalogImportStatus::NeedsReview,
                CatalogImportStatus::Approved,
            ], true), 409, 'El producto ya no puede rechazarse.');

            if ($lockedItem->product_id !== null) {
                // INFRA_TENANT_FILTER: hidden draft deletion is constrained by product ID and authorized tenant UUID.
                $this->productWriter->delete($tenantId, (int) $lockedItem->product_id);
            }

            $lockedItem->forceFill([
                'product_id' => null,
                'status' => CatalogImportStatus::Rejected,
                'reviewed_at' => now(),
            ])->save();

            return $lockedItem;
        });

        if ($item->source_disk === 'local') {
            Storage::disk('local')->delete($item->source_path);
        }

        return response()->json([
            'data' => [
                'id' => (string) $item->getKey(),
                'status' => $item->status->value,
            ],
        ]);
    }

    public function retry(Request $request, string $batchId, string $itemId): JsonResponse
    {
        $tenant = $this->authorizedTenant($request, Permission::CATALOG_UPDATE);
        $tenantId = (string) $tenant->getKey();
        $batch = CatalogImportBatch::query()
            ->whereKey($batchId)
            ->where('tenant_id', $tenantId)
            ->firstOrFail();

        /** @var array{CatalogImportItem, bool} $retry */
        $retry = DB::transaction(function () use ($batch, $itemId, $tenantId): array {
            $lockedItem = CatalogImportItem::query()
                ->whereKey($itemId)
                ->where('batch_id', (string) $batch->getKey())
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedItem->status === CatalogImportStatus::Queued) {
                return [$lockedItem, false];
            }

            abort_unless($lockedItem->status === CatalogImportStatus::Failed, 409, 'Solo se pueden reintentar elementos fallidos.');
            abort_unless(
                $lockedItem->source_disk === 'local'
                && str_starts_with($lockedItem->source_path, "catalog-imports/{$tenantId}/{$batch->getKey()}/")
                && Storage::disk('local')->exists($lockedItem->source_path),
                409,
                'La imagen privada ya no está disponible para reintento.',
            );

            $lockedItem->forceFill([
                'status' => CatalogImportStatus::Queued,
                'error_code' => null,
                'error_message' => null,
                'processed_at' => null,
            ])->save();
            $batch->forceFill([
                'status' => CatalogImportStatus::Queued,
                'completed_at' => null,
            ])->save();

            return [$lockedItem, true];
        });

        [$item, $shouldDispatch] = $retry;
        if ($shouldDispatch) {
            ProcessCatalogImportItem::dispatch($tenantId, (string) $item->getKey());
        }

        return response()->json([
            'data' => [
                'id' => (string) $item->getKey(),
                'status' => $item->status->value,
            ],
        ]);
    }

    public function cancel(Request $request, string $batchId): JsonResponse
    {
        $tenant = $this->authorizedTenant($request, Permission::CATALOG_UPDATE);
        $tenantId = (string) $tenant->getKey();

        /** @var array{CatalogImportBatch, array<int, string>} $cancellation */
        $cancellation = DB::transaction(function () use ($batchId, $tenantId): array {
            $batch = CatalogImportBatch::query()
                ->whereKey($batchId)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($batch->cancelled_at !== null) {
                return [$batch, []];
            }

            $items = CatalogImportItem::query()
                ->where('batch_id', (string) $batch->getKey())
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->get();
            abort_if(
                $items->contains(static fn (CatalogImportItem $item): bool => $item->status === CatalogImportStatus::Published),
                409,
                'Un lote con productos publicados ya no puede cancelarse.',
            );

            $paths = [];
            foreach ($items as $item) {
                if ($item->product_id !== null) {
                    // INFRA_TENANT_FILTER: cancellation removes only hidden drafts owned by this locked tenant batch.
                    $this->productWriter->delete($tenantId, (int) $item->product_id);
                }

                if ($item->source_disk === 'local'
                    && str_starts_with($item->source_path, "catalog-imports/{$tenantId}/{$batch->getKey()}/")) {
                    $paths[] = $item->source_path;
                }

                $item->forceFill([
                    'product_id' => null,
                    'status' => CatalogImportStatus::Rejected,
                    'error_code' => 'batch_cancelled',
                    'error_message' => 'El lote fue cancelado por el usuario.',
                ])->save();
            }

            $batch->forceFill([
                'status' => CatalogImportStatus::Rejected,
                'cancelled_at' => now(),
                'completed_at' => now(),
            ])->save();

            return [$batch, $paths];
        });

        [$batch, $paths] = $cancellation;
        foreach ($paths as $path) {
            Storage::disk('local')->delete($path);
        }

        return response()->json([
            'data' => [
                'id' => (string) $batch->getKey(),
                'status' => $batch->status->value,
            ],
        ]);
    }

    /** @return array<string, mixed> */
    private function batchPayload(CatalogImportBatch $batch): array
    {
        return [
            'id' => (string) $batch->getKey(),
            'status' => $batch->status->value,
            'total_items' => $batch->total_items,
            'items' => $batch->items->map(static fn (CatalogImportItem $item): array => [
                'id' => (string) $item->getKey(),
                'status' => $item->status->value,
                'original_name' => $item->original_name,
            ])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function batchDetailPayload(CatalogImportBatch $batch): array
    {
        return [
            'id' => (string) $batch->getKey(),
            'status' => $batch->status->value,
            'total_items' => $batch->total_items,
            'processed_items' => $batch->processed_items,
            'failed_items' => $batch->failed_items,
            'completed_at' => $batch->completed_at?->toIso8601String(),
            'cancelled_at' => $batch->cancelled_at?->toIso8601String(),
            'items' => $batch->items->map(fn (CatalogImportItem $item): array => [
                'id' => (string) $item->getKey(),
                'status' => $item->status->value,
                'original_name' => $item->original_name,
                'mime_type' => $item->mime_type,
                'size_bytes' => $item->size_bytes,
                'duplicate_of_id' => $item->duplicate_of_id,
                'product_id' => $item->product_id,
                'suggestion' => $item->normalized_payload,
                'review' => $this->reviewResponsePayload($item->review_payload),
                'field_confidence' => $item->field_confidence,
                'overall_confidence' => $item->overall_confidence === null
                    ? null
                    : (float) $item->overall_confidence,
                'error' => $item->error_code === null ? null : [
                    'code' => $item->error_code,
                    'message' => $this->publicErrorMessage($item->error_code),
                ],
                'processed_at' => $item->processed_at?->toIso8601String(),
                'reviewed_at' => $item->reviewed_at?->toIso8601String(),
                'published_at' => $item->published_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    private function authorizedTenant(
        Request $request,
        Permission $permission,
        Capability $capability = Capability::CATALOG_BASIC,
    ): Tenant {
        $tenant = Tenant::query()
            ->whereKey((string) $this->tenantContext->id())
            ->where('is_active', true)
            ->firstOrFail();

        abort_unless($this->permissionGuard->allows(
            $request->user(),
            $tenant,
            $capability,
            $permission,
        ), 403);

        return $tenant;
    }

    private function sanitizeNullableText(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $sanitized = $this->sanitizeText($value, $maxLength);

        return $sanitized === '' ? null : $sanitized;
    }

    private function sanitizeText(string $value, int $maxLength): string
    {
        $plainText = strip_tags($value);
        $collapsed = preg_replace('/\s+/u', ' ', $plainText) ?? '';

        return Str::limit(trim($collapsed), $maxLength, '');
    }

    /** @return array<int, string> */
    private function sanitizeTextList(mixed $values, int $itemLimit, int $listLimit): array
    {
        if (! is_array($values)) {
            return [];
        }

        $sanitized = array_map(
            fn (mixed $value): string => $this->sanitizeText((string) $value, $itemLimit),
            array_slice($values, 0, $listLimit),
        );

        return array_values(array_unique(array_filter(
            $sanitized,
            static fn (string $value): bool => $value !== '',
        )));
    }

    /** @return array{width: float|null, height: float|null, depth: float|null, unit: string|null}|null */
    private function normalizeDimensions(mixed $dimensions): ?array
    {
        if (! is_array($dimensions)) {
            return null;
        }

        return [
            'width' => isset($dimensions['width']) ? (float) $dimensions['width'] : null,
            'height' => isset($dimensions['height']) ? (float) $dimensions['height'] : null,
            'depth' => isset($dimensions['depth']) ? (float) $dimensions['depth'] : null,
            'unit' => isset($dimensions['unit']) ? (string) $dimensions['unit'] : null,
        ];
    }

    /** @return array<int, array{name: string, options: array<int, string>}> */
    private function normalizeVariations(mixed $variations): array
    {
        if (! is_array($variations)) {
            return [];
        }

        $normalized = [];
        foreach (array_slice($variations, 0, 12) as $variation) {
            if (! is_array($variation)) {
                continue;
            }

            $name = $this->sanitizeText((string) ($variation['name'] ?? ''), 60);
            $options = $this->sanitizeTextList($variation['options'] ?? [], 60, 20);
            if ($name !== '' && $options !== []) {
                $normalized[] = ['name' => $name, 'options' => $options];
            }
        }

        return $normalized;
    }

    /** @return array<string, array<int, string>> */
    private function commercialReviewErrors(mixed $review): array
    {
        $errors = [];
        if (! is_array($review) || ! isset($review['name']) || trim((string) $review['name']) === '') {
            $errors['name'] = ['Debes confirmar el nombre antes de aprobar.'];
        }
        if (! is_array($review) || (! is_int($review['price_minor'] ?? null) && ! is_numeric($review['price'] ?? null))) {
            $errors['price'] = ['Debes confirmar el precio antes de aprobar.'];
        }
        if (! is_array($review) || ! array_key_exists('stock_quantity', $review) || $review['stock_quantity'] === null) {
            $errors['stock_quantity'] = ['Debes confirmar el inventario antes de aprobar.'];
        }

        return $errors;
    }

    /** Decimal HNL is accepted only at this HTTP adapter boundary. */
    private function minorHnl(string $decimal): int
    {
        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $decimal)) {
            throw ValidationException::withMessages(['price' => ['El precio debe tener como máximo dos decimales.']]);
        }

        [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    /** @param mixed $review @return mixed */
    private function reviewResponsePayload(mixed $review): mixed
    {
        if (! is_array($review) || ! is_int($review['price_minor'] ?? null)) {
            return $review;
        }
        $review['price'] = $review['price_minor'] / 100;

        return $review;
    }

    private function publicErrorMessage(string $errorCode): string
    {
        return match ($errorCode) {
            'duplicate_image' => 'Esta imagen ya fue agregada al catálogo.',
            'analysis_failed', 'provider_unavailable' => 'No pudimos analizar la imagen. Puedes reintentarlo.',
            'batch_cancelled' => 'El lote fue cancelado.',
            default => 'No pudimos completar este elemento.',
        };
    }
}
