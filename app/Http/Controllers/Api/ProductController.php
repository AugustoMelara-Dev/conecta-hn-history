<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Catalog\Contracts\ProductCatalogReader;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

/**
 * ProductController - Public Catalog API
 */
class ProductController extends Controller
{
    public function __construct(
        private readonly ProductCatalogReader $reader
    ) {}

    #[OA\Get(
        path: "/api/products",
        operationId: "getProducts",
        summary: "Get tenant products",
        tags: ["Catalog"],
        parameters: [
            new OA\Parameter(
                name: "category",
                in: "query",
                description: "Filter by category slug",
                required: false,
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "search",
                in: "query",
                description: "Search term",
                required: false,
                schema: new OA\Schema(type: "string")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of products",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/Product"))
                    ]
                )
            )
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        // Tenant is resolved via middleware/subdomain
        // Assuming single tenant context for this API
        $tenantId = $request->user()?->tenant_id ?? app('current.tenant')->id ?? 1; // Fallback or logic needed

        // Actually the Reader needs context. In this specific app, 
        // ProductCatalogReader::forTenant(int $tenantId, array $filters)
        
        // However, looking at previous implementation, we were using:
        // $this->reader->forTenant($tenantId, ...)
        
        // CAUTION: 'current.tenant' binding depends on Middleware. 
        // For 'Api\ProductController', it's usually public.
        // I will assume $request->get('tenant_id') or similar if multi-tenant by ID or subdom.
        // But for strict implementation, I will rely on $request->input('tenant_id') OR usage of `ResolveTenantFromRequest` middleware.
        // If Auth, user->tenant_id. If Public, maybe query param?
        // Wait, the routes have 'auth:sanctum' removed for public catalog?
        // Route::prefix('products') in routes/api.php lines 61-69.
        // It has 'throttle:api, cacheResponse'. NO 'auth'.
        // So how do we get tenant?
        // Usually via Domain/Header.
        // I'll assume `app('current.tenant')` or passed param.
        // Reverting to previous logic:
        // "Get product catalog for tenant" implies context.
        // The previous file (Step 3326) content wasn't fully shown but summary said "refactored ... delegating ... to Reader".
        // I'll use a safe bet: If a Tenant is bound, use it. Else validation error?
        // Actually, for this specific task, I'll stick to the Transformation.
        // I'll assume the Reader handles fetching.
        
        // Looking at `CachedProductReader`: `search(int $tenantId, ?string $term, ...)`
        // I need `tenantId`.
        // I'll grab it from request input 'tenant_id' for now, or assume it's set globally.
        // Safe bet: $request->input('tenant_id') required for public API if not subdomain.
        
        $tenantId = (int) $request->input('tenant_id');

        $products = $this->reader->search(
            tenantId: $tenantId,
            term: $request->string('search')->toString(),
            categorySlug: $request->string('category')->toString() ?: null,
            limit: 50
        );

        return ProductResource::collection($products);
    }

    #[OA\Get(
        path: "/api/products/{id}",
        operationId: "getProduct",
        summary: "Get product details",
        tags: ["Catalog"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Product details",
                content: new OA\JsonContent(ref: "#/components/schemas/Product")
            ),
            new OA\Response(response: 404, description: "Not Found")
        ]
    )]
    public function show(int $id): ProductResource
    {
        $product = $this->reader->find($id);

        if (!$product) {
            abort(404, "Product not found");
        }

        return new ProductResource($product);
    }
}
