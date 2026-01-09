<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Actions\Orders\CancelOrderAction;
use App\Services\OrderStateService;
use App\Domain\Sales\Contracts\OrderReader;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
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
        private readonly \App\Application\Shared\Contracts\CommandBus $bus,
        private readonly OrderStateService $orderStateService,
        private readonly CancelOrderAction $cancelOrderAction
    ) {}

    // ... (Other methods: index, store, show, updateStatus kept same or omitted for brevity, focusing on destroy)
    // I will include ALL methods to ensure the file is complete and not truncated.

    public function index(Request $request): AnonymousResourceCollection
    {
        $tenantId = (int) $request->user()?->tenant_id;
        if (!$tenantId) abort(403);

        return OrderResource::collection(
            $this->reader->search(
                tenantId: $tenantId,
                status: $request->input('status'),
                perPage: 20
            )
        );
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        // 1. Transform (Factory in Request)
        $command = $request->toCommand();

        // 2. Execution (Business Logic via Bus)
        $order = $this->bus->dispatch($command);

        // 3. Response (Presentation Logic via Resource)
        return response()->json(new OrderResource($order->load('items')), 201);
    }

    public function show(int $id): OrderResource
    {
        $order = $this->reader->find($id);
        if (!$order) abort(404, 'Order not found');
        return new OrderResource($order);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, int $orderId): OrderResource
    {
        $order = $this->reader->find($orderId);
        if (!$order) abort(404);

        $this->orderStateService->transition(
            $order,
            $request->validated('status'),
            $request->user()->id
        );

        return new OrderResource($this->reader->find($orderId));
    }

    public function destroy(Request $request, int $orderId): JsonResponse
    {
        // DELEGATED: Action handles lookup and deletion
        // We use request->user()->tenant_id to authorize ownership in action
        $tenantId = (int) $request->user()?->tenant_id;
        
        $this->cancelOrderAction->execute($orderId, $tenantId);

        return response()->json(null, 204);
    }
}
