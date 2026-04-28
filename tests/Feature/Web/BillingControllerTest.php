<?php

declare(strict_types=1);

namespace Tests\Feature\Web;

use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BillingControllerTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function guest_cannot_view_payment_pending_metadata(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();
        $payment = $this->pendingPaymentFor($tenant);

        $this->get(route('payment.pending', ['payment' => $payment->id], false))
            ->assertRedirect(route('login', [], false));
    }

    #[Test]
    public function tenant_owner_can_view_payment_pending_metadata(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();
        $owner->forceFill(['tenant_id' => $tenant->id])->save();
        $payment = $this->pendingPaymentFor($tenant);

        $this->actingAs($owner)
            ->get(route('payment.pending', ['payment' => $payment->id], false))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Billing/PaymentPending')
                ->where('payment.id', $payment->id)
                ->where('payment.reference_number', 'SECURE-REF-001')
                ->where('payment.tenant_name', $tenant->name)
            );
    }

    #[Test]
    public function another_user_cannot_view_payment_pending_metadata(): void
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create();
        $payment = $this->pendingPaymentFor($tenant);
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->get(route('payment.pending', ['payment' => $payment->id], false))
            ->assertForbidden();
    }

    private function pendingPaymentFor(Tenant $tenant): SubscriptionPayment
    {
        return SubscriptionPayment::forceCreate([
            'tenant_id' => $tenant->id,
            'amount' => 25000,
            'bank' => 'bank_transfer',
            'reference_number' => 'SECURE-REF-001',
            'proof_path' => 'payments/proofs/secure-ref-001.jpg',
            'status' => SubscriptionPayment::STATUS_PENDING,
        ]);
    }
}
