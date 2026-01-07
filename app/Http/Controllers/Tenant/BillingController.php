<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Billing\Contracts\BillingReader;
use App\Http\Controllers\Controller;
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

    public function __construct(
        private readonly BillingReader $reader
    ) {}

    // ═══════════════════════════════════════════════════════════════════════
    // INDEX - Billing Dashboard
    // ═══════════════════════════════════════════════════════════════════════
    public function index(Request $request): Response
    {
        $tenant = $this->getTenant($request);

        // Get payment history via Reader (Infrastructure Agnostic)
        $payments = $this->reader->getPaymentHistory($tenant->id)
            ->map(fn ($payment) => [
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
    // REFACTORED: Delegates to ReportPaymentAction
    // ═══════════════════════════════════════════════════════════════════════
    public function reportPayment(
        Request $request,
        \App\Application\Actions\Billing\ReportPaymentAction $action
    ): \Illuminate\Http\RedirectResponse {
        $tenant = $this->getTenant($request);

        $request->validate([
            'bank' => ['required', 'string', 'in:' . implode(',', array_keys(self::BANK_OPTIONS))],
            'reference_number' => ['required', 'string', 'max:50'],
            'amount' => ['required', 'numeric', 'min:1'],
            'receipt' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:2048'],
        ]);

        // Create DTO and delegate to Action
        $dto = \App\Application\DTOs\Billing\ReportPaymentData::fromRequest($request, $tenant->id);
        $action->execute($dto);

        return back()->with('success', 'Pago reportado exitosamente. En revisión.');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // UPGRADE - Plan Upgrade Processing
    // REFACTORED: Delegates to RequestUpgradeAction
    // ═══════════════════════════════════════════════════════════════════════
    public function upgrade(
        Request $request,
        \App\Application\Actions\Billing\RequestUpgradeAction $action
    ): \Illuminate\Http\RedirectResponse {
        $tenant = $this->getTenant($request);

        $request->validate([
            'plan' => ['required', 'string', 'in:pro,premium'],
            'payment_method' => ['required', 'string'],
        ]);

        // Create DTO and delegate to Action
        $dto = \App\Application\DTOs\Billing\RequestUpgradeData::fromRequest($request, $tenant->id);
        $action->execute($dto);

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
}
