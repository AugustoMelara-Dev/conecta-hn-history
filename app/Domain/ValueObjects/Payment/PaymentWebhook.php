<?php

declare(strict_types=1);

namespace App\Domain\ValueObjects\Payment;

use Illuminate\Support\Carbon;
use InvalidArgumentException;

final readonly class PaymentWebhook
{
    private function __construct(
        public string $eventType,
        public string $tenantId,
        public string $providerId,
        public ?Carbon $occurredAt,
        /** @var array<string, mixed> */
        public array $payload,
    ) {}

    public static function fromArray(string $type, array $data): self
    {
        // 1. Solve "Anemic Domain": Encapsulate validation logic here.
        $tenantId = $data['meta']['tenant_id']
                 ?? $data['data']['object']['metadata']['tenant_id']
                 ?? $data['data']['object']['client_reference_id']
                 ?? null;

        if (empty($tenantId) || ! is_string($tenantId)) {
            throw new InvalidArgumentException('Payment Webhook rejected: Missing Tenant ID.');
        }

        $providerId = $data['id']
            ?? $data['event_id']
            ?? $data['data']['id']
            ?? $data['data']['object']['event_id']
            ?? $data['data']['object']['id']
            ?? null;

        if (! is_string($providerId) || trim($providerId) === '') {
            throw new InvalidArgumentException('Payment Webhook rejected: Missing provider event ID.');
        }

        return new self(
            eventType: $type,
            tenantId: $tenantId,
            providerId: trim($providerId),
            occurredAt: self::parseOccurredAt($data),
            payload: $data,
        );
    }

    public function isSuccessful(): bool
    {
        return in_array($this->eventType, [
            'payment.success',
            'payment_intent.succeeded',
            'checkout.session.completed',
            'invoice.paid',
        ], true);
    }

    public function paymentReference(): string
    {
        return (string) (
            $this->payload['data']['transaction_id']
            ?? $this->payload['data']['object']['payment_intent']
            ?? $this->payload['data']['object']['id']
            ?? $this->providerId
        );
    }

    public function amountInCents(): int
    {
        return (int) (
            $this->payload['amount']
            ?? $this->payload['data']['amount']
            ?? $this->payload['data']['object']['amount_paid']
            ?? $this->payload['data']['object']['amount_received']
            ?? $this->payload['data']['object']['amount_total']
            ?? $this->payload['data']['object']['amount']
            ?? 0
        );
    }

    public function currency(): string
    {
        $currency = (
            $this->payload['currency']
            ?? $this->payload['data']['currency']
            ?? $this->payload['data']['object']['currency']
            ?? null
        );

        if (! is_string($currency) || trim($currency) === '') {
            throw new InvalidArgumentException('Payment Webhook rejected: Missing currency.');
        }

        return strtoupper(trim($currency));
    }

    public function providerName(): string
    {
        return (string) (
            $this->payload['provider']
            ?? $this->payload['meta']['provider']
            ?? $this->payload['data']['object']['provider']
            ?? 'payment_gateway'
        );
    }

    public function isStripe(): bool
    {
        return $this->providerName() === 'stripe'
            || str_starts_with($this->eventType, 'payment_intent.')
            || str_starts_with($this->eventType, 'checkout.session.')
            || str_starts_with($this->eventType, 'invoice.');
    }

    /**
     * @return array<string, mixed>
     */
    public function metadata(): array
    {
        $metadata = $this->payload['data']['object']['metadata']
            ?? $this->payload['meta']
            ?? [];

        return is_array($metadata) ? $metadata : [];
    }

    public function subscriptionIntentId(): ?string
    {
        $metadata = $this->metadata();
        $value = $metadata['subscription_intent_id']
            ?? $metadata['intent_id']
            ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function clientIdempotencyKey(): ?string
    {
        $metadata = $this->metadata();
        $value = $metadata['client_idempotency_key']
            ?? $metadata['idempotency_key']
            ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function planIdentifier(): ?string
    {
        $metadata = $this->metadata();
        $value = $metadata['plan_identifier']
            ?? $metadata['plan']
            ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function orderId(): ?int
    {
        $value = $this->metadata()['order_id'] ?? null;

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    public function orderIdempotencyKey(): ?string
    {
        $value = $this->metadata()['order_idempotency_key'] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function providerCustomerId(): ?string
    {
        $value = $this->payload['data']['object']['customer']
            ?? $this->payload['data']['object']['subscription_details']['metadata']['stripe_customer_id']
            ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    public function providerSubscriptionId(): ?string
    {
        $value = $this->payload['data']['object']['subscription']
            ?? $this->payload['data']['object']['parent']['subscription_details']['subscription']
            ?? $this->payload['data']['object']['subscription_details']['metadata']['stripe_subscription_id']
            ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function parseOccurredAt(array $data): ?Carbon
    {
        $value = $data['created']
            ?? $data['created_at']
            ?? $data['timestamp']
            ?? $data['data']['created']
            ?? $data['data']['object']['created']
            ?? null;

        if ($value === null || $value === '') {
            return null;
        }

        try {
            if (is_numeric($value)) {
                return Carbon::createFromTimestamp((int) $value);
            }

            if (is_string($value)) {
                return Carbon::parse($value);
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
