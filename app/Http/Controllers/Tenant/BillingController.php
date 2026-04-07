<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Shared\Contracts\TenantContext;
use App\Domain\Tenant\Contracts\BillingRepository;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class BillingController extends Controller
{
    public function __construct(
        private readonly BillingRepository $billingRepository,
        private readonly TenantContext $tenantContext,
    ) {}

    public function index(): Response
    {
        $tenantId = $this->tenantContext->getTenantId();

        $invoices = $this->billingRepository->getInvoices($tenantId);
        $subscription = $this->billingRepository->getSubscriptionStatus($tenantId);
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
            'bankOptions' => config('billing.bank_options'),
        ]);
    }

    public function reportPayment(
        Request $request
    ): RedirectResponse {
        return $this->redirectToOfficialBilling('La gestion de pagos y upgrades ya se administra desde el panel oficial en /app.');
    }

    public function upgrade(
        Request $request
    ): RedirectResponse {
        return $this->redirectToOfficialBilling('La gestion de pagos y upgrades ya se administra desde el panel oficial en /app.');
    }

    private function redirectToOfficialBilling(string $message): RedirectResponse
    {
        return redirect()
            ->to(route('filament.app.pages.manage-billing', ['tenant' => $this->tenantSlug()], false))
            ->with('error', $message);
    }

    private function tenantSlug(): string
    {
        return (string) ($this->tenantContext->getTenantData(['slug'])['slug'] ?? '');
    }
}
