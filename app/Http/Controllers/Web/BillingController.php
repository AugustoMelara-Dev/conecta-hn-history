<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Billing\Enums\PaymentStatus;
use App\Domain\Billing\Repositories\PaymentRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Settings\GeneralSettings;
use App\Support\Diagnostics\SupportDiagnosticFactory;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

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
        private readonly SupportDiagnosticFactory $diagnostics,
    ) {}

    /**
     * Show the payment pending page.
     */
    public function showPaymentPending(string $paymentId): InertiaResponse|RedirectResponse|Response
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return redirect()->guest(route('login'));
        }

        $payment = $this->paymentRepository->findById($paymentId);

        if ($payment === null) {
            abort(404, 'Pago no encontrado.');
        }

        $tenant = Tenant::query()->find($payment->tenantId);

        if (! $tenant instanceof Tenant) {
            abort(404, 'Negocio asociado no encontrado.');
        }

        $tenantName = $tenant->name;
        $tenantUserId = $tenant->user_id;

        if ($user instanceof User && (string) $tenantUserId !== (string) $user->id) {
            abort(403, 'No tienes permiso para ver esta pagina.');
        }

        if ($payment->status === PaymentStatus::PAID) {
            return Inertia::location(route('login'));
        }

        $settings = app(GeneralSettings::class);
        $bankInstructions = $settings->bank_transfer_instructions ?? '';
        $supportWhatsApp = $settings->support_whatsapp_number ?? '';

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
            'diagnostic' => $this->diagnostics->make(
                'billing',
                'Pago pendiente de validacion',
                'El comprobante aun no ha sido aprobado por el equipo.',
                'Envia el comprobante por WhatsApp o correo e incluye la referencia de pago.'
            ),
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

        if (strlen($phone) === 8) {
            $phone = '504'.$phone;
        }

        $message = "Hola, acabo de registrar mi negocio *{$tenantName}* en Vito Business OS.\n\n";
        $message .= "Adjunto el comprobante de pago:\n";
        $message .= "Referencia: *{$reference}*\n";
        $message .= "Monto: *{$formattedAmount}*";

        return "https://wa.me/{$phone}?text=".urlencode($message);
    }
}
