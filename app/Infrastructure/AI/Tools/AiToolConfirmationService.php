<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tools;

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Application\Markets\TenantMarketResolver;
use App\Application\Sales\Contracts\PublicOrderCreator;
use App\Application\Sales\DTOs\CreatePublicOrderData;
use App\Domain\Access\Enums\Permission;
use App\Domain\Automation\ToolAuthorizationDecision;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Enums\PaymentMethod;
use App\Exceptions\Ai\AiToolConfirmationException;
use App\Exceptions\InvalidCouponException;
use App\Models\AiToolConfirmation;
use App\Models\Lead;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CouponValidatorService;
use App\Services\PriceCalculatorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final readonly class AiToolConfirmationService
{
    public function __construct(
        private PublicOrderCreator $orderCreator,
        private TenantPermissionGuardInterface $permissionGuard,
        private PriceCalculatorService $priceCalculator,
        private CouponValidatorService $couponValidator,
        private TenantMarketResolver $marketResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{confirmation: AiToolConfirmation, token: string|null, replayed: bool}
     */
    public function prepare(
        string $tenantId,
        User $actor,
        string $tool,
        array $payload,
        string $idempotencyKey,
    ): array {
        $this->assertEnabledTool($tool);
        $tenant = $this->assertActorTenant($tenantId, $actor);
        $validated = $this->validatePayload($tenantId, $tenant, $actor, $tool, $payload);
        $payloadHash = $this->payloadHash($validated);

        return DB::transaction(function () use (
            $tenantId,
            $actor,
            $tool,
            $validated,
            $payloadHash,
            $idempotencyKey,
        ): array {
            $token = Str::random(64);
            $confirmation = AiToolConfirmation::unguarded(fn (): AiToolConfirmation => AiToolConfirmation::query()->firstOrCreate([
                'tenant_id' => $tenantId,
                'actor_id' => $actor->getKey(),
                'idempotency_key' => $idempotencyKey,
            ], [
                'tool' => $tool,
                'payload' => $validated,
                'payload_hash' => $payloadHash,
                'confirmation_token' => $token,
                'confirmation_token_hash' => hash('sha256', $token),
                'status' => AiToolConfirmation::STATUS_PENDING,
                'expires_at' => now()->addSeconds(
                    max(60, (int) config('ai.tool_confirmations.ttl_seconds', 600)),
                ),
            ]));

            if (! hash_equals((string) $confirmation->payload_hash, $payloadHash)
                || ! hash_equals((string) $confirmation->tool, $tool)) {
                throw new AiToolConfirmationException(
                    'idempotency_conflict',
                    409,
                    'La clave de idempotencia ya fue utilizada con otra accion.',
                );
            }

            return [
                'confirmation' => $confirmation,
                'token' => $confirmation->status === AiToolConfirmation::STATUS_PENDING
                    ? (string) $confirmation->confirmation_token
                    : null,
                'replayed' => ! $confirmation->wasRecentlyCreated,
            ];
        }, 3);
    }

    /**
     * @return array{confirmation: AiToolConfirmation, result: array<string, mixed>, replayed: bool}
     */
    public function confirm(
        string $tenantId,
        User $actor,
        string $confirmationId,
        string $token,
    ): array {
        $tenant = $this->assertActorTenant($tenantId, $actor);

        $outcome = DB::transaction(function () use ($tenantId, $tenant, $actor, $confirmationId, $token): array {
            $confirmation = AiToolConfirmation::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($confirmationId)
                ->lockForUpdate()
                ->first();

            if (! $confirmation instanceof AiToolConfirmation
                || (string) $confirmation->actor_id !== (string) $actor->getKey()) {
                throw new AiToolConfirmationException(
                    'confirmation_not_found',
                    404,
                    'La confirmacion no existe o no pertenece al usuario.',
                );
            }

            if (! hash_equals((string) $confirmation->confirmation_token_hash, hash('sha256', $token))) {
                throw new AiToolConfirmationException(
                    'invalid_confirmation_token',
                    403,
                    'El token de confirmacion no es valido.',
                );
            }

            if ($confirmation->status === AiToolConfirmation::STATUS_COMPLETED) {
                return [
                    'confirmation' => $confirmation,
                    'result' => $confirmation->result ?? [],
                    'replayed' => true,
                ];
            }

            if ($confirmation->status === AiToolConfirmation::STATUS_CANCELLED) {
                throw new AiToolConfirmationException(
                    'confirmation_cancelled',
                    409,
                    'La accion fue cancelada y no puede ejecutarse.',
                );
            }

            if ($confirmation->status === AiToolConfirmation::STATUS_EXPIRED
                || $confirmation->expires_at->isPast()) {
                $confirmation->forceFill(['status' => AiToolConfirmation::STATUS_EXPIRED])->save();

                return ['expired' => true];
            }

            if ($confirmation->status !== AiToolConfirmation::STATUS_PENDING) {
                throw new AiToolConfirmationException(
                    'confirmation_state',
                    409,
                    'La confirmacion no esta disponible para ejecutar.',
                );
            }

            $this->assertEnabledTool((string) $confirmation->tool);
            $this->assertToolAuthorization($tenant, $actor, (string) $confirmation->tool);
            $payload = $confirmation->payload;
            if (! hash_equals((string) $confirmation->payload_hash, $this->payloadHash($payload))) {
                throw new AiToolConfirmationException(
                    'payload_integrity',
                    409,
                    'La accion no pudo verificarse de forma segura.',
                );
            }

            if ((string) $confirmation->tool === 'commerce.order.create') {
                $this->assertOrderQuoteStillValid($tenantId, $payload);
            }

            $result = $this->execute(
                $tenantId,
                $actor,
                $confirmation,
                $payload,
            );

            $confirmation->forceFill([
                'status' => AiToolConfirmation::STATUS_COMPLETED,
                'result' => $result,
                'executed_at' => now(),
            ])->save();

            return [
                'confirmation' => $confirmation,
                'result' => $result,
                'replayed' => false,
            ];
        }, 3);

        if (($outcome['expired'] ?? false) === true) {
            throw new AiToolConfirmationException(
                'confirmation_expired',
                410,
                'La confirmacion expiro. Prepara la accion nuevamente.',
            );
        }

        /** @var array{confirmation: AiToolConfirmation, result: array<string, mixed>, replayed: bool} $outcome */
        return $outcome;
    }

    public function cancel(
        string $tenantId,
        User $actor,
        string $confirmationId,
        string $token,
    ): AiToolConfirmation {
        $this->assertActorTenant($tenantId, $actor);

        return DB::transaction(function () use ($tenantId, $actor, $confirmationId, $token): AiToolConfirmation {
            $confirmation = AiToolConfirmation::query()
                ->where('tenant_id', $tenantId)
                ->whereKey($confirmationId)
                ->lockForUpdate()
                ->first();

            if (! $confirmation instanceof AiToolConfirmation
                || (string) $confirmation->actor_id !== (string) $actor->getKey()) {
                throw new AiToolConfirmationException(
                    'confirmation_not_found',
                    404,
                    'La confirmacion no existe o no pertenece al usuario.',
                );
            }

            if (! hash_equals((string) $confirmation->confirmation_token_hash, hash('sha256', $token))) {
                throw new AiToolConfirmationException(
                    'invalid_confirmation_token',
                    403,
                    'El token de confirmacion no es valido.',
                );
            }

            if ($confirmation->status === AiToolConfirmation::STATUS_COMPLETED) {
                throw new AiToolConfirmationException(
                    'confirmation_completed',
                    409,
                    'Una accion ejecutada no puede cancelarse.',
                );
            }

            if ($confirmation->status !== AiToolConfirmation::STATUS_CANCELLED) {
                $confirmation->forceFill([
                    'status' => AiToolConfirmation::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                ])->save();
            }

            return $confirmation;
        }, 3);
    }

    /**
     * Validates an already completed human confirmation for a workforce action.
     * It deliberately does not execute a tool or consume an idempotency claim;
     * those remain owned by the workflow and automation authorities.
     */
    public function authorizeAgentAction(
        string $tenantId,
        string $conversationId,
        string $actionKey,
        string $payloadHash,
        ?string $confirmationId,
        ?string $confirmationToken,
        bool $required,
    ): ToolAuthorizationDecision {
        if (! $required) {
            return ToolAuthorizationDecision::allow('confirmation_not_required');
        }
        if ($confirmationId === null || $confirmationToken === null) {
            return ToolAuthorizationDecision::deny('confirmation_required');
        }

        $confirmation = AiToolConfirmation::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($confirmationId)
            ->first();
        if (! $confirmation instanceof AiToolConfirmation
            || ! hash_equals((string) $confirmation->tool, $actionKey)
            || ! hash_equals((string) $confirmation->payload_hash, $payloadHash)
            || ! hash_equals((string) $confirmation->confirmation_token_hash, hash('sha256', $confirmationToken))) {
            return ToolAuthorizationDecision::deny('confirmation_binding_mismatch');
        }
        if ($confirmation->expires_at->isPast() || $confirmation->status === AiToolConfirmation::STATUS_EXPIRED) {
            return ToolAuthorizationDecision::deny('confirmation_expired');
        }
        if ($confirmation->status !== AiToolConfirmation::STATUS_COMPLETED) {
            return ToolAuthorizationDecision::deny('confirmation_pending');
        }

        if (! Str::isUuid($conversationId)) {
            return ToolAuthorizationDecision::deny('confirmation_binding_mismatch');
        }

        $conversationActorId = DB::table('ai_conversations')
            ->where('tenant_id', $tenantId)
            ->where('id', $conversationId)
            ->where('visibility', 'tenant_private')
            ->where('status', 'active')
            ->value('actor_id');
        if (! is_string($conversationActorId) || trim($conversationActorId) === '') {
            return ToolAuthorizationDecision::deny('confirmation_binding_mismatch');
        }
        if (! hash_equals((string) $confirmation->actor_id, $conversationActorId)) {
            return ToolAuthorizationDecision::deny('confirmation_binding_mismatch');
        }

        return ToolAuthorizationDecision::allow('confirmation_authorized', [
            'confirmation_id' => (string) $confirmation->getKey(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function responsePayload(
        AiToolConfirmation $confirmation,
        ?string $token = null,
        bool $includePreview = false,
    ): array {
        $payload = [
            'id' => (string) $confirmation->getKey(),
            'tool' => (string) $confirmation->tool,
            'status' => (string) $confirmation->status,
            'expires_at' => $confirmation->expires_at->toIso8601String(),
            'executed_at' => $confirmation->executed_at?->toIso8601String(),
            'cancelled_at' => $confirmation->cancelled_at?->toIso8601String(),
        ];
        if ($token !== null) {
            $payload['confirmation_token'] = $token;
        }
        if ($includePreview) {
            $payload['preview'] = $confirmation->payload;
        }

        return $payload;
    }

    private function assertEnabledTool(string $tool): void
    {
        if (! (bool) config('ai.tool_confirmations.enabled', false)) {
            throw new AiToolConfirmationException(
                'tool_confirmations_disabled',
                403,
                'Las acciones confirmables de IA no estan habilitadas.',
            );
        }

        $definitions = (array) config('ai.tools', []);
        $definition = $definitions[$tool] ?? null;
        if (! is_array($definition)
            || ! (bool) ($definition['confirmation_required'] ?? false)
            || ! in_array((string) ($definition['risk'] ?? ''), ['mutation', 'economic'], true)) {
            throw new AiToolConfirmationException(
                'tool_not_confirmable',
                422,
                'La herramienta solicitada no admite confirmacion.',
            );
        }
    }

    private function assertActorTenant(string $tenantId, User $actor): Tenant
    {
        $tenant = Str::isUuid($tenantId)
            ? Tenant::query()->whereKey($tenantId)->first()
            : null;
        if (! $tenant instanceof Tenant || ! $actor->canAccessTenant($tenant)) {
            throw new AiToolConfirmationException(
                'tenant_authorization',
                403,
                'No tenes permiso para confirmar acciones en este negocio.',
            );
        }

        return $tenant;
    }

    private function assertToolAuthorization(Tenant $tenant, User $actor, string $tool): void
    {
        $permission = match ($tool) {
            'tenant_lead.prepare' => Permission::AI_USE,
            'commerce.order.create' => Permission::ORDERS_CREATE,
            default => null,
        };

        if ($permission === null || ! $this->permissionGuard->allows(
            $actor,
            $tenant,
            Capability::AI_CONTENT,
            $permission,
        )) {
            throw new AiToolConfirmationException(
                'tool_authorization',
                403,
                'No tenes permiso para ejecutar esta accion.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validatePayload(
        string $tenantId,
        Tenant $tenant,
        User $actor,
        string $tool,
        array $payload,
    ): array {
        $this->assertToolAuthorization($tenant, $actor, $tool);

        return match ($tool) {
            'tenant_lead.prepare' => $this->validateLeadPayload($tenantId, $payload),
            'commerce.order.create' => $this->validateOrderPayload($tenant, $payload),
            default => throw new AiToolConfirmationException(
                'tool_not_confirmable',
                422,
                'La herramienta solicitada no admite confirmacion.',
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateLeadPayload(string $tenantId, array $payload): array
    {
        $validated = Validator::make($payload, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:30', 'required_without:email'],
            'message' => ['nullable', 'string', 'max:2000'],
            'product_id' => ['nullable', 'integer'],
        ])->validate();

        $productId = $validated['product_id'] ?? null;
        if (is_int($productId) && ! Product::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($productId)
            ->exists()) {
            throw ValidationException::withMessages([
                'payload.product_id' => 'El producto no pertenece al negocio activo.',
            ]);
        }

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validateOrderPayload(Tenant $tenant, array $payload): array
    {
        $validated = Validator::make($payload, [
            'customer_name' => ['required', 'string', 'max:255'],
            'customer_phone' => ['required', 'string', 'max:30'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'customer_address' => ['nullable', 'string', 'max:500'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
            'delivery_type' => ['required', Rule::in(['pickup', 'delivery'])],
            'payment_method' => ['required', Rule::in(PaymentMethod::values())],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.product_id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'items.*.options' => ['nullable', 'array'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ])->validate();

        $productIds = collect($validated['items'])
            ->pluck('product_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
        $validCount = Product::query()
            ->where('tenant_id', (string) $tenant->getKey())
            ->whereIn('id', $productIds)
            ->where('is_active', true)
            ->where('is_visible', true)
            ->count();
        if ($validCount !== $productIds->count()) {
            throw ValidationException::withMessages([
                'payload.items' => 'Todos los productos deben estar activos y pertenecer al negocio.',
            ]);
        }

        $validated['quote'] = $this->buildOrderQuote($tenant, $validated);

        return $validated;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function buildOrderQuote(Tenant $tenant, array $payload, bool $lock = false): array
    {
        $market = $this->marketResolver->resolve($tenant);
        $deliveryType = (string) $payload['delivery_type'];
        $attributes = $tenant->getAttributes();
        $deliveryEnabled = (bool) ($attributes['delivery_enabled'] ?? true);
        $pickupEnabled = (bool) ($attributes['pickup_enabled'] ?? true);

        if (($deliveryType === 'delivery' && ! $deliveryEnabled)
            || ($deliveryType === 'pickup' && ! $pickupEnabled)) {
            throw ValidationException::withMessages([
                'payload.delivery_type' => 'El metodo de entrega seleccionado no esta disponible.',
            ]);
        }
        if ($deliveryType === 'delivery' && trim((string) ($payload['customer_address'] ?? '')) === '') {
            throw ValidationException::withMessages([
                'payload.customer_address' => 'La direccion es obligatoria para entrega a domicilio.',
            ]);
        }

        $productIds = collect($payload['items'])
            ->pluck('product_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->sort()
            ->values();
        // INFRA_TENANT_FILTER: confirmation authority is server-derived and every product is constrained to that exact tenant.
        $query = Product::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', (string) $tenant->getKey())
            ->whereIn('id', $productIds)
            ->where('is_active', true)
            ->where('is_visible', true)
            ->whereNull('deleted_at')
            ->orderBy('id');
        if ($lock) {
            $query->lockForUpdate();
        }
        $products = $query->get()->keyBy('id');

        if ($products->count() !== $productIds->count()) {
            throw ValidationException::withMessages([
                'payload.items' => 'Uno o mas productos ya no estan disponibles.',
            ]);
        }

        $requestedByProduct = collect($payload['items'])
            ->groupBy('product_id')
            ->map(static fn ($items): int => (int) $items->sum('quantity'));
        foreach ($requestedByProduct as $productId => $quantity) {
            /** @var Product $product */
            $product = $products->get((int) $productId);
            if ((bool) $product->manage_stock
                && (int) ($product->stock_quantity ?? 0) < $quantity
                && ! (bool) ($product->allow_backorders ?? false)) {
                throw ValidationException::withMessages([
                    'payload.items' => "Stock insuficiente para '{$product->name}'.",
                ]);
            }
        }

        $calculatedItems = $this->priceCalculator->calculate($payload['items'], $products);
        $subtotal = round((float) collect($calculatedItems)->sum('line_total'), 2);
        $minimumOrder = round((float) ($attributes['minimum_order'] ?? 0), 2);
        if ($subtotal < $minimumOrder) {
            throw ValidationException::withMessages([
                'payload.items' => "El pedido mínimo de este negocio es {$minimumOrder} {$market->currencyCode}.",
            ]);
        }

        $discount = 0.0;
        if (isset($payload['coupon_code']) && trim((string) $payload['coupon_code']) !== '') {
            $coupon = $this->couponValidator->validate(
                (string) $payload['coupon_code'],
                null,
                $subtotal,
                $tenant,
            );
            $discount = round((float) $coupon->calculateDiscountUnsafe($subtotal), 2);
        }

        $deliveryFee = 0.0;
        $items = array_map(static function (array $item) use ($products): array {
            /** @var Product $product */
            $product = $products->get((int) $item['product_id']);

            return [
                'product_id' => (int) $item['product_id'],
                'product_name' => (string) $product->name,
                'base_price' => round((float) $product->price, 2),
                'unit_price' => round((float) $item['unit_price'], 2),
                'quantity' => (int) $item['quantity'],
                'line_total' => round((float) $item['line_total'], 2),
                'validated_options' => $item['validated_options'] ?? null,
            ];
        }, $calculatedItems);

        return [
            'currency' => $market->currencyCode,
            'delivery_type' => $deliveryType,
            'items' => $items,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'delivery_fee' => $deliveryFee,
            'total' => round(max(0, $subtotal - $discount + $deliveryFee), 2),
        ];
    }

    /** @param array<string, mixed> $payload */
    private function assertOrderQuoteStillValid(string $tenantId, array $payload): void
    {
        try {
            // INFRA_TENANT_FILTER: lock the exact server-authorized tenant before revalidating the economic quote.
            $tenant = Tenant::query()
                ->withoutGlobalScopes()
                ->whereKey($tenantId)
                ->where('is_active', true)
                ->whereNull('publication_paused_at')
                ->lockForUpdate()
                ->firstOrFail();
            $currentQuote = $this->buildOrderQuote($tenant, $payload, lock: true);
        } catch (ValidationException|InvalidCouponException) {
            throw new AiToolConfirmationException(
                'quote_changed',
                409,
                'La oferta cambio. Revisa precios y disponibilidad antes de confirmar nuevamente.',
            );
        }

        $confirmedQuote = is_array($payload['quote'] ?? null) ? $payload['quote'] : [];
        if (! hash_equals($this->payloadHash($confirmedQuote), $this->payloadHash($currentQuote))) {
            throw new AiToolConfirmationException(
                'quote_changed',
                409,
                'La oferta cambio. Revisa precios y disponibilidad antes de confirmar nuevamente.',
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function execute(
        string $tenantId,
        User $actor,
        AiToolConfirmation $confirmation,
        array $payload,
    ): array {
        $result = match ((string) $confirmation->tool) {
            'tenant_lead.prepare' => $this->createLead($tenantId, $payload),
            'commerce.order.create' => $this->createOrder($tenantId, $confirmation, $payload),
            default => throw new AiToolConfirmationException(
                'tool_not_confirmable',
                422,
                'La herramienta solicitada no admite confirmacion.',
            ),
        };

        Log::info('AI confirmed tool executed', [
            'confirmation_id' => (string) $confirmation->getKey(),
            'tool' => (string) $confirmation->tool,
            'actor_id' => (string) $actor->getKey(),
            'tenant_hash' => hash('sha256', $tenantId),
        ]);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function createLead(string $tenantId, array $payload): array
    {
        $lead = new Lead;
        $lead->forceFill([
            'tenant_id' => $tenantId,
            'name' => $payload['name'],
            'email' => $payload['email'] ?? null,
            'phone' => $payload['phone'] ?? null,
            'message' => $payload['message'] ?? null,
            'product_id' => $payload['product_id'] ?? null,
            'source' => 'private_ai_confirmed',
            'status' => 'new',
        ])->save();

        return [
            'type' => 'lead',
            'lead_id' => (int) $lead->getKey(),
            'status' => 'new',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function createOrder(
        string $tenantId,
        AiToolConfirmation $confirmation,
        array $payload,
    ): array {
        $items = array_map(
            static fn (array $item): array => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
                'options' => $item['options'] ?? [],
                'notes' => $item['notes'] ?? null,
            ],
            $payload['items'],
        );

        $result = $this->orderCreator->create(new CreatePublicOrderData(
            tenantId: $tenantId,
            idempotencyKey: 'ai-tool:'.(string) $confirmation->getKey(),
            customerName: (string) $payload['customer_name'],
            customerPhone: (string) $payload['customer_phone'],
            customerEmail: isset($payload['customer_email']) ? (string) $payload['customer_email'] : null,
            customerAddress: isset($payload['customer_address']) ? (string) $payload['customer_address'] : null,
            customerNotes: isset($payload['customer_notes']) ? (string) $payload['customer_notes'] : null,
            deliveryType: (string) $payload['delivery_type'],
            paymentMethod: (string) $payload['payment_method'],
            items: $items,
            couponCode: isset($payload['coupon_code'])
                ? strtoupper((string) $payload['coupon_code'])
                : null,
            userId: null,
            source: 'private_ai_confirmed',
        ));

        return ['type' => 'order'] + $result->toArray();
    }

    /** @param array<string, mixed> $payload */
    private function payloadHash(array $payload): string
    {
        return hash('sha256', json_encode(
            $this->canonicalize($payload),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return array_map(fn (mixed $item): mixed => $this->canonicalize($item), $value);
    }
}
