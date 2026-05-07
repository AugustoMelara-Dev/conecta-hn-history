<?php

declare(strict_types=1);

namespace App\Application\Billing\Results;

final readonly class BillingCheckoutResult
{
    public const STATUS_MANUAL_PENDING = 'manual_pending';

    public const STATUS_REQUIRES_PAYMENT = 'requires_payment';

    public const STATUS_PAID = 'paid';

    public const STATUS_FAILED = 'failed';

    public function __construct(
        public string $status,
        public string $tenantId,
        public string $planIdentifier,
        public string $subscriptionIntentId,
        public string $provider,
        public ?string $paymentIntentId = null,
        public ?string $providerSessionId = null,
        public ?string $checkoutUrl = null,
        public ?string $clientSecret = null,
        public ?string $message = null,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'tenant_id' => $this->tenantId,
            'plan_identifier' => $this->planIdentifier,
            'subscription_intent_id' => $this->subscriptionIntentId,
            'provider' => $this->provider,
            'payment_intent_id' => $this->paymentIntentId,
            'provider_session_id' => $this->providerSessionId,
            'checkout_url' => $this->checkoutUrl,
            'client_secret' => $this->clientSecret,
            'message' => $this->message,
        ];
    }
}
