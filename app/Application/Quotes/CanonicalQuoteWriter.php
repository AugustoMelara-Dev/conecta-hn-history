<?php

declare(strict_types=1);

namespace App\Application\Quotes;

// @withoutGlobalScopes-safety CANONICAL_WRITER_TENANT_FILTER: every bypass is constrained by command tenant ownership.

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Application\Availability\AvailabilityQuery;
use App\Application\Markets\TenantMarketResolver;
use App\Application\Orders\CanonicalOrderWriter;
use App\Application\Orders\Commands\CreateOrderCommand;
use App\Application\Quotes\Commands\CreateQuoteCommand;
use App\Application\Quotes\Commands\ReviseQuoteCommand;
use App\Application\Quotes\Commands\TransitionQuoteCommand;
use App\Application\Reservations\CanonicalReservationWriter;
use App\Application\Reservations\Commands\CreateReservationCommand;
use App\Domain\Access\Enums\Permission;
use App\Domain\Operations\Events\BusinessOperationChanged;
use App\Domain\Shared\OutboxRepositoryInterface;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\CrmDeal;
use App\Models\CustomerProfile;
use App\Models\Lead;
use App\Models\Location;
use App\Models\Product;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Authoritative transaction owner for durable commercial quotes.
 *
 * Quotes are commercial records, not invoices or claims of legal acceptance.
 */
final readonly class CanonicalQuoteWriter
{
    public function __construct(
        private TenantPermissionGuardInterface $permissionGuard,
        private TenantMarketResolver $marketResolver,
        private OutboxRepositoryInterface $outbox,
        private CanonicalOrderWriter $orders,
        private CanonicalReservationWriter $reservations,
        private AvailabilityQuery $availability,
    ) {}

    public function create(CreateQuoteCommand $command): QuoteWriteResult
    {
        $this->assertCreate($command);

        return DB::transaction(function () use ($command): QuoteWriteResult {
            [$tenant, $actor] = $this->authority(
                $command->tenantId,
                $command->actorId,
                Permission::CRM_DEALS_CREATE,
            );
            $payloadHash = $this->hash([
                'location_id' => $command->locationId,
                'customer_profile_id' => $command->customerProfileId,
                'lead_id' => $command->leadId,
                'crm_deal_id' => $command->crmDealId,
                'source' => $command->source,
                'expires_at' => $command->expiresAt?->utc()->toIso8601String(),
                'items' => $command->items,
            ]);
            $existing = $this->idempotentQuote($command->tenantId, $command->idempotencyKey);
            if ($existing instanceof Quote) {
                $this->assertReplay($existing, $payloadHash);

                return new QuoteWriteResult($existing->load('items'), true);
            }
            $market = $this->marketResolver->resolve($tenant);
            $location = $this->location($command->tenantId, $command->locationId, $market->currencyCode);
            $this->reference(CustomerProfile::class, $command->tenantId, $command->customerProfileId);
            $this->reference(Lead::class, $command->tenantId, $command->leadId);
            $this->reference(CrmDeal::class, $command->tenantId, $command->crmDealId);
            [$items, $totals] = $this->priceItems(
                $command->tenantId,
                strtoupper($market->currencyCode),
                $command->items,
            );
            $quote = $this->persistQuote([
                'tenant_id' => $command->tenantId,
                'public_id' => (string) Str::uuid(),
                'customer_profile_id' => $command->customerProfileId,
                'lead_id' => $command->leadId,
                'crm_deal_id' => $command->crmDealId,
                'location_id' => $location->getKey(),
                'created_by_user_id' => $actor->getKey(),
                'status' => Quote::STATUS_DRAFT,
                'source' => trim($command->source),
                'currency' => strtoupper($market->currencyCode),
                'version' => 1,
                ...$totals,
                'idempotency_key' => $command->idempotencyKey,
                'payload_hash' => $payloadHash,
                'expires_at' => $command->expiresAt?->utc(),
            ], $items);
            $this->audit($actor, $quote, 'quote.created');

            return new QuoteWriteResult($quote);
        }, 3);
    }

    public function revise(ReviseQuoteCommand $command): QuoteWriteResult
    {
        $this->assertRevise($command);

        return DB::transaction(function () use ($command): QuoteWriteResult {
            [$tenant, $actor] = $this->authority(
                $command->tenantId,
                $command->actorId,
                Permission::CRM_DEALS_UPDATE,
            );
            $source = Quote::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $command->tenantId)
                ->lockForUpdate()
                ->findOrFail($command->quoteId);
            if (! in_array($source->status, [Quote::STATUS_DRAFT, Quote::STATUS_ISSUED], true)) {
                throw ValidationException::withMessages([
                    'status' => ['Only draft or issued quotes can be revised.'],
                ]);
            }
            $payloadHash = $this->hash([
                'supersedes_quote_id' => $source->getKey(),
                'items' => $command->items,
                'expires_at' => $command->expiresAt?->utc()->toIso8601String(),
            ]);
            $existing = $this->idempotentQuote($command->tenantId, $command->idempotencyKey);
            if ($existing instanceof Quote) {
                $this->assertReplay($existing, $payloadHash);

                return new QuoteWriteResult($existing->load('items'), true);
            }
            [$items, $totals] = $this->priceItems(
                $command->tenantId,
                (string) $source->currency,
                $command->items,
            );
            $revised = $this->persistQuote([
                'tenant_id' => $command->tenantId,
                'public_id' => (string) Str::uuid(),
                'customer_profile_id' => $source->customer_profile_id,
                'lead_id' => $source->lead_id,
                'crm_deal_id' => $source->crm_deal_id,
                'location_id' => $source->location_id,
                'created_by_user_id' => $actor->getKey(),
                'supersedes_quote_id' => $source->getKey(),
                'status' => Quote::STATUS_DRAFT,
                'source' => $source->source,
                'currency' => $source->currency,
                'version' => ((int) $source->version) + 1,
                ...$totals,
                'idempotency_key' => $command->idempotencyKey,
                'payload_hash' => $payloadHash,
                'expires_at' => $command->expiresAt?->utc() ?? $source->expires_at,
            ], $items);
            $this->audit($actor, $revised, 'quote.revised', [
                'supersedes_quote_id' => (string) $source->getKey(),
            ]);

            return new QuoteWriteResult($revised);
        }, 3);
    }

    public function issue(TransitionQuoteCommand $command): QuoteWriteResult
    {
        if ($command->targetStatus !== Quote::STATUS_ISSUED) {
            throw new \InvalidArgumentException('Quote issue requires the issued target status.');
        }

        return $this->transition($command);
    }

    public function expire(TransitionQuoteCommand $command): QuoteWriteResult
    {
        if ($command->targetStatus !== Quote::STATUS_EXPIRED) {
            throw new \InvalidArgumentException('Quote expiry requires the expired target status.');
        }

        return $this->transition($command);
    }

    public function reject(TransitionQuoteCommand $command): QuoteWriteResult
    {
        if ($command->targetStatus !== Quote::STATUS_REJECTED) {
            throw new \InvalidArgumentException('Quote rejection requires the rejected target status.');
        }

        return $this->transition($command);
    }

    public function cancel(TransitionQuoteCommand $command): QuoteWriteResult
    {
        if ($command->targetStatus !== Quote::STATUS_CANCELLED) {
            throw new \InvalidArgumentException('Quote cancellation requires the cancelled target status.');
        }

        return $this->transition($command);
    }

    public function accept(TransitionQuoteCommand $command): QuoteWriteResult
    {
        if ($command->targetStatus !== Quote::STATUS_ACCEPTED) {
            throw new \InvalidArgumentException('Quote acceptance requires the accepted target status.');
        }
        $this->assertTransition($command);

        return DB::transaction(function () use ($command): QuoteWriteResult {
            [, $actor] = $this->authority(
                $command->tenantId,
                $command->actorId,
                Permission::CRM_DEALS_UPDATE,
            );
            $quote = Quote::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $command->tenantId)
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($command->quoteId);
            if ($quote->status === Quote::STATUS_ACCEPTED) {
                return new QuoteWriteResult($quote, true);
            }
            if ($quote->status !== Quote::STATUS_ISSUED) {
                throw ValidationException::withMessages([
                    'status' => ['Only issued quotes can be accepted.'],
                ]);
            }
            if ($quote->expires_at !== null && ! $quote->expires_at->isFuture()) {
                $quote->forceFill(['status' => Quote::STATUS_EXPIRED])->save();
                $this->audit($actor, $quote, 'quote.expired');

                return new QuoteWriteResult($quote, outcome: 'quote_expired');
            }
            if ($this->hasCatalogDrift($quote)) {
                $revision = $this->revise(new ReviseQuoteCommand(
                    tenantId: $command->tenantId,
                    actorId: $command->actorId,
                    quoteId: $command->quoteId,
                    idempotencyKey: 'quote-drift:'.substr(hash(
                        'sha256',
                        $command->quoteId.'|'.$command->idempotencyKey,
                    ), 0, 120),
                    items: $this->rawItems($quote),
                    expiresAt: $quote->expires_at,
                ))->quote;

                return new QuoteWriteResult(
                    quote: $quote,
                    outcome: 'quote_changed',
                    revisedQuote: $revision,
                );
            }
            $quote->forceFill([
                'status' => Quote::STATUS_ACCEPTED,
                'accepted_at' => now(),
            ])->save();
            $this->audit($actor, $quote, 'quote.accepted');

            return new QuoteWriteResult($quote);
        }, 3);
    }

    public function convertToOrder(TransitionQuoteCommand $command): QuoteWriteResult
    {
        if ($command->targetStatus !== Quote::ACTION_CONVERT_TO_ORDER) {
            throw new \InvalidArgumentException('Order conversion requires the convert-to-order action.');
        }
        $this->assertTransition($command);

        return DB::transaction(function () use ($command): QuoteWriteResult {
            [, $actor] = $this->authority(
                $command->tenantId,
                $command->actorId,
                Permission::CRM_DEALS_UPDATE,
            );
            $quote = Quote::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $command->tenantId)
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($command->quoteId);
            if ($quote->converted_order_id !== null) {
                return new QuoteWriteResult($quote, true, 'converted_to_order');
            }
            if ($quote->status !== Quote::STATUS_ACCEPTED
                || $quote->converted_appointment_id !== null) {
                throw ValidationException::withMessages([
                    'status' => ['Only an accepted, unconverted quote can become an order.'],
                ]);
            }
            if ($quote->items->contains(fn (QuoteItem $item): bool => $item->product_id === null)
                || $quote->items->contains(fn (QuoteItem $item): bool => $item->discount_minor !== 0 || $item->tax_minor !== 0)) {
                throw ValidationException::withMessages([
                    'items' => ['Order conversion currently requires product-only items without unsupported quote adjustments.'],
                ]);
            }
            $metadata = $command->metadata;
            $order = $this->orders->create(new CreateOrderCommand(
                tenantId: $command->tenantId,
                actorId: $command->actorId,
                idempotencyKey: 'q2o:'.substr(hash('sha256', $command->quoteId), 0, 60),
                customerName: (string) ($metadata['customer_name'] ?? ''),
                customerPhone: (string) ($metadata['customer_phone'] ?? ''),
                customerEmail: isset($metadata['customer_email'])
                    ? (string) $metadata['customer_email']
                    : null,
                customerAddress: isset($metadata['customer_address'])
                    ? (string) $metadata['customer_address']
                    : null,
                customerNotes: isset($metadata['customer_notes'])
                    ? (string) $metadata['customer_notes']
                    : null,
                deliveryType: (string) ($metadata['delivery_type'] ?? ''),
                paymentMethod: (string) ($metadata['payment_method'] ?? ''),
                items: $quote->items->map(fn (QuoteItem $item): array => [
                    'product_id' => (int) $item->product_id,
                    'quantity' => (int) $item->quantity,
                ])->all(),
                source: 'quote_conversion',
                requestedCurrency: (string) $quote->currency,
            ))->order;
            $minorUnit = (int) DB::table('currencies')
                ->where('code', $quote->currency)
                ->value('minor_unit');
            $orderTotalMinor = (int) round((float) $order->total * (10 ** $minorUnit));
            if ($orderTotalMinor !== (int) $quote->total_minor) {
                throw ValidationException::withMessages([
                    'quote' => ['Order conversion economics no longer match the accepted quote.'],
                ]);
            }
            $quote->forceFill(['converted_order_id' => $order->getKey()])->save();
            $this->audit($actor, $quote, 'quote.converted_to_order', [
                'order_id' => (int) $order->getKey(),
            ]);

            return new QuoteWriteResult($quote, outcome: 'converted_to_order');
        }, 3);
    }

    public function convertToReservation(TransitionQuoteCommand $command): QuoteWriteResult
    {
        if ($command->targetStatus !== Quote::ACTION_CONVERT_TO_RESERVATION) {
            throw new \InvalidArgumentException('Reservation conversion requires the convert-to-reservation action.');
        }
        $this->assertTransition($command);

        return DB::transaction(function () use ($command): QuoteWriteResult {
            [, $actor] = $this->authority(
                $command->tenantId,
                $command->actorId,
                Permission::CRM_DEALS_UPDATE,
            );
            $quote = Quote::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $command->tenantId)
                ->with('items')
                ->lockForUpdate()
                ->findOrFail($command->quoteId);
            if ($quote->converted_appointment_id !== null) {
                return new QuoteWriteResult($quote, true, 'converted_to_reservation');
            }
            if ($quote->status !== Quote::STATUS_ACCEPTED
                || $quote->converted_order_id !== null
                || $quote->items->count() !== 1) {
                throw ValidationException::withMessages([
                    'status' => ['Only an accepted, unconverted single-service quote can become a reservation.'],
                ]);
            }
            /** @var QuoteItem $item */
            $item = $quote->items->first();
            if ($item->service_id === null
                || $item->product_id !== null
                || $item->quantity !== 1
                || $item->discount_minor !== 0
                || $item->tax_minor !== 0) {
                throw ValidationException::withMessages([
                    'items' => ['Reservation conversion requires one unadjusted service item.'],
                ]);
            }
            $itemMetadata = is_array($item->metadata) ? $item->metadata : [];
            $providerId = (int) ($itemMetadata['provider_id'] ?? 0);
            $startsAt = isset($itemMetadata['starts_at_utc'])
                ? CarbonImmutable::parse((string) $itemMetadata['starts_at_utc'])->utc()
                : null;
            if ($providerId < 1 || ! $startsAt instanceof CarbonImmutable) {
                throw ValidationException::withMessages([
                    'items' => ['Reservation quote requires provider and UTC start metadata.'],
                ]);
            }
            $metadata = $command->metadata;
            $appointment = $this->reservations->create(new CreateReservationCommand(
                tenantId: $command->tenantId,
                actorId: $command->actorId,
                idempotencyKey: 'q2r:'.hash('sha256', $command->quoteId),
                serviceId: (int) $item->service_id,
                providerId: $providerId,
                locationId: (string) $quote->location_id,
                startsAtUtc: $startsAt,
                customerName: (string) ($metadata['customer_name'] ?? ''),
                customerEmail: (string) ($metadata['customer_email'] ?? ''),
                customerPhone: (string) ($metadata['customer_phone'] ?? ''),
                customerNotes: isset($metadata['customer_notes'])
                    ? (string) $metadata['customer_notes']
                    : null,
                source: 'quote_conversion',
            ))->appointment;
            if ((int) $appointment->price_minor !== (int) $item->unit_price_minor
                || (string) $appointment->currency !== (string) $quote->currency
                || (int) $appointment->duration_minutes !== (int) ($itemMetadata['duration_minutes_snapshot'] ?? 0)) {
                throw ValidationException::withMessages([
                    'quote' => ['Reservation conversion economics no longer match the accepted quote.'],
                ]);
            }
            $quote->forceFill(['converted_appointment_id' => $appointment->getKey()])->save();
            $this->audit($actor, $quote, 'quote.converted_to_reservation', [
                'appointment_id' => (int) $appointment->getKey(),
            ]);

            return new QuoteWriteResult($quote, outcome: 'converted_to_reservation');
        }, 3);
    }

    public function transition(TransitionQuoteCommand $command): QuoteWriteResult
    {
        $this->assertTransition($command);

        return DB::transaction(function () use ($command): QuoteWriteResult {
            [, $actor] = $this->authority(
                $command->tenantId,
                $command->actorId,
                Permission::CRM_DEALS_UPDATE,
            );
            $quote = Quote::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $command->tenantId)
                ->lockForUpdate()
                ->findOrFail($command->quoteId);
            if ($quote->status === $command->targetStatus) {
                return new QuoteWriteResult($quote->load('items'), true);
            }
            $attributes = match ($command->targetStatus) {
                Quote::STATUS_ISSUED => $this->issueAttributes($quote),
                Quote::STATUS_EXPIRED => $this->expiryAttributes($quote),
                Quote::STATUS_REJECTED => $this->terminalAttributes($quote, Quote::STATUS_REJECTED),
                Quote::STATUS_CANCELLED => $this->terminalAttributes($quote, Quote::STATUS_CANCELLED),
                default => throw ValidationException::withMessages([
                    'status' => ['Unsupported quote lifecycle transition.'],
                ]),
            };
            $quote->forceFill($attributes)->save();
            $this->audit($actor, $quote, 'quote.'.$command->targetStatus, [
                'reason' => $command->reason,
            ]);

            return new QuoteWriteResult($quote->load('items'));
        }, 3);
    }

    /** @return array{Tenant, User} */
    private function authority(string $tenantId, string $actorId, Permission $permission): array
    {
        $tenant = Tenant::query()
            ->withoutGlobalScopes()
            ->whereKey($tenantId)
            ->where('is_active', true)
            ->lockForUpdate()
            ->firstOrFail();
        $actor = User::query()->withoutGlobalScopes()->findOrFail($actorId);
        if (! $this->permissionGuard->allows(
            $actor,
            $tenant,
            Capability::CRM_PIPELINE_MANAGEMENT,
            $permission,
        )) {
            throw new AuthorizationException('Actor cannot manage quotes for this tenant.');
        }

        return [$tenant, $actor];
    }

    private function location(string $tenantId, string $locationId, string $currency): Location
    {
        $location = Location::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->findOrFail($locationId);
        if (strtoupper((string) $location->currency_code) !== strtoupper($currency)) {
            throw ValidationException::withMessages([
                'location_id' => ['Quote location currency does not match the tenant market.'],
            ]);
        }

        return $location;
    }

    /** @param class-string<Model> $model */
    private function reference(string $model, string $tenantId, ?int $id): ?Model
    {
        if ($id === null) {
            return null;
        }

        return $model::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
    }

    /**
     * @param  list<array<string, mixed>>  $rawItems
     * @return array{list<array<string, mixed>>, array{subtotal_minor: int, discount_minor: int, tax_minor: int, total_minor: int}}
     */
    private function priceItems(string $tenantId, string $currency, array $rawItems): array
    {
        $items = [];
        $subtotal = 0;
        $discount = 0;
        $tax = 0;
        foreach ($rawItems as $index => $raw) {
            $productId = isset($raw['product_id']) ? (int) $raw['product_id'] : null;
            $serviceId = isset($raw['service_id']) ? (int) $raw['service_id'] : null;
            if (($productId === null) === ($serviceId === null)) {
                throw ValidationException::withMessages([
                    "items.{$index}" => ['Each quote item must reference exactly one product or service.'],
                ]);
            }
            $catalog = $productId !== null
                ? Product::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('is_visible', true)
                    ->lockForUpdate()
                    ->findOrFail($productId)
                : Service::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $tenantId)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->findOrFail($serviceId);
            if ($catalog->price_minor === null
                || strtoupper((string) $catalog->currency) !== strtoupper($currency)) {
                throw ValidationException::withMessages([
                    "items.{$index}" => ['Catalog item lacks a compatible canonical price snapshot.'],
                ]);
            }
            $quantity = (int) ($raw['quantity'] ?? 0);
            $lineDiscount = (int) ($raw['discount_minor'] ?? 0);
            $lineTax = (int) ($raw['tax_minor'] ?? 0);
            if ($quantity < 1 || $lineDiscount < 0 || $lineTax < 0) {
                throw ValidationException::withMessages([
                    "items.{$index}" => ['Quantity and money adjustments must be valid non-negative minor units.'],
                ]);
            }
            $lineSubtotal = (int) $catalog->price_minor * $quantity;
            if ($lineDiscount > $lineSubtotal + $lineTax) {
                throw ValidationException::withMessages([
                    "items.{$index}.discount_minor" => ['Discount cannot exceed the priced line.'],
                ]);
            }
            $lineTotal = $lineSubtotal - $lineDiscount + $lineTax;
            $metadata = is_array($raw['metadata'] ?? null) ? $raw['metadata'] : [];
            if ($serviceId !== null) {
                $metadata['duration_minutes_snapshot'] = (int) $catalog->duration_minutes;
            }
            $items[] = [
                'product_id' => $productId,
                'service_id' => $serviceId,
                'description' => (string) $catalog->name,
                'quantity' => $quantity,
                'unit_price_minor' => (int) $catalog->price_minor,
                'discount_minor' => $lineDiscount,
                'tax_minor' => $lineTax,
                'line_total_minor' => $lineTotal,
                'metadata' => $metadata === [] ? null : $metadata,
            ];
            $subtotal += $lineSubtotal;
            $discount += $lineDiscount;
            $tax += $lineTax;
        }

        return [$items, [
            'subtotal_minor' => $subtotal,
            'discount_minor' => $discount,
            'tax_minor' => $tax,
            'total_minor' => $subtotal - $discount + $tax,
        ]];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array<string, mixed>>  $items
     */
    private function persistQuote(array $attributes, array $items): Quote
    {
        $quote = new Quote;
        $quote->forceFill(['id' => (string) Str::uuid(), ...$attributes])->save();
        foreach ($items as $item) {
            $quoteItem = new QuoteItem;
            $quoteItem->forceFill(['quote_id' => $quote->getKey(), ...$item])->save();
        }

        return $quote->load('items');
    }

    private function idempotentQuote(string $tenantId, string $key): ?Quote
    {
        return Quote::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('idempotency_key', $key)
            ->lockForUpdate()
            ->first();
    }

    private function hasCatalogDrift(Quote $quote): bool
    {
        foreach ($quote->items as $item) {
            $catalog = $item->product_id !== null
                ? Product::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $quote->tenant_id)
                    ->where('is_visible', true)
                    ->lockForUpdate()
                    ->find($item->product_id)
                : Service::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $quote->tenant_id)
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->find($item->service_id);
            if (! $catalog instanceof Model
                || $catalog->price_minor === null
                || (int) $catalog->price_minor !== (int) $item->unit_price_minor
                || strtoupper((string) $catalog->currency) !== strtoupper((string) $quote->currency)
                || (string) $catalog->name !== (string) $item->description) {
                return true;
            }
            if ($item->service_id !== null) {
                $metadata = is_array($item->metadata) ? $item->metadata : [];
                $providerId = (int) ($metadata['provider_id'] ?? 0);
                try {
                    $startsAt = isset($metadata['starts_at_utc'])
                        ? CarbonImmutable::parse((string) $metadata['starts_at_utc'])->utc()
                        : null;
                } catch (\Throwable) {
                    return true;
                }
                if ($providerId < 1
                    || ! $startsAt instanceof CarbonImmutable
                    || (int) ($metadata['duration_minutes_snapshot'] ?? 0) !== (int) $catalog->duration_minutes) {
                    return true;
                }
                $decision = $this->availability->assertAvailable(
                    (string) $quote->tenant_id,
                    (int) $item->service_id,
                    $providerId,
                    (string) $quote->location_id,
                    $startsAt,
                    $startsAt->addMinutes((int) $catalog->duration_minutes),
                );
                if (! $decision->available) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return list<array<string, mixed>> */
    private function rawItems(Quote $quote): array
    {
        return $quote->items->map(fn (QuoteItem $item): array => array_filter([
            'product_id' => $item->product_id === null ? null : (int) $item->product_id,
            'service_id' => $item->service_id === null ? null : (int) $item->service_id,
            'quantity' => (int) $item->quantity,
            'discount_minor' => (int) $item->discount_minor,
            'tax_minor' => (int) $item->tax_minor,
            'metadata' => $item->metadata,
        ], static fn (mixed $value): bool => $value !== null))->all();
    }

    private function assertReplay(Quote $quote, string $payloadHash): void
    {
        if (! hash_equals((string) $quote->payload_hash, $payloadHash)) {
            throw ValidationException::withMessages([
                'idempotency_key' => ['Quote idempotency key was reused with another payload.'],
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function issueAttributes(Quote $quote): array
    {
        if ($quote->status !== Quote::STATUS_DRAFT) {
            throw ValidationException::withMessages(['status' => ['Only draft quotes can be issued.']]);
        }
        if ($quote->expires_at !== null && ! $quote->expires_at->isFuture()) {
            throw ValidationException::withMessages(['expires_at' => ['An expired quote cannot be issued.']]);
        }

        return ['status' => Quote::STATUS_ISSUED, 'issued_at' => now()];
    }

    /** @return array<string, mixed> */
    private function expiryAttributes(Quote $quote): array
    {
        if ($quote->status !== Quote::STATUS_ISSUED
            || $quote->expires_at === null
            || $quote->expires_at->isFuture()) {
            throw ValidationException::withMessages([
                'expires_at' => ['Only an issued quote past its explicit expiry can expire.'],
            ]);
        }

        return ['status' => Quote::STATUS_EXPIRED];
    }

    /** @return array<string, mixed> */
    private function terminalAttributes(Quote $quote, string $status): array
    {
        if (! in_array($quote->status, [Quote::STATUS_DRAFT, Quote::STATUS_ISSUED], true)) {
            throw ValidationException::withMessages(['status' => ['Quote is already terminal.']]);
        }

        return match ($status) {
            Quote::STATUS_REJECTED => ['status' => $status, 'rejected_at' => now()],
            Quote::STATUS_CANCELLED => ['status' => $status, 'cancelled_at' => now()],
            default => throw new \LogicException('Unsupported terminal quote status.'),
        };
    }

    private function assertCreate(CreateQuoteCommand $command): void
    {
        if (! Str::isUuid($command->tenantId)
            || ! Str::isUuid($command->actorId)
            || ! Str::isUuid($command->locationId)
            || trim($command->idempotencyKey) === ''
            || mb_strlen($command->idempotencyKey) > 160
            || trim($command->source) === ''
            || $command->items === []) {
            throw new \InvalidArgumentException('Quote creation requires valid authority, location, items, and idempotency data.');
        }
    }

    private function assertRevise(ReviseQuoteCommand $command): void
    {
        if (! Str::isUuid($command->tenantId)
            || ! Str::isUuid($command->actorId)
            || ! Str::isUuid($command->quoteId)
            || trim($command->idempotencyKey) === ''
            || mb_strlen($command->idempotencyKey) > 160
            || $command->items === []) {
            throw new \InvalidArgumentException('Quote revision requires valid authority, quote, items, and idempotency data.');
        }
    }

    private function assertTransition(TransitionQuoteCommand $command): void
    {
        if (! Str::isUuid($command->tenantId)
            || ! Str::isUuid($command->actorId)
            || ! Str::isUuid($command->quoteId)
            || trim($command->idempotencyKey) === ''
            || mb_strlen($command->idempotencyKey) > 160) {
            throw new \InvalidArgumentException('Quote transition requires valid authority, quote, and idempotency data.');
        }
    }

    /** @param array<string, mixed> $payload */
    private function hash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /** @param array<string, scalar|null> $properties */
    private function audit(
        User $actor,
        Quote $quote,
        string $event,
        array $properties = [],
    ): void {
        activity('business-operations')
            ->performedOn($quote)
            ->causedBy($actor)
            ->withProperties([
                'tenant_id' => (string) $quote->tenant_id,
                'status' => (string) $quote->status,
                'currency' => (string) $quote->currency,
                ...$properties,
            ])
            ->event($event)
            ->log($event);
        $this->outbox->append(new BusinessOperationChanged(
            aggregateId: 'quote:'.$quote->getKey(),
            aggregateType: 'quote',
            tenantId: (string) $quote->tenant_id,
            actorId: (string) $actor->getKey(),
            eventName: $event,
            attributes: [
                'status' => (string) $quote->status,
                'currency' => (string) $quote->currency,
                ...$properties,
            ],
            occurredAt: CarbonImmutable::now(),
        ));
    }
}
