<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Domain\Billing\Entities\PaymentEntity;
use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Repositories\PaymentRepositoryInterface;
use App\Domain\Shared\Services\TenantContext;
use App\Domain\Shared\ValueObjects\Money;
use App\Models\Tenant;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class PaymentPendingLaunchContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedGeneralSettings();
    }

    public function test_repository_and_owner_page_preserve_major_and_minor_money_units(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();
        $paymentId = (string) Str::uuid();

        $this->app->make(TenantContext::class)->setTenant((string) $tenant->id);
        $repository = $this->app->make(PaymentRepositoryInterface::class);
        $repository->save(new PaymentEntity(
            id: $paymentId,
            tenantId: (string) $tenant->id,
            amount: Money::HNL(63900),
            bank: 'pending_activation',
            referenceNumber: 'PRO-L639',
            status: PaymentStatus::PENDING,
            proofUrl: 'pending',
            createdAt: new DateTimeImmutable,
            idempotencyKey: 'launch-price-639',
        ));

        self::assertSame(639.0, (float) DB::table('subscription_payments')->where('id', $paymentId)->value('amount'));
        self::assertSame(63900, $repository->findById($paymentId)?->amount->amount);
        self::assertSame('L639.00', $repository->findById($paymentId)?->amount->format());

        $this->actingAs($owner)
            ->get(route('payment.pending', $paymentId))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Billing/PaymentPending')
                ->where('payment.amount', 639)
                ->where('payment.reference_number', 'PRO-L639'));
    }

    public function test_payment_pending_requires_authentication_and_exact_tenant_ownership(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();
        $paymentId = (string) Str::uuid();

        DB::table('subscription_payments')->insert([
            'id' => $paymentId,
            'tenant_id' => $tenant->id,
            'amount' => 639.00,
            'bank' => 'pending_activation',
            'reference_number' => 'PRIVATE-REF',
            'proof_path' => 'pending',
            'status' => 'pending',
            'idempotency_key' => 'private-ref',
            'version' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->get(route('payment.pending', $paymentId))
            ->assertRedirect(route('login'));

        $this->actingAs($intruder)
            ->get(route('payment.pending', $paymentId))
            ->assertForbidden();
    }

    private function seedGeneralSettings(): void
    {
        foreach ([
            'pro_plan_price' => 639.0,
            'grace_period_days' => 7,
            'free_plan_product_limit' => 10,
            'pro_plan_product_limit' => 100,
            'bank_transfer_instructions' => 'Transferencia bancaria',
            'support_whatsapp_number' => '+50499990000',
            'support_email' => 'soporte@example.com',
            'maintenance_mode' => false,
            'allow_registrations' => true,
        ] as $name => $payload) {
            DB::table('settings')->updateOrInsert(
                ['group' => 'general', 'name' => $name],
                [
                    'locked' => false,
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }
}
