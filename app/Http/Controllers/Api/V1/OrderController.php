<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Application\Orders\CanonicalOrderWriter;
use App\Application\Orders\Commands\TransitionOrderCommand;
use App\Application\Sales\Contracts\PublicOrderCreator;
use App\Domain\Sales\Contracts\OrderReader;
use App\Domain\Shared\Services\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * OrderController - Transaction Management
 */
final class OrderController extends Controller
{
    public function __construct(
        private readonly OrderReader $reader,
        private readonly CanonicalOrderWriter $writer,
        private readonly TenantContext $tenantContext,
    ) {}

    // ... (Other methods: index, store, show, updateStatus kept same or omitted for brevity, focusing on destroy)
    // I will include ALL methods to ensure the file is complete and not truncated.

    public function index(Request $request): AnonymousResourceCollection
    {
        $tenantId = $this->tenantId();

        return OrderResource::collection(
            $this->reader->search(
                tenantId: $tenantId,
                status: $request->input('status'),
                perPage: 20
            )
        );
    }

    public function store(StoreOrderRequest $request, PublicOrderCreator $creator): JsonResponse
    {
        $data = $request->toData();
        $tenant = Tenant::query()
            ->whereKey($data->tenantId)
            ->where('is_active', true)
            ->whereNull('publication_paused_at')
            ->firstOrFail();

        $this->tenantContext->setTenant($data->tenantId);
        app()->instance('currentTenant', $tenant);
        app()->instance('current_tenant', $tenant);

        $result = $creator->create($data);

        return response()->json([
            'success' => true,
            'data' => $result->toArray(),
        ], $result->replayed ? 200 : 201);
    }

    public function show(Request $request, int $id): OrderResource
    {
        $tenantId = $this->tenantId();

        $order = $this->reader->find($tenantId, $id);
        if (! $order) {
            abort(404, 'Order not found');
        }

        return new OrderResource($order);
    }

    public function updateStatus(UpdateOrderStatusRequest $request, int $orderId): OrderResource
    {
        $tenantId = $this->tenantId();

        $order = $this->reader->find($tenantId, $orderId);
        if (! $order) {
            abort(404);
        }

        $this->writer->transition(new TransitionOrderCommand(
            tenantId: $tenantId,
            actorId: (string) $request->user()->getKey(),
            orderId: $orderId,
            targetStatus: (string) $request->validated('status'),
        ));

        return new OrderResource($this->reader->find($tenantId, $orderId));
    }

    public function destroy(Request $request, int $orderId): JsonResponse
    {
        $tenantId = $this->tenantId();
        $order = $this->reader->find($tenantId, $orderId);

        if (! $order) {
            abort(404, 'Order not found');
        }

        $this->writer->cancel(new TransitionOrderCommand(
            tenantId: $tenantId,
            actorId: (string) $request->user()->getKey(),
            orderId: $orderId,
            targetStatus: Order::STATUS_CANCELLED,
            reason: 'Cancelled through the order API.',
        ));

        return response()->json(null, 204);
    }

    private function tenantId(): string
    {
        return (string) $this->tenantContext->id();
    }
}
