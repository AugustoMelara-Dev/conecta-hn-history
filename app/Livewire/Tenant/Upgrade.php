<?php

namespace App\Livewire\Tenant;

use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Upgrade Landing Page - Internal Sales Page
 *
 * Purpose: Bridge between Dashboard (purchase intent) and Payment confirmation.
 * Flow: Dashboard → Upgrade → Payment::create → PaymentPending (Receipt)
 */
#[Layout('layouts.app')]
class Upgrade extends Component
{
    // ═══════════════════════════════════════════════════════════════════════
    // PRICING CONFIGURATION
    // ═══════════════════════════════════════════════════════════════════════

    public const PRICE_MONTHLY = 1500.00;

    public const CURRENCY = 'HNL';

    // ═══════════════════════════════════════════════════════════════════════
    // STATE
    // ═══════════════════════════════════════════════════════════════════════

    public Tenant $tenant;

    public bool $processing = false;

    /**
     * Mount with strict security validations
     */
    public function mount(Tenant $tenant): void
    {
        // ─────────────────────────────────────────────────────────────────────
        // GUARD CLAUSE 1: Ownership Verification
        // ─────────────────────────────────────────────────────────────────────
        if (Auth::id() !== $tenant->user_id) {
            abort(403, 'No tienes permiso para actualizar este negocio.');
        }

        // ─────────────────────────────────────────────────────────────────────
        // GUARD CLAUSE 2: Already PRO - Redirect back
        // ─────────────────────────────────────────────────────────────────────
        if ($tenant->is_pro) {
            session()->flash('toast', '✅ Tu Plan PRO ya está activo');

            $this->redirect("/app/{$tenant->slug}");

            return;
        }

        $this->tenant = $tenant;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ACTIONS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Create upgrade payment order and redirect to pending receipt
     */
    public function createUpgradeOrder()
    {
        $this->processing = true;

        try {
            $payment = DB::transaction(function () {
                return Payment::create([
                    'tenant_id' => $this->tenant->id,
                    'user_id' => Auth::id(),
                    'amount' => self::PRICE_MONTHLY,
                    'reference_number' => 'PRO-'.strtoupper(Str::random(8)),
                    'status' => 'pending',
                    'description' => "Suscripción PRO Mensual - {$this->tenant->name}",
                    'currency' => self::CURRENCY,
                ]);
            });

            // Redirect to existing PaymentPending page (DRY principle)
            return redirect()->route('payment.pending', ['payment' => $payment->id]);

        } catch (\Exception $e) {
            $this->processing = false;

            session()->flash('error', 'Error al procesar la orden. Intenta nuevamente.');

            return null;
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // COMPUTED DATA
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Features comparison for pricing table
     */
    public function getFeatures(): array
    {
        return [
            'free' => [
                ['text' => 'Límite de 10 productos', 'included' => true],
                ['text' => 'Micrositio básico', 'included' => true],
                ['text' => 'Botón de WhatsApp', 'included' => false],
                ['text' => 'Pedidos por WhatsApp', 'included' => false],
                ['text' => 'Visibilidad destacada', 'included' => false],
                ['text' => 'Estadísticas avanzadas', 'included' => false],
                ['text' => 'Soporte prioritario', 'included' => false],
            ],
            'pro' => [
                ['text' => 'Hasta 100 productos', 'included' => true],
                ['text' => 'Micrositio premium', 'included' => true],
                ['text' => 'Botón de WhatsApp activo', 'included' => true],
                ['text' => 'Pedidos ilimitados por WhatsApp', 'included' => true],
                ['text' => 'Visibilidad destacada en directorio', 'included' => true],
                ['text' => 'Estadísticas y gráficos de ventas', 'included' => true],
                ['text' => 'Soporte prioritario 24/7', 'included' => true],
            ],
        ];
    }

    /**
     * Render the upgrade view
     */
    public function render()
    {
        return view('livewire.tenant.upgrade', [
            'features' => $this->getFeatures(),
            'price' => self::PRICE_MONTHLY,
        ])->title('Mejorar a PRO | '.$this->tenant->name);
    }
}
