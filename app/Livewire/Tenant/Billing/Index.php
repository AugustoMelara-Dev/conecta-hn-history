<?php

namespace App\Livewire\Tenant\Billing;

use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Billing Index - Subscription Management & Payment Reporting
 *
 * Polymorphic UI:
 * - FREE: Upsell landing page
 * - PRO: Subscription dashboard with payment history
 */
#[Layout('layouts.app')]
class Index extends Component
{
    use WithFileUploads;

    public Tenant $tenant;

    // Modal State
    public bool $showPaymentModal = false;

    // Payment Form
    #[Validate('required|in:BAC,Ficohsa,Atlantida,Occidente')]
    public string $bank = '';

    #[Validate('required|string|min:4|max:50')]
    public string $reference_number = '';

    #[Validate('required|numeric|min:100')]
    public float $amount = 1500.00;

    #[Validate('required|image|max:3072')] // Max 3MB
    public $proof_photo = null;

    // ═══════════════════════════════════════════════════════════════════════
    // LIFECYCLE
    // ═══════════════════════════════════════════════════════════════════════

    public function mount(): void
    {
        $user = Auth::user();

        if (! $user || ! $user->tenant) {
            abort(403, 'No tienes un negocio asociado.');
        }

        $this->tenant = $user->tenant;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // COMPUTED PROPERTIES
    // ═══════════════════════════════════════════════════════════════════════

    #[Computed]
    public function isPro(): bool
    {
        return (bool) $this->tenant->is_pro;
    }

    #[Computed]
    public function paymentHistory()
    {
        return SubscriptionPayment::where('tenant_id', $this->tenant->id)
            ->orderByDesc('created_at')
            ->get();
    }

    #[Computed]
    public function hasPendingPayment(): bool
    {
        return SubscriptionPayment::where('tenant_id', $this->tenant->id)
            ->pending()
            ->exists();
    }

    /**
     * Status semaphore color based on billing date
     */
    #[Computed]
    public function statusColor(): string
    {
        if (! $this->tenant->next_billing_date) {
            return 'gray';
        }

        $daysLeft = now()->diffInDays($this->tenant->next_billing_date, false);

        if ($daysLeft < 0) {
            return 'red';
        }
        if ($daysLeft <= 3) {
            return 'yellow';
        }

        return 'green';
    }

    /**
     * Human-readable status message
     */
    #[Computed]
    public function statusMessage(): string
    {
        if (! $this->tenant->next_billing_date) {
            return 'Sin fecha de facturación configurada';
        }

        $daysLeft = now()->diffInDays($this->tenant->next_billing_date, false);
        $date = $this->tenant->next_billing_date->format('d/m/Y');

        if ($daysLeft < 0) {
            return "⚠️ Vencido desde el {$date}. Por favor reporta tu pago.";
        }
        if ($daysLeft <= 3) {
            return "⏰ Vence pronto ({$date}). Renueva ahora.";
        }
        if ($daysLeft == 0) {
            return "📅 Vence hoy ({$date}).";
        }

        return "✅ Al día. Próximo pago: {$date} ({$daysLeft} días).";
    }

    #[Computed]
    public function bankOptions(): array
    {
        return [
            'BAC' => 'BAC Credomatic',
            'Ficohsa' => 'Banco Ficohsa',
            'Atlantida' => 'Banco Atlántida',
            'Occidente' => 'Banco de Occidente',
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ACTIONS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Report a new payment
     */
    public function reportPayment(): void
    {
        $this->validate();

        // Check for duplicate reference
        $exists = SubscriptionPayment::where('bank', $this->bank)
            ->where('reference_number', $this->reference_number)
            ->exists();

        if ($exists) {
            $this->addError('reference_number', 'Este número de referencia ya fue reportado.');

            return;
        }

        // Store proof image on private disk
        $path = $this->proof_photo->store("payments/{$this->tenant->id}", 'public');

        // Create payment record
        SubscriptionPayment::create([
            'tenant_id' => $this->tenant->id,
            'bank' => $this->bank,
            'reference_number' => $this->reference_number,
            'amount' => $this->amount,
            'proof_path' => $path,
            'status' => 'pending',
        ]);

        // Reset form
        $this->reset(['bank', 'reference_number', 'proof_photo', 'showPaymentModal']);
        $this->amount = 1500.00;

        session()->flash('toast', '✅ Pago reportado. Un administrador lo verificará pronto.');
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RENDER
    // ═══════════════════════════════════════════════════════════════════════

    public function render()
    {
        return view('livewire.tenant.billing.index')
            ->title('Facturación | '.$this->tenant->name);
    }
}

