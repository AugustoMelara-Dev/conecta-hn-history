<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InvalidOrderTransitionException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Requests\UpdateOrderStatusRequest;
use App\Http\Resources\OrderResource;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tenant;
use App\Notifications\NewOrderNotification;
use App\Services\InventoryManager;
use App\Services\OrderStateService;
use App\Services\PriceCalculatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * OrderController - Checkout API with Atomic Idempotency Protection
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ZERO-TRUST CONCURRENCY: ATOMIC LOCKS + TRANSACTIONAL INTEGRITY         ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * PROBLEM SOLVED:
 * 1. Double-Spend Vulnerability: User clicks "Pay" multiple times on slow network.
 *    Without locks, system creates duplicate orders, double-charges, corrupts inventory.
 *
 * SOLUTION:
 * Atomic Cache Lock wraps the entire order creation process.
 * Lock key = hash of (tenant_id + customer_fingerprint + cart_hash)
 * Concurrent requests for the SAME cart block until first completes.
 *
 * TRANSACTIONAL HANDSHAKE PATTERN:
 * 1. Acquire atomic lock (or fail with 409)
 * 2. Validate coupon and persist order in DB transaction
 * 3. Release lock automatically
 * 4. Return WhatsApp URL
 */
class OrderController extends Controller
{
    /**
     * Lock duration in seconds.
     */
    protected const LOCK_DURATION_SECONDS = 10;

    /**
     * Maximum time to wait for lock acquisition.
     */
    protected const LOCK_WAIT_SECONDS = 5;

    /**
     * Domain Services (Constructor Injection)
     */
    protected InventoryManager $inventoryManager;

    protected PriceCalculatorService $priceCalculator;

    protected OrderStateService $orderStateService;

    /**
     * Inject dependencies via constructor for testability.
     *
     * BENEFITS:
     * - Explicit dependency declaration
     * - Mock injection in unit tests
     * - No hidden service locator calls
     */
    public function __construct(
        InventoryManager $inventoryManager,
        PriceCalculatorService $priceCalculator,
        OrderStateService $orderStateService
    ) {
        $this->inventoryManager = $inventoryManager;
        $this->priceCalculator = $priceCalculator;
        $this->orderStateService = $orderStateService;
    }

    /**
     * Create a new order from cart data.
     *
     * POST /api/orders
     *
     * ATOMIC IDEMPOTENCY:
     * Uses Cache::lock() to prevent parallel execution for the same cart.
     * Concurrent duplicate requests return 409 Conflict.
     *
     * NOTE: Validation delegated to StoreOrderRequest (SRP compliance)
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        // Validated data from Form Request (auto-validated, 422 on failure)
        $data = $request->validated();

        // ═══════════════════════════════════════════════════════════════════════
        // ATOMIC IDEMPOTENCY LOCK
        // ═══════════════════════════════════════════════════════════════════════
        // Lock key = tenant + customer fingerprint + cart hash
        // This allows the same customer to place DIFFERENT orders sequentially,
        // but blocks IDENTICAL carts from being processed in parallel.
        // ═══════════════════════════════════════════════════════════════════════
        // ═══════════════════════════════════════════════════════════════════════
        $lockKey = $this->generateLockKey($data);

        $lock = Cache::lock($lockKey, self::LOCK_DURATION_SECONDS);

        try {
            // Attempt to acquire lock, waiting up to 5 seconds
            if (! $lock->block(self::LOCK_WAIT_SECONDS)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ya se está procesando una orden. Por favor espera.',
                    'error_code' => 'ORDER_IN_PROGRESS',
                ], 409);
            }

            // ═══════════════════════════════════════════════════════════════════
            // TRANSACTIONAL ORDER CREATION WITH COUPON ATOMICITY
            // ═══════════════════════════════════════════════════════════════════
            $order = DB::transaction(function () use ($data) {
                return $this->createOrderWithinTransaction($data);
            });

            // ═══════════════════════════════════════════════════════════════════════
            // EAGER LOAD TENANT (N+1 Prevention)
            // ═══════════════════════════════════════════════════════════════════════
            // OrderResource->getPaymentInstructions() accesses $order->tenant for
            // bank details. Without explicit loading, this triggers a lazy query.
            $order->load('tenant');

            // ═══════════════════════════════════════════════════════════════════════
            // BUILD WHATSAPP URL
            // ═══════════════════════════════════════════════════════════════════════
            $whatsappUrl = $order->getWhatsAppUrl();

            // ═══════════════════════════════════════════════════════════════════════
            // FAULT-TOLERANT NOTIFICATION (Best Effort, Non-Blocking)
            // ═══════════════════════════════════════════════════════════════════════
            // Notification is a side effect - must not block HTTP response.
            // If SMTP/Slack fails, we log warning but STILL return success.
            try {
                if ($order->tenant?->user) {
                    $order->tenant->user->notify(new NewOrderNotification($order));
                }
            } catch (\Exception $notificationError) {
                // Suppress exception - notification failure is non-critical
                Log::warning('Failed to send new order notification', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'error' => $notificationError->getMessage(),
                ]);
            }

            // Return standardized response using API Resource
            return (new OrderResource($order))
                ->additional(['message' => 'Pedido creado exitosamente'])
                ->response()
                ->setStatusCode(201);

        } catch (ValidationException $e) {
            // ═══════════════════════════════════════════════════════════════════
            // BUSINESS LOGIC REJECTION: Re-throw for Laravel's 422 handler
            // ═══════════════════════════════════════════════════════════════════
            // ValidationException from InventoryManager/PriceCalculatorService
            // should bubble up to Laravel's ExceptionHandler for proper 422
            // response with structured errors array for frontend form binding.
            throw $e;
        } catch (\App\Exceptions\InvalidCouponException $e) {
             \Log::warning('Coupon validation failed', [
                'error' => $e->getMessage(),
                'code' => $e->getErrorCode(),
                'data' => $e->getData(),
            ]);

            return response()->json($e->toArray(), $e->getHttpStatusCode());

        } catch (\Exception $e) {
            // ═══════════════════════════════════════════════════════════════════
            // SYSTEM FAILURE: Log and return 500
            // ═══════════════════════════════════════════════════════════════════
            \Log::error('Order creation failed', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al crear el pedido. Por favor intente de nuevo.',
                'error_code' => 'ORDER_ERROR',
                'error' => app()->isLocal() ? $e->getMessage() : null,
            ], 500);
        } finally {
            // ALWAYS release lock, even if exception occurred
            $lock->release();
        }
    }

    /**
     * Generate a unique lock key based on cart intent.
     *
     * COMPOSITE HASH STRATEGY:
     * - tenant_id: Tenant context
     * - customer_fingerprint: hash of phone (unique identifier)
     * - cart_hash: hash of items array (same items = same order intent)
     *
     * This allows:
     * - Same customer, different cart = different locks (allowed)
     * - Same customer, same cart = same lock (blocked)
     */
    protected function generateLockKey(array $data): string
    {
        $tenantId = $data['tenant_id'];
        $customerFingerprint = md5($data['customer_phone']);

        // Sort items by product_id to ensure key matches regardless of order
        $items = collect($data['items'])
            ->sortBy('product_id')
            ->values()
            ->all();

        $cartHash = md5(json_encode($items));

        return "order_creation_lock_{$tenantId}_{$customerFingerprint}_{$cartHash}";
    }

    /**
     * Create order within database transaction.
     *
     * ╔══════════════════════════════════════════════════════════════════════════╗
     * ║  PESSIMISTIC LOCKING STRATEGY: ZERO-TRUST ORDER PROCESSING              ║
     * ╚══════════════════════════════════════════════════════════════════════════╝
     *
     * SECURITY GUARANTEES:
     * 1. Price Tampering Prevention: Server-side price retrieval with lockForUpdate()
     * 2. Stock Race Condition Prevention: Exclusive locks on product rows
     * 3. Order Number Collision Prevention: Tenant row locking in generateOrderNumber()
     * 4. Deadlock Immunity: Canonical lock ordering (sorted IDs)
     * 5. Inventory Mutation: Atomic stock decrement within transaction
     *
     * EXECUTION FLOW:
     * 1. Extract and SORT product IDs (canonical order = deadlock prevention)
     * 2. Lock all product rows (FOR UPDATE) in sorted order
     * 3. Validate stock and decrement inventory atomically
     * 4. Enforce server-side pricing (ignore client prices)
     * 5. Lock coupon row (if applicable)
     * 6. Generate atomic order number via tenant lock
     * 7. Create order and items
     *
     * ARCHITECTURE: Clean Orchestrator Pattern
     * - InventoryManager: Handles locking, validation, decrement
     * - PriceCalculatorService: Handles O(1) batch pricing
     * - Controller just orchestrates and persists
     */
    protected function createOrderWithinTransaction(array $data): Order
    {
        $tenantId = $data['tenant_id'];
        $cartItems = $data['items'];

        // ═══════════════════════════════════════════════════════════════════════
        // STEP 1: INVENTORY - Lock, validate, and decrement stock
        // ═══════════════════════════════════════════════════════════════════════
        $lockedProducts = $this->inventoryManager->validateAndLock($cartItems, $tenantId);

        // ═══════════════════════════════════════════════════════════════════════
        // STEP 2: PRICING - Calculate composite prices (O(1) batch query)
        // ═══════════════════════════════════════════════════════════════════════
        $calculatedItems = $this->priceCalculator->calculate($cartItems, $lockedProducts);

        // ═══════════════════════════════════════════════════════════════════════
        // STEP 3: BUILD ORDER ITEMS
        // ═══════════════════════════════════════════════════════════════════════
        $subtotal = 0;
        $itemsData = [];

        foreach ($calculatedItems as $calc) {
            if ($calc['is_custom']) {
                $orderItem = OrderItem::fromCartItem([
                    'name' => $calc['name'],
                    'price' => $calc['unit_price'],
                    'quantity' => $calc['quantity'],
                    'notes' => $calc['notes'],
                ]);
            } else {
                $orderItem = new OrderItem([
                    'product_id' => $calc['product_id'],
                    'name' => $calc['name'],
                    'price' => $calc['unit_price'],
                    'quantity' => $calc['quantity'],
                    'options' => $calc['validated_options'],
                    'notes' => $calc['notes'],
                ]);
                $orderItem->line_total = $calc['line_total'];
            }

            $subtotal += $calc['line_total'];
            $itemsData[] = $orderItem;
        }

        // ═══════════════════════════════════════════════════════════════════════
        // STEP 4: COUPON VALIDATION (with row lock)
        // ═══════════════════════════════════════════════════════════════════════
        $discountAmount = 0;
        $couponCode = null;

        if (! empty($data['coupon_code'])) {
            // Coupon already normalized by StoreOrderRequest::prepareForValidation
            $couponCode = $data['coupon_code'];

            $coupon = Coupon::forTenant($tenantId)
                ->byCode($couponCode)
                ->lockForUpdate()
                ->first();

            if ($coupon) {
                $validation = $coupon->validate($subtotal);

                if ($validation['valid']) {
                    $discountAmount = $validation['discount_amount'];

                    if (! $coupon->incrementUsage()) {
                        throw new \Exception('Este cupón ya alcanzó su límite de usos');
                    }
                } else {
                    throw new \Exception($validation['error']);
                }
            } else {
                throw new \Exception('Cupón no encontrado');
            }
        }

        $total = max(0, $subtotal - $discountAmount);

        // ═══════════════════════════════════════════════════════════════════════
        // STEP 5: GENERATE ATOMIC ORDER NUMBER
        // ═══════════════════════════════════════════════════════════════════════
        $orderNumber = Order::generateOrderNumber($tenantId);

        // ═══════════════════════════════════════════════════════════════════════
        // STEP 6: CREATE ORDER
        // ═══════════════════════════════════════════════════════════════════════
        $order = Order::create([
            'tenant_id' => $tenantId,
            'order_number' => $orderNumber,
            'customer_name' => $data['customer_name'],
            'customer_phone' => $data['customer_phone'],
            'customer_email' => $data['customer_email'] ?? null,
            'customer_address' => $data['customer_address'] ?? null,
            'customer_notes' => $data['customer_notes'] ?? null,
            'delivery_type' => $data['delivery_type'],
            'payment_method' => $data['payment_method'] ?? 'pending',
            'subtotal' => $subtotal,
            'tax_amount' => 0,
            'delivery_fee' => 0,
            'discount_amount' => $discountAmount,
            'coupon_code' => $couponCode,
            'total' => $total,
            'status' => Order::STATUS_PENDING,
        ]);

        // ═══════════════════════════════════════════════════════════════════════
        // STEP 7: CREATE ORDER ITEMS (Declarative Batch Persistence)
        // ═══════════════════════════════════════════════════════════════════════
        $order->items()->saveMany($itemsData);

        $order->load(['items', 'tenant']);

        return $order;
    }

    /**
     * Get orders for a specific tenant (for admin panel).
     *
     * GET /api/orders?tenant_id=X
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = $request->input('tenant_id');

        if (! $tenantId) {
            return response()->json([
                'success' => false,
                'message' => 'tenant_id es requerido',
            ], 400);
        }

        // SECURITY: Prevent cross-tenant data access
        // If user is bound to a tenant, they can ONLY access their own orders.
        $user = auth()->user();
        if ($user && $user->tenant_id && (int) $tenantId !== $user->tenant_id) {
            abort(403, 'Unauthorized access to tenant data');
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
     *
     * PATCH /api/orders/{order}/status
     *
     * FSM DELEGATION:
     * All transition logic delegated to OrderStateService.
     * Controller acts only as HTTP gateway.
     */
    public function updateStatus(UpdateOrderStatusRequest $request, Order $order): JsonResponse
    {
        $validated = $request->validated();
        $newStatus = $validated['status'];

        // SECURITY: Defense in Depth - Explicitly verify tenant ownership
        if ($order->tenant_id !== auth()->user()?->tenant_id) {
            abort(403);
        }

        try {
            // Delegate to FSM service
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
     *
     * GET /api/orders/{order}
     *
     * SECURITY: Rely on tenant.ownership middleware for access control.
     * If the request reaches here, the order belongs to the authenticated user's tenant.
     */
    public function show(Order $order): JsonResponse
    {
        if ($order->tenant_id !== auth()->user()?->tenant_id) {
            abort(403);
        }
        // Load relationships for complete data
        $order->load('items', 'tenant');

        return (new OrderResource($order))->response();
    }

    /**
     * Delete an order.
     *
     * DELETE /api/orders/{order}
     *
     * SECURITY:
     * 1. tenant.ownership middleware validates tenant ownership
     * 2. Policy validates business rules (cannot delete processed orders)
     */
    public function destroy(Order $order): JsonResponse
    {
        // SECURITY: Defense in Depth - Explicitly verify tenant ownership
        // even if middleware usually handles it.
        if ($order->tenant_id !== auth()->user()?->tenant_id) {
            abort(403, 'Unauthorized access to this order');
        }
        // Business rule: Cannot delete orders in protected states
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
