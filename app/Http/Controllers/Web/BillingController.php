<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Settings\GeneralSettings;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BillingController - Payment Flow Pages
 *
 * MIGRATED FROM: App\Livewire\Pages\PaymentPending
 */
final class BillingController extends Controller
{
    /**
     * Show the payment pending page.
     */
    public function showPaymentPending(string $paymentId): Response
    {
        $payment = Payment::with('tenant')->findOrFail($paymentId);

        // Security: Verify ownership
        $user = auth()->user();
        if ($user && $payment->tenant->user_id !== $user->id) {
            abort(403, 'No tienes permiso para ver esta página.');
        }

        // If payment is already approved, redirect
        if ($payment->status === 'approved') {
            return Inertia::location(route('login'));
        }

        // Load settings
        $settings = app(GeneralSettings::class);
        $bankInstructions = $settings->bank_transfer_instructions ?? '';
        $supportWhatsApp = $settings->support_whatsapp_number ?? '';

        // Generate WhatsApp URL
        $whatsappUrl = $this->generateWhatsAppUrl($payment, $supportWhatsApp);

        return Inertia::render('Billing/PaymentPending', [
            'payment' => [
                'id' => $payment->id,
                'reference_number' => $payment->reference_number,
                'amount' => $payment->amount,
                'currency' => $payment->currency ?? 'HNL',
                'status' => $payment->status,
                'description' => $payment->description,
                'tenant_name' => $payment->tenant->name ?? 'Tu Negocio',
            ],
            'bankInstructions' => $bankInstructions,
            'whatsappUrl' => $whatsappUrl,
        ]);
    }

    /**
     * Generate WhatsApp URL with pre-filled message.
     */
    private function generateWhatsAppUrl(Payment $payment, string $supportPhone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $supportPhone);

        if (empty($phone)) {
            return '#';
        }

        // Add country code if missing
        if (strlen($phone) === 8) {
            $phone = '504' . $phone; // Honduras
        }

        $tenantName = $payment->tenant?->name ?? 'Mi Negocio';
        $reference = $payment->reference_number ?? 'N/A';
        $amount = number_format($payment->amount, 2);

        $message = "Hola, acabo de registrar mi negocio *{$tenantName}* en Conecta HN.\n\n";
        $message .= "Adjunto el comprobante de pago:\n";
        $message .= "📋 Referencia: *{$reference}*\n";
        $message .= "💰 Monto: *L. {$amount}*";

        return "https://wa.me/{$phone}?text=" . urlencode($message);
    }
}
