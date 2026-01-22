<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Application\Actions\Billing\ReportPaymentAction;
use App\Application\Actions\Billing\RequestUpgradeAction;
use App\Application\Billing\Results\ReportPaymentResult;
use App\Application\Billing\Results\RequestUpgradeResult;
use App\Domain\Shared\Contracts\TenantContext;
use App\Domain\Tenant\Contracts\BillingRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ReportPaymentRequest;
use App\Http\Requests\Tenant\UpgradePlanRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BillingController - Final Sanitized Version
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  ARCHITECTURAL COMPLIANCE: PURE HEXAGONAL                                ║
 * ║  - Context: Injected via TenantContext (No User Model coupling)          ║
 * ║  - Read: Via BillingRepository                                           ║
 * ║  - Write: Via FormRequest -> Action                                      ║
 * ║  - Config: Bank options externalized to config/billing.php               ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class BillingController extends Controller
{
    public function __construct(
        private readonly BillingRepository $billingRepository,
        private readonly TenantContext $tenantContext
    ) {}

    public function index(): Response
    {
        $tenantId = $this->tenantContext->getTenantId();

        $invoices = $this->billingRepository->getInvoices($tenantId);
        $subscription = $this->billingRepository->getSubscriptionStatus($tenantId);

        // Simple Transform
        $paymentsFormatted = $invoices->through(fn ($payment) => [
            'id' => $payment->id,
            'bank' => $payment->bank,
            'reference_number' => $payment->reference_number,
            'amount' => (float) $payment->amount,
            'status' => $payment->status,
            'created_at' => $payment->created_at->toIso8601String(),
            'download_url' => $payment->receipt_path
                ? route('tenant.billing.download-receipt', $payment->id)
                : null,
        ]);

        return Inertia::render('Tenant/Billing/Index', [
            'subscription' => $subscription,
            'payments' => $paymentsFormatted,
            // EXTERNALIZED: Bank options now come from config file
            'bankOptions' => config('billing.bank_options'),
        ]);
    }

    public function reportPayment(
        ReportPaymentRequest $request,
        ReportPaymentAction $action
    ): RedirectResponse {
        $tenantId = $this->tenantContext->getTenantId();

        // PRE-PROCESS: File upload handled in Controller (Infrastructure Layer)
        $receiptPath = $request->file('receipt')?->store('tenants/receipts', 'public');

        $dto = ReportPaymentResult::fromRequest($request, $tenantId, $receiptPath);
        $action->execute($dto);

        return back()->with('success', 'Pago reportado.');
    }

    public function upgrade(
        UpgradePlanRequest $request,
        RequestUpgradeAction $action
    ): RedirectResponse {
        $tenantId = $this->tenantContext->getTenantId();

        $dto = RequestUpgradeResult::fromRequest($request, $tenantId);
        $action->execute($dto);

        return redirect()
            ->route('tenant.billing.index')
            ->with('success', 'Solicitud recibida.');
    }
}
