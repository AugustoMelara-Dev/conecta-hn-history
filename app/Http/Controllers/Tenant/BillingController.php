<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Application\Billing\Commands\ReportPaymentCommand;
use App\Application\Billing\Commands\RequestUpgradeCommand;
use App\Application\Billing\Handlers\ReportPaymentHandler;
use App\Application\Billing\Handlers\RequestUpgradeHandler;
use App\Domain\Shared\Contracts\TenantContext;
use App\Domain\Tenant\Contracts\BillingRepository;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\ReportPaymentRequest;
use App\Http\Requests\Tenant\UpgradePlanRequest;
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
        ReportPaymentRequest $request,
        ReportPaymentHandler $handler
    ): RedirectResponse {
        $validated = $request->validated();
        $tenantUuid = $this->tenantContext->getTenantUuid();
        $receiptPath = $request->file('receipt')?->store("temp/{$tenantUuid}/payments", 'public');

        $handler->handle(new ReportPaymentCommand(
            tenantId: $tenantUuid,
            paymentReference: (string) $validated['reference_number'],
            amountInCents: (int) round((float) $validated['amount'] * 100),
            currency: 'HNL',
            bank: (string) $validated['bank'],
            idempotencyKey: $this->resolveIdempotencyKey($request, [
                'tenant_id' => $tenantUuid,
                'reference_number' => $validated['reference_number'],
                'amount' => $validated['amount'],
            ]),
            proofOfPaymentPath: $receiptPath,
        ));

        return back()->with('success', 'Pago reportado.');
    }

    public function upgrade(
        UpgradePlanRequest $request,
        RequestUpgradeHandler $handler
    ): RedirectResponse {
        $validated = $request->validated();
        $tenantUuid = $this->tenantContext->getTenantUuid();
        $idempotencyKey = $this->resolveIdempotencyKey($request, [
            'tenant_id' => $tenantUuid,
            'plan' => $validated['plan'],
            'payment_method' => $validated['payment_method'],
        ]);

        $handler->handle(new RequestUpgradeCommand(
            tenantId: $tenantUuid,
            newPlanIdentifier: (string) $validated['plan'],
            currency: 'HNL',
            idempotencyKey: $idempotencyKey,
            clientIdempotencyKey: $idempotencyKey,
        ));

        return redirect()
            ->route('tenant.billing.index')
            ->with('success', 'Solicitud recibida.');
    }

    private function resolveIdempotencyKey(Request $request, array $payload): string
    {
        $headerKey = $request->header('Idempotency-Key');
        if (! is_string($headerKey) || $headerKey === '') {
            $headerKey = $request->header('X-Idempotency-Key');
        }

        if (is_string($headerKey) && $headerKey !== '') {
            return $headerKey;
        }

        return hash('xxh128', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
