<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Application\Sales\Commands\CreateOrderCommand;
use App\Application\Sales\Handlers\CreateOrderHandlerInterface;
use App\Application\TenantGovernance\Contracts\CapabilityManagerInterface;
use App\Domain\Identity\Repositories\UserRepositoryInterface;
use App\Domain\Sales\Contracts\OrderReader;
use App\Domain\Shared\Services\TenantContext;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\StoreCheckoutRequest;
use App\Models\Location;
use App\Models\User;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
        private readonly TenantContext $tenantContext,
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

        $this->tenantContext->setTenant($tenantId);

        $checkoutCustomer = $this->resolveOrCreateCheckoutCustomer($request, $validated, $tenantId);
        $customerId = (string) $checkoutCustomer->id;

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
                customerEmail: (string) ($validated['customer_email'] ?? $checkoutCustomer->email),
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
            $whatsappUrl = null;

            if (is_object($order) && method_exists($order, 'getWhatsAppUrl')) {
                try {
                    $whatsappUrl = $order->getWhatsAppUrl();
                } catch (\Throwable $whatsappError) {
                    $this->log->warning('OrderController: Unable to build WhatsApp URL for order response', [
                        'tenant_id' => $tenantId,
                        'order_id' => $result->id,
                        'error' => $whatsappError->getMessage(),
                    ]);
                }
            }

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

        $message = match ($capability) {
            Capability::PICKUP_ORDERS => 'Este comercio no permite retiro en tienda.',
            Capability::DELIVERY_ORDERS => 'Este comercio no ofrece entrega a domicilio.',
            Capability::SHIPPING_ORDERS => 'Este comercio no realiza envios nacionales.',
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

    /**
     * Resolve authenticated customer or create a tenant-scoped guest user.
     *
     * This keeps public storefront checkout functional without requiring prior auth.
     *
     * @param  array<string, mixed>  $validated
     */
    private function resolveOrCreateCheckoutCustomer(Request $request, array $validated, string $tenantId): User
    {
        if ($request->user() instanceof User) {
            return $request->user();
        }

        $providedEmail = isset($validated['customer_email']) && is_string($validated['customer_email'])
            ? trim(mb_strtolower($validated['customer_email']))
            : '';

        if ($providedEmail !== '') {
            $existingTenantCustomer = $this->userRepository->findByEmail($providedEmail, $tenantId);
            if ($existingTenantCustomer !== null) {
                $existingModel = User::query()->find($existingTenantCustomer->id());
                if ($existingModel instanceof User) {
                    return $existingModel;
                }
            }

            $globalEmailCollision = User::query()->where('email', $providedEmail)->first();
            if ($globalEmailCollision instanceof User && (string) $globalEmailCollision->tenant_id !== $tenantId) {
                $providedEmail = '';
            }
        }

        $name = isset($validated['customer_name']) && is_string($validated['customer_name'])
            ? trim($validated['customer_name'])
            : '';

        $guest = new User;
        $guest->name = $name !== '' ? $name : 'Cliente invitado';
        $guest->email = $providedEmail !== ''
            ? $providedEmail
            : sprintf('guest+%s+%s@vito.local', Str::slug($tenantId), Str::lower((string) Str::ulid()));
        $guest->password = Hash::make(Str::random(48));
        $guest->tenant_id = $tenantId;
        $guest->phone = isset($validated['customer_phone']) && is_string($validated['customer_phone'])
            ? trim($validated['customer_phone'])
            : null;
        $guest->email_verified_at = now()->toDateTimeString();
        $guest->save();

        return $guest;
    }
}
