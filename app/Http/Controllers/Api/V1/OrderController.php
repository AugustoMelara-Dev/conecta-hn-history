<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Sales\Contracts\OrderStateServiceInterface;
use App\Domain\Sales\Contracts\OrderReader;
use App\Domain\Shared\Contracts\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

final class OrderController extends Controller
{
    public function __construct(
        private readonly OrderReader $reader,
        private readonly \App\Application\Shared\Contracts\CommandBusInterface $bus,
        private readonly OrderStateServiceInterface $orderStateService,
        private readonly TenantContext $tenantContext
    ) {}

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
        $tenantId = $this->tenantContext->getTenantUuid();

        return OrderResource::collection(
            $this->reader->search(
                tenantId: $tenantId,
                status: $request->string('status')->toString() ?: null,
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
        $command = $request->toCommand();
        $order = $this->bus->dispatch($command);

        return response()->json(new OrderResource($order), 201);
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
        $tenantId = $this->tenantContext->getTenantUuid();
        $order = $this->reader->find($tenantId, $id);

        if (! $order instanceof Order) {
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
        $tenantId = $this->tenantContext->getTenantUuid();
        $order = $this->reader->find($tenantId, $orderId);

        if (! $order instanceof Order) {
            abort(404, 'Order not found');
        }

        $updatedOrder = $this->orderStateService->transition(
            $order,
            (string) $request->validated('status')
        );

        return new OrderResource($updatedOrder);
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
        $tenantId = $this->tenantContext->getTenantUuid();
        $order = $this->reader->find($tenantId, $orderId);

        if (! $order instanceof Order) {
            abort(404, 'Order not found');
        }

        $this->orderStateService->transition($order, Order::STATUS_CANCELLED);

        return response()->json(null, 204);
    }
}
