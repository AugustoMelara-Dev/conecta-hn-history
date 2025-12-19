<?php

namespace App\Livewire\Pages;

use App\Models\Payment;
use App\Settings\GeneralSettings;
use Livewire\Component;

/**
 * PaymentPending - Instrucciones de Pago para Plan PRO
 *
 * Página dedicada para usuarios PRO que acaban de registrarse
 * pero aún no han completado su pago.
 *
 * Características:
 * - Muestra instrucciones de transferencia bancaria dinámicas
 * - Genera enlace de WhatsApp con mensaje prellenado
 * - Incluye referencia de pago única para conciliación
 */
class PaymentPending extends Component
{
    /**
     * Modelo del pago pendiente
     */
    public Payment $payment;

    /**
     * Instrucciones de pago desde settings
     */
    public string $bankInstructions = '';

    /**
     * Número de WhatsApp de soporte
     */
    public string $supportWhatsApp = '';

    /**
     * Inicialización con Route Model Binding
     */
    public function mount(Payment $payment): void
    {
        // Verificar que el pago esté pendiente
        if ($payment->status !== 'pending') {
            // Si ya fue procesado, redirigir
            if ($payment->status === 'approved') {
                session()->flash('success', '¡Tu pago ya fue confirmado! Inicia sesión para continuar.');
                $this->redirect('/login');
            }

            return;
        }

        $this->payment = $payment->load('tenant');

        // Cargar configuración dinámica
        $settings = app(GeneralSettings::class);
        $this->bankInstructions = $settings->bank_transfer_instructions ?? '';
        $this->supportWhatsApp = $settings->support_whatsapp_number ?? '';
    }

    /**
     * Genera URL de WhatsApp con mensaje prellenado para soporte
     */
    public function getWhatsAppUrl(): string
    {
        $phone = preg_replace('/[^0-9]/', '', $this->supportWhatsApp);

        if (empty($phone)) {
            return '#';
        }

        // Agregar código de país si no lo tiene
        if (strlen($phone) === 8) {
            $phone = '504'.$phone; // Honduras
        }

        $tenantName = $this->payment->tenant?->name ?? 'Mi Negocio';
        $reference = $this->payment->reference_number ?? 'N/A';
        $amount = number_format($this->payment->amount, 2);

        $message = "Hola, acabo de registrar mi negocio *{$tenantName}* en Conecta HN.\n\n";
        $message .= "Adjunto el comprobante de pago:\n";
        $message .= "📋 Referencia: *{$reference}*\n";
        $message .= "💰 Monto: *L. {$amount}*";

        return "https://wa.me/{$phone}?text=".urlencode($message);
    }

    /**
     * Renderiza la vista
     */
    public function render()
    {
        return view('livewire.pages.payment-pending', [
            'whatsappUrl' => $this->getWhatsAppUrl(),
        ])->layout('layouts.guest', [
            'title' => 'Completar Pago | Conecta HN',
        ]);
    }
}

