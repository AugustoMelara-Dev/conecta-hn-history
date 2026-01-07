<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Application\Actions\Orders\CreateOrderAction;
use App\Application\DTOs\Orders\CreateOrderData;
use App\Exceptions\InvalidOrderTransitionException;
use App\Exceptions\TenantMismatchException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\OrderStateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * OrderController - Thin HTTP Gateway
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  REFACTORED: All business logic moved to CreateOrderAction              ║
 * ║  This controller is now "blind" to order creation logic.                ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
class OrderController extends Controller
{
    protected const LOCK_DURATION_SECONDS = 10;
    protected const LOCK_WAIT_SECONDS = 5;

    public function __construct(
        private readonly CreateOrderAction $createOrderAction,
        private readonly OrderStateService $orderStateService
    ) {}

    /**
     * Create a new order from cart data.
     *
     * POST /api/orders
     *
     * REFACTORED: Delegates entirely to CreateOrderAction.
     * Controller responsibility: Lock, Delegate, Respond.
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        $dto = CreateOrderData::fromRequest($request);
        $lock = Cache::lock($dto->generateLockKey(), self::LOCK_DURATION_SECONDS);

        try {
            if (!$lock->block(self::LOCK_WAIT_SECONDS)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya se está procesando una orden. Por favor espera.',
                    'error_code' => 'ORDER_IN_PROGRESS',
                ], 409);
            }

            // ═══════════════════════════════════════════════════════════════════
            // DELEGATION: All business logic lives in the Action
            // ═══════════════════════════════════════════════════════════════════
            $order = $this->createOrderAction->execute($dto, $request->user());

            return (new OrderResource($order))
                ->additional(['message' => 'Pedido creado exitosamente'])
                ->response()
                ->setStatusCode(201);

        } catch (ValidationException $e) {
            throw $e;
        } catch (\App\Exceptions\InvalidCouponException $e) {
            Log::warning('Coupon validation failed', [
                'error' => $e->getMessage(),
                'code' => $e->getErrorCode(),
            ]);
            return response()->json($e->toArray(), $e->getHttpStatusCode());
        } catch (\Exception $e) {
            Log::error('Order creation failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el pedido. Por favor intente de nuevo.',
                'error_code' => 'ORDER_ERROR',
                'error' => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        } finally {
            $lock->release();
        }
    }

    /**
     * Get orders for a specific tenant (for admin panel).
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');

        if (!$tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'tenant_id es requerido',
            ], 400);
        }

        $user = auth()->user();
        if ($user && $user->tenant_id && (int) $tenantId !== $user->tenant_id) {
            throw TenantMismatchException::crossTenantAccess(
                $user->tenant_id,
                (int) $tenantId,
                'TenantOrders',
                0
            );
        }

        $orders = Order::with(['items', 'tenant'])
            ->forTenant($tenantId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return OrderResource::collection($orders)
            ->additional(['success' => true]);
    }

    /**
     * Update order status (for Kanban drag-drop).
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();
        $newStatus = $validated['status'];

        if ($order->tenant_id !== auth()->user()?->tenant_id) {
            throw TenantMismatchException::forOrder(
                $order->id,
                $order->tenant_id,
                auth()->user()?->tenant_id
            );
        }

        try {
            $order = $this->orderStateService->transition($order, $newStatus);

            return response()->json([
                'success' => true,
                'message' => 'Estado actualizado',
                'data' => [
                    'order_id' => $order->id,
                    'status' => $order->status,
                    'allowed_transitions' => $this->orderStateService->getAllowedTransitions($order->status),
                ],
            ]);
        } catch (InvalidOrderTransitionException $e) {
            return response()->json($e->toArray(), $e->getHttpStatusCode());
        }
    }

    /**
     * Show order details.
     */
    public function show(Order $order): JsonResponse
    {
        if ($order->tenant_id !== auth()->user()?->tenant_id) {
            throw TenantMismatchException::forOrder(
                $order->id,
                $order->tenant_id,
                auth()->user()?->tenant_id
            );
        }

        $order->load('items', 'tenant');
        return (new OrderResource($order))->response();
    }

    /**
     * Delete an order.
     */
    public function destroy(Order $order): JsonResponse
    {
        if ($order->tenant_id !== auth()->user()?->tenant_id) {
            throw TenantMismatchException::forOrder(
                $order->id,
                $order->tenant_id,
                auth()->user()?->tenant_id
            );
        }

        $protectedStates = [
            Order::STATUS_PROCESSING,
            Order::STATUS_READY,
            Order::STATUS_DELIVERED,
        ];

        if (in_array($order->status, $protectedStates)) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar una orden que ya fue procesada',
                'error_code' => 'ORDER_PROTECTED',
            ], 403);
        }

        $order->delete();

        return response()->json([
            'success' => true,
            'message' => 'Orden eliminada correctamente',
        ]);
    }
}

