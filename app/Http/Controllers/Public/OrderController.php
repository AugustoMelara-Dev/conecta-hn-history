<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Application\Sales\Commands\CreateOrderCommand;
use App\Application\Sales\Handlers\CreateOrderHandlerInterface;
use App\Application\TenantGovernance\Contracts\CapabilityManagerInterface;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Sales\Contracts\OrderReader;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreCheckoutRequest;
use App\Models\Location;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
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
        private readonly CapabilityManagerInterface $capabilityManager,
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

        $this->assertFulfillmentCapability(
            $tenantId,
            (string) ($validated['delivery_type'] ?? 'pickup')
        );

        $locationId = $this->resolveLocationId(
            $tenantId,
            $validated['location_id'] ?? null
        );

        try {
            $command = new CreateOrderCommand(
                tenantId: $tenantId,
                locationId: $locationId,
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

    private function assertFulfillmentCapability(string $tenantId, string $deliveryType): void
    {
        $capability = match ($deliveryType) {
            'pickup' => Capability::PICKUP_ORDERS,
            'delivery' => Capability::DELIVERY_ORDERS,
            'shipping' => Capability::SHIPPING_ORDERS,
            default => throw ValidationException::withMessages([
                'delivery_type' => 'Tipo de entrega no soportado.',
            ]),
        };

        if ($this->capabilityManager->hasForTenantId($tenantId, $capability)) {
            return;
        }

        $message = match ($deliveryType) {
            'pickup' => 'Este comercio no permite retiro en tienda.',
            'delivery' => 'Este comercio no ofrece entrega a domicilio.',
            'shipping' => 'Este comercio no realiza envios nacionales.',
            default => 'Tipo de entrega no soportado.',
        };

        throw ValidationException::withMessages([
            'delivery_type' => $message,
        ]);
    }

    private function resolveLocationId(string $tenantId, ?string $locationId): string
    {
        if ($locationId !== null && $locationId !== '') {
            $location = Location::query()
                ->where('id', $locationId)
                ->where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->first();

            if ($location !== null) {
                return (string) $location->id;
            }

            throw ValidationException::withMessages([
                'location_id' => 'Invalid or inactive location for this tenant.',
            ]);
        }

        $primaryLocationId = Location::query()
            ->where('tenant_id', $tenantId)
            ->where('is_primary', true)
            ->where('is_active', true)
            ->value('id');

        if ($primaryLocationId !== null) {
            return (string) $primaryLocationId;
        }

        throw ValidationException::withMessages([
            'location_id' => 'No active primary location found for this tenant.',
        ]);
    }
}
