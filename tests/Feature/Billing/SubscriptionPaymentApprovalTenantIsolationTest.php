<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Domain\Billing\Events\SubscriptionPaymentApproved;
use App\Domain\Shared\Services\TenantContext;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Listeners\UpdateTenantSubscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use stdClass;
use Tests\TestCase;

final class SubscriptionPaymentApprovalTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function model_approval_dispatches_the_subscription_event_with_uuid_identifiers(): void
    {
        Event::fake([SubscriptionPaymentApproved::class]);
        [$tenant, $payment, $reviewer] = $this->fixture();
        $payment->forceFill(['status' => SubscriptionPayment::STATUS_PENDING])->save();

        $payment->approve((string) $reviewer->getKey(), 45);

        Event::assertDispatched(
            SubscriptionPaymentApproved::class,
            static fn (SubscriptionPaymentApproved $event): bool => $event->tenantId === (string) $tenant->getKey()
                && $event->paymentId === (string) $payment->getKey()
                && $event->reviewerId === (string) $reviewer->getKey()
                && $event->daysToAdd === 45,
        );
        self::assertSame(SubscriptionPayment::STATUS_APPROVED, $payment->fresh()->status);
    }

    #[Test]
    public function approved_payment_extends_only_its_exact_uuid_tenant(): void
    {
        [$tenant, $payment, $reviewer] = $this->fixture();
        $event = SubscriptionPaymentApproved::fromPayment(
            $tenant->getKey(),
            $payment->getKey(),
            $reviewer->getKey(),
            30,
        );

        $this->runListener($event);
        app(TenantContext::class)->setAsSystemRoot();

        self::assertSame(TenantPlan::PRO, $tenant->fresh()->plan_type);
        self::assertSame('active', $tenant->fresh()->subscription_status);
        self::assertSame(15.0, (float) $tenant->fresh()->ai_balance);
        self::assertSame(100, (int) $tenant->fresh()->product_limit);
        self::assertSame(1, \Illuminate\Support\Facades\DB::table('ai_credit_grants')
            ->where('tenant_id', $tenant->getKey())
            ->where('plan', 'pro')
            ->count());
        self::assertNotNull($payment->fresh()->subscription_extended_at);
    }

    #[Test]
    public function cross_tenant_event_cannot_consume_or_extend_a_foreign_payment(): void
    {
        [$tenantA, $payment, $reviewer] = $this->fixture();
        $tenantB = Tenant::factory()->create();
        $event = SubscriptionPaymentApproved::fromPayment(
            $tenantB->getKey(),
            $payment->getKey(),
            $reviewer->getKey(),
            30,
        );

        try {
            $this->runListener($event);
            self::fail('The cross-tenant payment event should be rejected.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('not found', $exception->getMessage());
        }

        app(TenantContext::class)->setAsSystemRoot();

        self::assertNotSame(TenantPlan::PRO, $tenantA->fresh()->plan_type);
        self::assertNotSame(TenantPlan::PRO, $tenantB->fresh()->plan_type);
        self::assertNull($payment->fresh()->subscription_extended_at);
    }

    private function runListener(SubscriptionPaymentApproved $event): void
    {
        $listener = new UpdateTenantSubscription;
        $middleware = $listener->middleware($event);

        self::assertCount(1, $middleware);
        $middleware[0]->handle(new stdClass, static function () use ($listener, $event): void {
            $listener->handle($event);
        });

        self::assertFalse(app(TenantContext::class)->isInitialized());
    }

    /**
     * @return array{Tenant, SubscriptionPayment, User}
     */
    private function fixture(): array
    {
        $context = app(TenantContext::class);
        $context->setAsSystemRoot();

        $tenant = Tenant::factory()->create([
            'is_pro' => false,
            'plan_type' => 'semilla',
            'subscription_ends_at' => null,
        ]);
        $reviewer = User::factory()->create();
        $payment = SubscriptionPayment::forceCreate([
            'id' => (string) Str::uuid(),
            'tenant_id' => (string) $tenant->getKey(),
            'idempotency_key' => (string) Str::uuid(),
            'amount' => 499.00,
            'bank' => 'BAC',
            'reference_number' => 'approval-'.Str::random(12),
            'proof_path' => 'payments/proofs/test.png',
            'status' => SubscriptionPayment::STATUS_APPROVED,
            'reviewed_by' => (string) $reviewer->getKey(),
            'reviewed_at' => now(),
        ]);

        return [$tenant, $payment, $reviewer];
    }
}
