<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Application\Sales\Commands\CreateOrderCommand;
use App\Application\Sales\Handlers\CreateOrderHandlerInterface;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Sales\Contracts\OrderReader;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreCheckoutRequest;
use DomainException;
use Illuminate\Http\JsonResponse;
use Psr\Log\LoggerInterface;

/**
 * OrderController - Transactional Gateway (Hexagonal Compliant)
 *
 * ARCHITECTURAL COMPLIANCE:
 * - Pure adapter: Translates HTTP Request to Domain Command.
 * - Zero App\Models imports.
 * - Execution delegated to Application Layer Handler.
 */
final class OrderController extends Controller
{
    public function __construct(
        private readonly LoggerInterface $log,
        private readonly CreateOrderHandlerInterface $createOrderHandler,
        private readonly UserRepositoryInterface $userRepository,
        private readonly OrderReader $orderReader,
    ) {}

    public function store(StoreCheckoutRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $tenantId = (string) ($validated['tenant_id'] ?? $request->route('tenant') ?? $request->get('tenant') ?? '');

        if ($tenantId === '') {
            return response()->json([
                'success' => false,
                'message' => 'Tenant identification required.',
            ], 400);
        }

        $customerId = $request->user()?->id;

        if ($customerId === null && ! empty($validated['customer_email'])) {
            $customer = $this->userRepository->findByEmail((string) $validated['customer_email'], $tenantId);
            $customerId = $customer?->id();
        }

        if ($customerId === null) {
            return response()->json([
                'success' => false,
                'message' => 'Customer identification required.',
            ], 422);
        }

        try {
            $command = new CreateOrderCommand(
                tenantId: $tenantId,
                customerId: (string) $customerId,
                currency: 'HNL',
                customerName: (string) ($validated['customer_name'] ?? ''),
                customerPhone: (string) ($validated['customer_phone'] ?? ''),
                customerEmail: $validated['customer_email'] ?? null,
                customerAddress: $validated['customer_address'] ?? null,
                customerNotes: $validated['customer_notes'] ?? null,
                deliveryType: (string) ($validated['delivery_type'] ?? 'pickup'),
                paymentMethod: (string) ($validated['payment_method'] ?? 'pending'),
                items: $validated['items'] ?? [],
                couponCode: $validated['coupon_code'] ?? null,
                requestorId: $request->user()?->id,
                sessionId: $validated['session_id'] ?? $request->header('X-Checkout-Session'),
                idempotencyKey: $request->header('Idempotency-Key') ?: null,
                schemaVersion: 1,
            );

            $result = $this->createOrderHandler->handle($command);

            $order = $this->orderReader->find($tenantId, $result->id);
            $whatsappUrl = is_object($order) && method_exists($order, 'getWhatsAppUrl')
                ? $order->getWhatsAppUrl()
                : null;

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => [
                    'order_number' => $result->orderNumber,
                    'order_id' => $result->id,
                    'whatsapp_url' => $whatsappUrl,
                ],
            ], 201);
        } catch (DomainException $e) {
            $this->log->warning('OrderController: Order creation failed (Domain logic)', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        } catch (\Throwable $e) {
            $this->log->error('OrderController: Critical failure during order creation', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Ocurrio un error inesperado al procesar tu pedido.',
            ], 500);
        }
    }
}
