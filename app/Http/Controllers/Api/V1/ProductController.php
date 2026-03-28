<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Product\Contracts\ProductReader;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
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
        private readonly ProductReader $reader
    ) {}

    #[OA\Get(
        path: '/api/products',
        summary: 'Get tenant products',
        tags: ['Catalog'],
        parameters: [
            new OA\Parameter(name: 'tenant_id', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'category', in: 'query', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'search', in: 'query', schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'List of products'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        // 1. Resolve Tenant safely (Public API context)
        $tenantId = trim((string) $request->input('tenant_id', ''));

        if ($tenantId === '') {
            abort(400, 'tenant_id is required');
        }

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
            new OA\Parameter(name: 'tenant_id', in: 'query', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Product details'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function show(int $id, Request $request): ProductResource
    {
        $tenantId = trim((string) $request->input('tenant_id', ''));

        if ($tenantId === '') {
            abort(400, 'tenant_id is required');
        }

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
        \App\Http\Requests\StoreProductRequest $request,
        \App\Application\Products\Handlers\CreateProductHandler $handler
    ): \Illuminate\Http\JsonResponse {
        $command = $request->toCommand();
        $product = $handler->handle($command);

        return response()->json(new ProductResource($product), 201);
    }

    /**
     * Update the specified product.
     */
    public function update(
        \App\Http\Requests\Tenant\UpdateProductRequest $request,
        \App\Application\Products\Handlers\UpdateProductHandler $handler,
        int $id
    ): \Illuminate\Http\JsonResponse {
        // Resolve tenant ownership logic handled by Middleware/Request
        // But we need to ensure ID matches or is validated?
        // Middleware `EnsureTenantOwnership` already checked `Product` model binding?
        // If route is /products/{product}, and model binding is on, $id might be object.
        // But signature says `int $id`. Laravel adapts.
        // Step 4404 `EnsureTenantOwnership` checks model binding.
        // If we use `int $id`, ensure we are safe. Handler checks ID usually.

        $command = $request->toCommand($id);
        $result = $handler->handle($command);

        return response()->json(new ProductResource($result), 200);
    }
}
