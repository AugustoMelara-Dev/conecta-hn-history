<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Actions\Orders\CreateOrderAction;
use App\Application\DTOs\Orders\CreateOrderData;
use App\Domain\Orders\Services\OrderStateService;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use OpenApi\Attributes as OA;

/**
 * OrderController - Transaction Management
 */
class OrderController extends Controller
{
    public function __construct(
        private readonly CreateOrderAction $createOrderAction,
        private readonly OrderStateService $orderStateService
    ) {}

    #[OA\Get(
        path: "/api/orders",
        operationId: "listOrders",
        summary: "List tenant orders",
        tags: ["Orders"],
        parameters: [
            new OA\Parameter(name: "page", in: "query", schema: new OA\Schema(type: "integer")),
            new OA\Parameter(name: "status", in: "query", schema: new OA\Schema(type: "string"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of orders",
                content: new OA\JsonContent(
                    type: "object",
                    properties: [
                        new OA\Property(property: "data", type: "array", items: new OA\Items(ref: "#/components/schemas/Order")),
                        new OA\Property(property: "meta", type: "object"),
                        new OA\Property(property: "links", type: "object")
                    ]
                )
            )
        ]
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $tenantId = $request->user()->tenant_id;

        $query = Order::where('tenant_id', $tenantId)->latest();

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        return OrderResource::collection($query->paginate(20));
    }

    #[OA\Post(
        path: "/api/orders",
        operationId: "createOrder",
        summary: "Create a new order",
        tags: ["Orders"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: "#/components/schemas/CreateOrderData")
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: "Order created successfully",
                content: new OA\JsonContent(ref: "#/components/schemas/Order")
            ),
            new OA\Response(response: 422, description: "Validation Error")
        ]
    )]
    public function store(StoreOrderRequest $request): JsonResponse
    {
        // 1. DTO Hydration
        $data = CreateOrderData::fromRequest($request);

        // 2. Execution
        $order = $this->createOrderAction->execute($data);

        // 3. Response
        return response()->json(
            new OrderResource($order->load('items')), 
            201
        );
    }

    #[OA\Get(
        path: "/api/orders/{id}",
        operationId: "getOrder",
        summary: "Get order details",
        tags: ["Orders"],
        parameters: [
            new OA\Parameter(name: "id", in: "path", required: true, schema: new OA\Schema(type: "integer"))
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: "Order details",
                content: new OA\JsonContent(ref: "#/components/schemas/Order")
            ),
            new OA\Response(response: 404, description: "Not Found")
        ]
    )]
    public function show(Order $order): OrderResource
    {
        // Tenant ownership handled by middleware 'tenant.ownership'
        return new OrderResource($order->load(['items', 'tenant']));
    }

    #[OA\Patch(
        path: "/api/orders/{id}/status",
        operationId: "updateOrderStatus",
        summary: "Update order status",
        tags: ["Orders"],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ["status"],
                properties: [
                    new OA\Property(property: "status", type: "string", enum: ["pending", "confirmed", "preparing", "ready_for_pickup", "out_for_delivery", "delivered", "cancelled"])
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: "Order updated",
                content: new OA\JsonContent(ref: "#/components/schemas/Order")
            )
        ]
    )]
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): OrderResource
    {
        $this->orderStateService->transition(
            $order,
            $request->validated('status'),
            $request->user()->id
        );

        return new OrderResource($order->fresh(['items']));
    }

    #[OA\Delete(
        path: "/api/orders/{id}",
        operationId: "deleteOrder",
        summary: "Delete order (Cancel)",
        tags: ["Orders"],
        responses: [
            new OA\Response(response: 204, description: "Deleted")
        ]
    )]
    public function destroy(Order $order): JsonResponse
    {
        // Typically 'destroy' implies cancellation or hard delete.
        // Assuming delete for now.
        $order->delete();

        return response()->json(null, 204);
    }
}
