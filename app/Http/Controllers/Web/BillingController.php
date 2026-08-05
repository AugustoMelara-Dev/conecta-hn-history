<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Repositories\PaymentRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Settings\GeneralSettings;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BillingController - Payment Flow Pages
 *
 * MIGRATED FROM: App\Livewire\Pages\PaymentPending
 * REFACTORED: Now uses PaymentRepositoryInterface instead of Eloquent model
 */
final class BillingController extends Controller
{
    public function __construct(
        private readonly PaymentRepositoryInterface $paymentRepository,
    ) {}

    /**
     * Show the payment pending page.
     */
    public function showPaymentPending(string $paymentId): Response
    {
        $result = $this->paymentRepository->findByIdWithTenant($paymentId);

        if (! $result) {
            abort(404, 'Pago no encontrado.');
        }

        $payment = $result['payment'];
        $tenantName = $result['tenant_name'];
        $tenantUserId = $result['tenant_user_id'];

        // Security: Verify ownership
        $user = auth()->user();
        if ($user === null || $tenantUserId === null || (string) $tenantUserId !== (string) $user->id) {
            abort(403, 'No tienes permiso para ver esta pagina.');
        }

        // If payment is already approved, redirect
        if ($payment->status === PaymentStatus::PAID) {
            return Inertia::location(route('login'));
        }

        // Load settings
        $settings = app(GeneralSettings::class);
        $bankInstructions = $settings->bank_transfer_instructions ?? '';
        $supportWhatsApp = $settings->support_whatsapp_number ?? '';

        // Generate WhatsApp URL
        $whatsappUrl = $this->generateWhatsAppUrl(
            $payment->referenceNumber,
            $payment->amount->format(),
            $tenantName,
            $supportWhatsApp
        );

        return Inertia::render('Billing/PaymentPending', [
            'payment' => [
                'id' => $payment->id,
                'reference_number' => $payment->referenceNumber,
                'amount' => $payment->amount->amount / 100,
                'currency' => $payment->amount->currency,
                'status' => $payment->status->value,
                'tenant_name' => $tenantName,
            ],
            'bankInstructions' => $bankInstructions,
            'whatsappUrl' => $whatsappUrl,
        ]);
    }

    /**
     * Generate WhatsApp URL with pre-filled message.
     */
    private function generateWhatsAppUrl(
        string $reference,
        string $formattedAmount,
        string $tenantName,
        string $supportPhone
    ): string {
        $phone = preg_replace('/[^0-9]/', '', $supportPhone);

        if (empty($phone)) {
            return '#';
        }

        // Add country code if missing
        if (strlen($phone) === 8) {
            $phone = '504'.$phone;
        }

        $message = "Hola, acabo de registrar mi negocio *{$tenantName}* en Dovra AI.\n\n";
        $message .= "Adjunto el comprobante de pago:\n";
        $message .= "Referencia: *{$reference}*\n";
        $message .= "Monto: *{$formattedAmount}*";

        return "https://wa.me/{$phone}?text=".rawurlencode($message);
    }
}
