<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\Orders\CancelOrderAction;
use App\Domain\Sales\Contracts\OrderReader;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Application\Sales\Contracts\OrderStateServiceInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

/**
 * OrderController - Transaction Management
 */
final class OrderController extends Controller
{
    public function __construct(
        private readonly OrderReader $reader,
        private readonly \App\Application\Shared\Contracts\CommandBusInterface $bus,
        private readonly OrderStateServiceInterface $orderStateService,
        private readonly CancelOrderAction $cancelOrderAction,
        private readonly \App\Domain\Shared\Contracts\TenantContext $tenantContext
    ) {}

    // ... (Other methods: index, store, show, updateStatus kept same or omitted for brevity, focusing on destroy)
    // I will include ALL methods to ensure the file is complete and not truncated.

    #[OA\Get(
        path: '/api/orders',
        summary: 'Get tenant orders',
        tags: ['Sales'],
        responses: [
            new OA\Response(response: 200, description: 'List of orders'),
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $tenantId = (int) $this->tenantContext->getTenantId();
        if (! $tenantId) {
            abort(403);
        }

        return OrderResource::collection(
            $this->reader->search(
                tenantId: $tenantId,
                status: $request->input('status'),
                perPage: 20
            )
        );
    }

    #[OA\Post(
        path: '/api/orders',
        summary: 'Create a new order',
        tags: ['Sales'],
        requestBody: new OA\RequestBody(required: true, description: 'Order payload'),
        responses: [
            new OA\Response(response: 201, description: 'Order created successfully'),
            new OA\Response(response: 422, description: 'Validation Error'),
        ]
    )]
    public function store(StoreOrderRequest $request): JsonResponse
    {
        // 1. Transform (Factory in Request)
        $command = $request->toCommand();

        // 2. Execution (Business Logic via Bus)
        $order = $this->bus->dispatch($command);

        // 3. Response (Presentation Logic via Resource)
        return response()->json(new OrderResource($order->load('items')), 201);
    }

    #[OA\Get(
        path: '/api/orders/{id}',
        summary: 'Get order details',
        tags: ['Sales'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Order details'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function show(Request $request, int $id): OrderResource
    {
        $tenantId = (int) $this->tenantContext->getTenantId();
        if (! $tenantId) {
            abort(403, 'Tenant context required');
        }

        $order = $this->reader->find($tenantId, $id);
        if (! $order) {
            abort(404, 'Order not found');
        }

        return new OrderResource($order);
    }

    #[OA\Patch(
        path: '/api/orders/{id}/status',
        summary: 'Update order status',
        tags: ['Sales'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        requestBody: new OA\RequestBody(required: true, description: 'Status update payload'),
        responses: [
            new OA\Response(response: 200, description: 'Order status updated'),
            new OA\Response(response: 404, description: 'Not Found'),
            new OA\Response(response: 422, description: 'Validation Error'),
        ]
    )]
    public function updateStatus(UpdateOrderStatusRequest $request, int $orderId): OrderResource
    {
        $tenantId = (int) $this->tenantContext->getTenantId();
        if (! $tenantId) {
            abort(403, 'Tenant context required');
        }

        $order = $this->reader->find($tenantId, $orderId);
        if (! $order) {
            abort(404);
        }

        $this->orderStateService->transition(
            $order,
            $request->validated('status'),
            $request->user()->id
        );

        return new OrderResource($this->reader->find($tenantId, $orderId));
    }

    #[OA\Delete(
        path: '/api/orders/{id}',
        summary: 'Cancel an order',
        tags: ['Sales'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Order cancelled successfully'),
            new OA\Response(response: 404, description: 'Not Found'),
        ]
    )]
    public function destroy(Request $request, int $orderId): JsonResponse
    {
        // DELEGATED: Action handles lookup and deletion
        // We use request->user()->tenant_id to authorize ownership in action
        $tenantId = (int) $this->tenantContext->getTenantId();

        $this->cancelOrderAction->execute($orderId, $tenantId);

        return response()->json(null, 204);
    }
}
