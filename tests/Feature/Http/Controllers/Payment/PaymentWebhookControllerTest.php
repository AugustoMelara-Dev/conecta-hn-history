<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Payment;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Tests\TestCase;

final class PaymentWebhookControllerTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function payment_webhook_rejects_invalid_signatures(): void
    {
        config()->set('services.payment.webhook_secret', 'test-webhook-secret');

        /** @var array<string, mixed> $payload */
        $payload = [
            'id' => 'evt_invalid',
            'event' => 'payment.success',
            'meta' => ['tenant_id' => (string) Str::uuid()],
            'data' => [
                'transaction_id' => 'tx_invalid',
                'object' => [
                    'id' => 'tx_invalid',
                    'metadata' => ['tenant_id' => (string) Str::uuid()],
                ],
            ],
        ];

        $response = $this->postJson('/api/webhooks/payment', $payload, [
            'X-Signature' => 'bad-signature',
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function payment_webhook_materializes_a_single_payment_record_and_extends_the_tenant_once(): void
    {
        config()->set('services.payment.webhook_secret', 'test-webhook-secret');

        $fixture = $this->createWebhookTenantFixture();
        $eventId = (string) Str::uuid();
        $transactionId = 'tx_'.Str::lower(Str::random(12));

        /** @var array<string, mixed> $payload */
        $payload = [
            'id' => $eventId,
            'event' => 'payment.success',
            'meta' => [
                'tenant_id' => $fixture['tenant_id'],
                'provider' => 'manual_gateway',
            ],
            'data' => [
                'transaction_id' => $transactionId,
                'object' => [
                    'id' => $transactionId,
                    'amount' => 9900,
                    'currency' => 'HNL',
                    'metadata' => [
                        'tenant_id' => $fixture['tenant_id'],
                    ],
                ],
            ],
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'test-webhook-secret');

        $responseA = $this->call(
            'POST',
            '/api/webhooks/payment',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Signature' => $signature,
                'HTTP_X-Timestamp' => (string) $timestamp,
            ],
            $body,
        );

        $responseB = $this->call(
            'POST',
            '/api/webhooks/payment',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Signature' => $signature,
                'HTTP_X-Timestamp' => (string) $timestamp,
            ],
            $body,
        );

        $responseA->assertOk();
        $responseB->assertAccepted()
            ->assertJsonPath('reason', 'duplicate_event');

        $this->assertDatabaseCount('subscription_payments', 1);
        $this->assertDatabaseCount('webhook_logs', 1);
        $this->assertDatabaseHas('subscription_payments', [
            'tenant_id' => $fixture['tenant_id'],
            'reference_number' => $transactionId,
            'idempotency_key' => $eventId,
            'status' => 'approved',
        ]);
        self::assertNotNull(
            DB::table('subscription_payments')
                ->where('idempotency_key', $eventId)
                ->value('receipt_issued_at')
        );

        /** @var stdClass|null $tenant */
        $tenant = DB::table('tenants')->where('id', $fixture['tenant_id'])->first();

        self::assertNotNull($tenant);
        self::assertSame('active', (string) $tenant->subscription_status);
        self::assertNotNull($tenant->subscription_ends_at);
    }

    #[Test]
    public function payment_webhook_rejects_legacy_signature_without_timestamp_by_default(): void
    {
        config()->set('services.payment.webhook_secret', 'test-webhook-secret');

        $payload = [
            'id' => 'evt_without_timestamp',
            'event' => 'payment.success',
            'meta' => ['tenant_id' => (string) Str::uuid()],
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', $body, 'test-webhook-secret');

        $response = $this->call(
            'POST',
            '/api/webhooks/payment',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Signature' => $signature,
            ],
            $body,
        );

        $response->assertForbidden();
    }

    #[Test]
    public function payment_webhook_rejects_stale_timestamp_when_timestamp_is_required(): void
    {
        config()->set('services.payment.webhook_secret', 'test-webhook-secret');
        config()->set('services.payment.webhook_require_timestamp', true);

        $payload = [
            'id' => 'evt_stale',
            'event' => 'payment.success',
            'meta' => ['tenant_id' => (string) Str::uuid()],
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = now()->subMinutes(10)->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'test-webhook-secret');

        $response = $this->call(
            'POST',
            '/api/webhooks/payment',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X-Signature' => $signature,
                'HTTP_X-Timestamp' => (string) $timestamp,
            ],
            $body,
        );

        $response->assertForbidden();
    }

    #[Test]
    public function stripe_payment_intent_webhook_requires_stripe_signature_and_materializes_payment(): void
    {
        config()->set('services.payment.provider', 'stripe');
        config()->set('services.stripe.webhook_secret', 'whsec_test_stripe_webhook');

        $fixture = $this->createWebhookTenantFixture();
        $eventId = 'evt_'.Str::lower(Str::random(16));
        $paymentIntentId = 'pi_'.Str::lower(Str::random(16));
        $intentId = (string) Str::uuid();

        DB::table('subscription_intents')->insert([
            'id' => $intentId,
            'tenant_id' => $fixture['tenant_id'],
            'client_idempotency_key' => 'stripe-test-'.$intentId,
            'plan_identifier' => 'pro',
            'gateway_provider' => 'stripe',
            'status' => 'PENDING',
            'gateway_transaction_id' => null,
            'error_reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'id' => $eventId,
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'created' => now()->timestamp,
            'data' => [
                'object' => [
                    'id' => $paymentIntentId,
                    'object' => 'payment_intent',
                    'amount_received' => 2900,
                    'currency' => 'hnl',
                    'metadata' => [
                        'tenant_id' => $fixture['tenant_id'],
                        'subscription_intent_id' => $intentId,
                        'client_idempotency_key' => 'stripe-test-'.$intentId,
                        'plan_identifier' => 'pro',
                    ],
                ],
            ],
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test_stripe_webhook');

        $this->call(
            'POST',
            '/api/webhooks/payment',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            ],
            $body,
        )->assertOk();

        $this->assertDatabaseHas('subscription_payments', [
            'tenant_id' => $fixture['tenant_id'],
            'reference_number' => $paymentIntentId,
            'idempotency_key' => $eventId,
            'bank' => 'stripe',
            'status' => 'approved',
        ]);
        self::assertNotNull(
            DB::table('subscription_payments')
                ->where('idempotency_key', $eventId)
                ->value('receipt_issued_at')
        );

        $this->assertDatabaseHas('subscription_intents', [
            'id' => $intentId,
            'tenant_id' => $fixture['tenant_id'],
            'status' => 'COMPLETED',
            'gateway_transaction_id' => $paymentIntentId,
        ]);
    }

    #[Test]
    public function stripe_webhook_without_matching_subscription_intent_does_not_materialize_payment(): void
    {
        config()->set('services.payment.provider', 'stripe');
        config()->set('services.stripe.webhook_secret', 'whsec_test_stripe_webhook');

        $fixture = $this->createWebhookTenantFixture();
        $eventId = 'evt_'.Str::lower(Str::random(16));
        $paymentIntentId = 'pi_'.Str::lower(Str::random(16));

        $payload = [
            'id' => $eventId,
            'object' => 'event',
            'type' => 'payment_intent.succeeded',
            'created' => now()->timestamp,
            'data' => [
                'object' => [
                    'id' => $paymentIntentId,
                    'object' => 'payment_intent',
                    'amount_received' => 2900,
                    'currency' => 'hnl',
                    'metadata' => [
                        'tenant_id' => $fixture['tenant_id'],
                        'plan_identifier' => 'pro',
                    ],
                ],
            ],
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test_stripe_webhook');

        $this->call(
            'POST',
            '/api/webhooks/payment',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            ],
            $body,
        )->assertOk();

        $this->assertDatabaseMissing('subscription_payments', [
            'tenant_id' => $fixture['tenant_id'],
            'reference_number' => $paymentIntentId,
        ]);
    }

    #[Test]
    public function stripe_checkout_session_completed_materializes_subscription_payment_from_local_intent(): void
    {
        config()->set('services.payment.provider', 'stripe');
        config()->set('services.stripe.webhook_secret', 'whsec_test_stripe_webhook');

        $fixture = $this->createWebhookTenantFixture();
        $eventId = 'evt_'.Str::lower(Str::random(16));
        $sessionId = 'cs_test_'.Str::lower(Str::random(16));
        $intentId = (string) Str::uuid();

        DB::table('subscription_intents')->insert([
            'id' => $intentId,
            'tenant_id' => $fixture['tenant_id'],
            'client_idempotency_key' => 'checkout-test-'.$intentId,
            'plan_identifier' => 'pro',
            'gateway_provider' => 'stripe',
            'status' => 'PROCESSING',
            'gateway_transaction_id' => $sessionId,
            'error_reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'id' => $eventId,
            'object' => 'event',
            'type' => 'checkout.session.completed',
            'created' => now()->timestamp,
            'data' => [
                'object' => [
                    'id' => $sessionId,
                    'object' => 'checkout.session',
                    'amount_total' => 2900,
                    'currency' => 'hnl',
                    'client_reference_id' => $fixture['tenant_id'],
                    'metadata' => [
                        'tenant_id' => $fixture['tenant_id'],
                        'subscription_intent_id' => $intentId,
                        'client_idempotency_key' => 'checkout-test-'.$intentId,
                        'plan_identifier' => 'pro',
                    ],
                ],
            ],
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test_stripe_webhook');

        $this->call('POST', '/api/webhooks/payment', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], $body)->assertOk();

        $this->assertDatabaseHas('subscription_payments', [
            'tenant_id' => $fixture['tenant_id'],
            'reference_number' => $sessionId,
            'idempotency_key' => $eventId,
            'bank' => 'stripe',
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('subscription_intents', [
            'id' => $intentId,
            'tenant_id' => $fixture['tenant_id'],
            'status' => 'COMPLETED',
            'gateway_transaction_id' => $sessionId,
        ]);
    }

    #[Test]
    public function stripe_invoice_payment_failed_marks_tenant_past_due_without_materializing_payment(): void
    {
        config()->set('services.payment.provider', 'stripe');
        config()->set('services.stripe.webhook_secret', 'whsec_test_stripe_webhook');

        $fixture = $this->createWebhookTenantFixture();
        $eventId = 'evt_'.Str::lower(Str::random(16));
        $invoiceId = 'in_'.Str::lower(Str::random(16));
        $intentId = (string) Str::uuid();

        DB::table('subscription_intents')->insert([
            'id' => $intentId,
            'tenant_id' => $fixture['tenant_id'],
            'client_idempotency_key' => 'invoice-test-'.$intentId,
            'plan_identifier' => 'pro',
            'gateway_provider' => 'stripe',
            'status' => 'PROCESSING',
            'gateway_transaction_id' => 'cs_test_'.$intentId,
            'error_reason' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $payload = [
            'id' => $eventId,
            'object' => 'event',
            'type' => 'invoice.payment_failed',
            'created' => now()->timestamp,
            'data' => [
                'object' => [
                    'id' => $invoiceId,
                    'object' => 'invoice',
                    'amount_due' => 2900,
                    'currency' => 'hnl',
                    'subscription_details' => [
                        'metadata' => [
                            'tenant_id' => $fixture['tenant_id'],
                            'subscription_intent_id' => $intentId,
                            'client_idempotency_key' => 'invoice-test-'.$intentId,
                            'plan_identifier' => 'pro',
                        ],
                    ],
                ],
            ],
        ];

        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = now()->timestamp;
        $signature = hash_hmac('sha256', $timestamp.'.'.$body, 'whsec_test_stripe_webhook');

        $this->call('POST', '/api/webhooks/payment', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
        ], $body)->assertOk();

        $this->assertDatabaseMissing('subscription_payments', [
            'tenant_id' => $fixture['tenant_id'],
            'reference_number' => $invoiceId,
        ]);

        $this->assertDatabaseHas('subscription_intents', [
            'id' => $intentId,
            'tenant_id' => $fixture['tenant_id'],
            'status' => 'FAILED',
            'error_reason' => 'invoice.payment_failed',
        ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $fixture['tenant_id'],
            'subscription_status' => 'past_due',
        ]);
    }

    /**
     * @return array{user_id: string, tenant_id: string}
     */
    private function createWebhookTenantFixture(): array
    {
        $userId = (string) Str::uuid();
        $tenantId = (string) Str::uuid();
        $email = 'webhook+'.Str::lower(Str::random(10)).'@example.test';
        $slug = 'webhook-tenant-'.Str::lower(Str::random(10));
        $now = now();

        DB::table('users')->insert([
            'id' => $userId,
            'name' => 'Webhook Test User',
            'email' => $email,
            'password' => bcrypt(Str::random(32)),
            'role' => 'seller',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        /** @var array<string, mixed> $tenantPayload */
        $tenantPayload = [
            'id' => $tenantId,
            'user_id' => $userId,
            'name' => 'Webhook Test Tenant',
            'slug' => $slug,
            'plan' => 'SEED',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        if (Schema::hasColumn('tenants', 'subscription_status')) {
            $tenantPayload['subscription_status'] = 'inactive';
        }

        if (Schema::hasColumn('tenants', 'plan_type')) {
            $tenantPayload['plan_type'] = 'semilla';
        }

        if (Schema::hasColumn('tenants', 'subscription_ends_at')) {
            $tenantPayload['subscription_ends_at'] = null;
        }

        DB::table('tenants')->insert($tenantPayload);

        DB::table('users')->where('id', $userId)->update([
            'tenant_id' => $tenantId,
        ]);

        return [
            'user_id' => $userId,
            'tenant_id' => $tenantId,
        ];
    }
}
