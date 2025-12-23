<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * OrderController - Checkout API with Coupon Support
 *
 * TRANSACTIONAL HANDSHAKE PATTERN:
 * The frontend cannot open WhatsApp directly. It must:
 * 1. POST cart data to this endpoint
 * 2. System validates coupon (if any) and persists order transactionally
 * 3. System returns WhatsApp URL with order ID
 * 4. Frontend redirects to WhatsApp
 *
 * COUPON ATOMICITY:
 * Coupon usage_count is incremented INSIDE the DB transaction
 * to prevent race conditions on limited coupons.
 */
class OrderController extends Controller
{
    /**
     * Create a new order from cart data.
     *
     * POST /api/orders
     */
    public function store(Request $request): JsonResponse
    {
        // ═══════════════════════════════════════════════════════════════════════
        // VALIDATION
        // ═══════════════════════════════════════════════════════════════════════
        $validator = Validator::make($request->all(), [
            'tenant_id' => 'required|exists:tenants,id',
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email|max:255',
            'customer_address' => 'nullable|string|max:500',
            'customer_notes' => 'nullable|string|max:1000',
            'delivery_type' => 'required|in:pickup,delivery',
            'payment_method' => 'nullable|in:cash,card,transfer,pending',
            'coupon_code' => 'nullable|string|max:50', // NEW: Optional coupon
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'nullable|exists:products,id',
            'items.*.name' => 'required|string|max:255',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.options' => 'nullable|array',
            'items.*.notes' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Datos de pedido inválidos',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        // ═══════════════════════════════════════════════════════════════════════
        // TRANSACTIONAL ORDER CREATION WITH COUPON ATOMICITY
        // ═══════════════════════════════════════════════════════════════════════
        try {
            $order = DB::transaction(function () use ($data) {
                // Calculate subtotal from items
                $subtotal = 0;
                $itemsData = [];

                foreach ($data['items'] as $cartItem) {
                    $orderItem = OrderItem::fromCartItem($cartItem);
                    $subtotal += $orderItem->line_total;
                    $itemsData[] = $orderItem;
                }

                // ═══════════════════════════════════════════════════════════
                // COUPON VALIDATION (Inside transaction for atomicity)
                // ═══════════════════════════════════════════════════════════
                $discountAmount = 0;
                $couponCode = null;
                $coupon = null;

                if (! empty($data['coupon_code'])) {
                    $couponCode = strtoupper(trim($data['coupon_code']));

                    $coupon = Coupon::forTenant($data['tenant_id'])
                        ->byCode($couponCode)
                        ->lockForUpdate() // Row lock for race condition prevention
                        ->first();

                    if ($coupon) {
                        $validation = $coupon->validate($subtotal);

                        if ($validation['valid']) {
                            $discountAmount = $validation['discount_amount'];

                            // Atomic increment - will fail if limit reached
                            if (! $coupon->incrementUsage()) {
                                throw new \Exception('Este cupón ya alcanzó su límite de usos');
                            }
                        } else {
                            // Coupon validation failed - throw to abort transaction
                            throw new \Exception($validation['error']);
                        }
                    } else {
                        throw new \Exception('Cupón no encontrado');
                    }
                }

                // Calculate final total
                $total = max(0, $subtotal - $discountAmount);

                // Create order header
                $order = Order::create([
                    'tenant_id' => $data['tenant_id'],
                    'order_number' => Order::generateOrderNumber($data['tenant_id']),
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

                // Create order items
                foreach ($itemsData as $itemData) {
                    $order->items()->save($itemData);
                }

                // Load items for message building
                $order->load(['items', 'tenant']);

                return $order;
            });

            // ═══════════════════════════════════════════════════════════════════
            // BUILD WHATSAPP URL
            // ═══════════════════════════════════════════════════════════════════
            $whatsappUrl = $order->getWhatsAppUrl();

            return response()->json([
                'success' => true,
                'message' => 'Pedido creado exitosamente',
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'subtotal' => $order->subtotal,
                    'discount_amount' => $order->discount_amount,
                    'coupon_code' => $order->coupon_code,
                    'total' => $order->total,
                    'formatted_total' => $order->formatted_total,
                    'status' => $order->status,
                    'whatsapp_url' => $whatsappUrl,
                ],
            ], 201);

        } catch (\Exception $e) {
            // Check if this is a coupon error
            $isCouponError = str_contains($e->getMessage(), 'cupón') ||
                             str_contains($e->getMessage(), 'Cupón');

            \Log::error('Order creation failed', [
                'error' => $e->getMessage(),
                'is_coupon_error' => $isCouponError,
                'data' => $data,
            ]);

            return response()->json([
                'success' => false,
                'message' => $isCouponError ? $e->getMessage() : 'Error al crear el pedido. Por favor intente de nuevo.',
                'error_code' => $isCouponError ? 'COUPON_ERROR' : 'ORDER_ERROR',
                'error' => app()->isLocal() ? $e->getMessage() : null,
            ], $isCouponError ? 422 : 500);
        }
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

        $orders = Order::with('items')
            ->forTenant($tenantId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $orders,
        ]);
    }

    /**
     * Update order status (for Kanban drag-drop).
     *
     * PATCH /api/orders/{order}/status
     */
    public function updateStatus(Request $request, Order $order): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:'.implode(',', Order::STATUSES),
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Estado inválido',
                'errors' => $validator->errors(),
            ], 422);
        }

        $success = $order->transitionTo($request->input('status'));

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'Estado actualizado',
                'data' => [
                    'order_id' => $order->id,
                    'status' => $order->status,
                    'status_label' => $order->status_label,
                ],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Error al actualizar el estado',
        ], 500);
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
        // Load relationships for complete data
        $order->load('items', 'tenant');

        return response()->json([
            'success' => true,
            'data' => $order,
        ]);
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
