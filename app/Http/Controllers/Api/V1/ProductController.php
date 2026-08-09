<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Products\CanonicalProductWriter;
use App\Application\Products\Commands\CreateCanonicalProductCommand;
use App\Domain\Product\Contracts\ProductReader;
use App\Domain\Shared\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\Tenant\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;

/**
 * ProductController - Public Catalog API
 *
 * ARCHITECTURE:
 * - Hexagonal: Delegates to ProductReader
 * - Zero Eloquent: No direct Model interactions
 * - Strict: Final class, strict types
 */
final class ProductController extends Controller
{
    public function __construct(
        private readonly ProductReader $reader,
        private readonly CanonicalProductWriter $productWriter,
        private readonly TenantContext $tenantContext,
    ) {}

    #[OA\Get(
        path: '/api/products',
        summary: 'Get tenant products',
        tags: ['Catalog'],
        parameters: [
            new OA\Parameter(name: 'category', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of products'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $tenantId = $this->resolveTenant($request);

        // 2. Delegate to Reader (Hexagonal)
        $products = $this->reader->search(
            tenantId: $tenantId,
            filters: [
                'search' => $request->input('search'),
                'category' => $request->input('category'),
            ],
            perPage: 20
        );

        // 3. Return Resource Collection
        return ProductResource::collection($products);
    }

    #[OA\Get(
        path: '/api/products/{id}',
        summary: 'Get product details',
        tags: ['Catalog'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Product details'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function show(int $id, Request $request): ProductResource
    {
        $tenantId = $this->resolveTenant($request);

        $product = $this->reader->find($tenantId, $id);

        if (! $product) {
            abort(404, 'Product not found');
        }

        return new ProductResource($product);
    }

    /**
     * Store a newly created product.
     */
    public function store(
        StoreProductRequest $request,
    ): JsonResponse {
        $data = $request->validated();
        $tenantId = $this->trustedWriteTenant($request);
        try {
            $product = $this->productWriter->create(new CreateCanonicalProductCommand(
                tenantId: $tenantId,
                idempotencyKey: $this->idempotencyKey($request),
                name: (string) $data['name'],
                priceMinor: $this->minorHnl($data['price']),
                initialStockQuantity: (int) ($data['stock_quantity'] ?? 0),
                attributes: $this->writeAttributes($data),
                published: (bool) ($data['is_visible'] ?? true),
            ));
        } catch (\InvalidArgumentException $exception) {
            abort(422, $exception->getMessage());
        }

        return response()->json(new ProductResource($product), 201);
    }

    /**
     * Update the specified product.
     */
    public function update(
        UpdateProductRequest $request,
        int $product
    ): JsonResponse {
        $data = $request->validated();
        $result = DB::transaction(function () use ($request, $product, $data) {
            $attributes = $this->writeAttributes($data);
            $attributes['name'] = (string) $data['name'];
            $attributes['price_minor'] = $this->minorHnl($data['price']);
            $tenantId = $this->trustedWriteTenant($request);
            $updated = $this->productWriter->update($tenantId, $product, $attributes);
            if (array_key_exists('stock_quantity', $data)) {
                try {
                    $updated = $this->productWriter->setStock($tenantId, $product, (int) $data['stock_quantity'], $this->idempotencyKey($request));
                } catch (\LogicException $exception) {
                    abort(422, $exception->getMessage());
                }
            }
            if (array_key_exists('is_visible', $data)) {
                $updated = $this->productWriter->publish($tenantId, $product, (bool) $data['is_visible']);
            }

            return $updated;
        });

        return response()->json(new ProductResource($result), 200);
    }

    private function resolveTenant(Request $request): string
    {
        $validated = $request->validate([
            'tenant_id' => ['required', 'uuid'],
        ]);

        $tenant = Tenant::query()
            ->where('is_active', true)
            ->findOrFail((string) $validated['tenant_id']);

        $context = app(TenantContext::class);
        $context->setTenant((string) $tenant->getKey());
        app()->instance('currentTenant', $tenant);
        app()->instance('current_tenant', $tenant);

        return (string) $tenant->getKey();
    }

    /** The request body is never an authority source for mutation ownership. */
    private function trustedWriteTenant(Request $request): string
    {
        if ($this->tenantContext->isInitialized() && ! $this->tenantContext->isRoot()) {
            return (string) $this->tenantContext->id();
        }

        $tenantId = $request->user()?->tenant_id;
        abort_unless(is_string($tenantId) && Str::isUuid($tenantId), 403);

        return $tenantId;
    }

    /** @param array<string, mixed> $data @return array<string, mixed> */
    private function writeAttributes(array $data): array
    {
        return array_filter([
            'category_id' => $data['category_id'] ?? null,
            'menu_section_id' => $data['menu_section_id'] ?? null,
            'description' => $data['description'] ?? null,
            'allow_backorders' => $data['allow_backorders'] ?? null,
            'low_stock_threshold' => $data['low_stock_threshold'] ?? null,
            'is_featured' => $data['is_featured'] ?? null,
            'is_new' => $data['is_new'] ?? null,
            'variants' => $data['variants'] ?? null,
            'manage_stock' => array_key_exists('manage_stock', $data) ? (bool) $data['manage_stock'] : null,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /** Decimal HNL is adapter-only; application commands receive integer minor units. */
    private function minorHnl(mixed $decimal): int
    {
        if (! is_string($decimal) || ! preg_match('/^\d+(?:\.\d{1,2})?$/', $decimal)) {
            abort(422, 'price must be an exact HNL decimal with at most two places');
        }
        [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');

        return ((int) $whole * 100) + (int) str_pad($fraction, 2, '0');
    }

    private function idempotencyKey(Request $request): string
    {
        $key = $request->header('Idempotency-Key');
        abort_unless(is_string($key) && trim($key) !== '' && strlen($key) <= 191, 422, 'Idempotency-Key is required and must be at most 191 characters.');

        return $key;
    }
}
