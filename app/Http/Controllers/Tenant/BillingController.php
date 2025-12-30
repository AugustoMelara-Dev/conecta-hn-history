<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BillingController - Read-Only Subscription Dashboard
 *
 * DATA CONTRACT: Dates returned in ISO 8601 format (Y-m-d\TH:i:s\Z).
 * Frontend handles timezone conversion via Intl.DateTimeFormat.
 */
final class BillingController extends Controller
{
    /**
     * Bank options for payment dropdown.
     */
    private const BANK_OPTIONS = [
        'bac' => 'BAC Credomatic',
        'ficohsa' => 'Banco Ficohsa',
        'banpais' => 'Banpaís',
        'atlantida' => 'Banco Atlántida',
        'davivienda' => 'Banco Davivienda',
        'occidente' => 'Banco de Occidente',
        'other' => 'Otro',
    ];

    // ═══════════════════════════════════════════════════════════════════════
    // INDEX - Billing Dashboard
    // ═══════════════════════════════════════════════════════════════════════
    public function index(Request $request): Response
    {
        $tenant = $this->getTenant($request);

        // Get payment history with ISO 8601 dates
        $payments = $this->getPaymentHistory($tenant);

        // Get subscription status (mock if Cashier not configured)
        $subscription = $this->getSubscriptionStatus($tenant);

        return Inertia::render('Tenant/Billing/Index', [
            'tenant' => $tenant->only('id', 'name', 'slug', 'is_pro'),
            'subscription' => $subscription,
            'payments' => $payments,
            'bankOptions' => self::BANK_OPTIONS,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // REPORT PAYMENT - Manual Payment Reporting
    // ═══════════════════════════════════════════════════════════════════════
    public function reportPayment(Request $request)
    {
        $tenant = $this->getTenant($request);

        $validated = $request->validate([
            'bank' => ['required', 'string', 'in:' . implode(',', array_keys(self::BANK_OPTIONS))],
            'reference_number' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:1'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ]);

        // Store receipt if provided
        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')
                ->store("tenants/{$tenant->id}/receipts", 'public');
        }

        Payment::create([
            'tenant_id' => $tenant->id,
            'bank' => $validated['bank'],
            'reference_number' => $validated['reference_number'],
            'amount' => $validated['amount'],
            'receipt_path' => $receiptPath,
            'status' => 'pending', // Admin will verify and approve
        ]);

        return back()->with('success', 'Pago reportado exitosamente. En revisión.');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // UPGRADE - Plan Upgrade Processing
    // FIX #1: Added to support tenant.billing.upgrade route
    // Principle: Complete Route Coverage - all frontend actions need endpoints
    // ═══════════════════════════════════════════════════════════════════════
    public function upgrade(Request $request)
    {
        $tenant = $this->getTenant($request);

        $validated = $request->validate([
            'plan' => ['required', 'string', 'in:pro,premium'],
            'payment_method' => ['required', 'string'],
        ]);

        // TODO: Implement actual payment gateway integration
        // For now, create a pending payment record for admin approval

        $planPrices = [
            'pro' => 499.00,
            'premium' => 999.00,
        ];

        $amount = $planPrices[$validated['plan']] ?? 499.00;

        // Create pending payment
        Payment::create([
            'tenant_id' => $tenant->id,
            'bank' => $validated['payment_method'],
            'reference_number' => 'UPGRADE-' . strtoupper(uniqid()),
            'amount' => $amount,
            'status' => 'pending',
            'notes' => "Upgrade to {$validated['plan']} plan",
        ]);

        return redirect()
            ->route('tenant.billing.index')
            ->with('success', 'Solicitud de upgrade recibida. Te contactaremos pronto.');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PRIVATE HELPERS
    // ═══════════════════════════════════════════════════════════════════════

    private function getTenant(Request $request): Tenant
    {
        /** @var \App\Models\User|null $user */
        $user = $request->user();

        // Fail-fast: No authenticated user
        abort_unless($user !== null, 403, 'Authentication required.');

        // Resolve primary tenant via accessor (deterministic)
        $tenant = $user->tenant;

        // Fail-fast: User has no tenant association
        abort_unless($tenant !== null, 403, 'No tenant associated with this account.');

        return $tenant;
    }

    /**
     * Get payment history with ISO 8601 dates.
     *
     * DATE SERIALIZATION: Returns dates in 'Y-m-d\TH:i:s\Z' format.
     * Frontend's Intl.DateTimeFormat handles local timezone conversion.
     */
    private function getPaymentHistory(Tenant $tenant): array
    {
        // Check if Payment model exists, otherwise return mock data
        if (!class_exists(Payment::class)) {
            return $this->getMockPayments();
        }

        return Payment::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'bank' => $payment->bank,
                'reference_number' => $payment->reference_number,
                'amount' => (float) $payment->amount,
                'status' => $payment->status,
                'created_at' => $payment->created_at->toIso8601String(),
                'download_url' => $payment->receipt_path
                    ? route('tenant.billing.download-receipt', $payment->id)
                    : null,
            ])
            ->toArray();
    }

    /**
     * Get subscription status.
     * Returns mock structure if Cashier not configured.
     */
    private function getSubscriptionStatus(Tenant $tenant): ?array
    {
        // If tenant has subscription relationship (Cashier)
        if (method_exists($tenant, 'subscription') && $tenant->subscription) {
            return [
                'status' => $tenant->subscription->stripe_status ?? 'active',
                'renewal_date' => $tenant->subscription->ends_at?->toIso8601String(),
                'plan' => $tenant->is_pro ? 'PRO' : 'FREE',
            ];
        }

        // Mock structure for non-Cashier setups
        return $tenant->is_pro ? [
            'status' => 'active',
            'renewal_date' => now()->addMonth()->toIso8601String(),
            'plan' => 'PRO',
        ] : null;
    }

    /**
     * Mock payments for development/demo.
     */
    private function getMockPayments(): array
    {
        return [
            [
                'id' => 1,
                'bank' => 'bac',
                'reference_number' => 'REF-001234',
                'amount' => 1500.00,
                'status' => 'approved',
                'created_at' => now()->subDays(30)->toIso8601String(),
                'download_url' => null,
            ],
            [
                'id' => 2,
                'bank' => 'ficohsa',
                'reference_number' => 'REF-005678',
                'amount' => 1500.00,
                'status' => 'pending',
                'created_at' => now()->subDays(2)->toIso8601String(),
                'download_url' => null,
            ],
        ];
    }
}
