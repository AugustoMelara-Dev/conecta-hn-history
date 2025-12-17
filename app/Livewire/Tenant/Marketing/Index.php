<?php

namespace App\Livewire\Tenant\Marketing;

use App\Models\Tenant;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Marketing Module - Digital Channels & Physical Assets
 *
 * Features:
 * - WhatsApp template configuration with live preview
 * - QR Code generation for offline-to-online conversion
 * - PDF "Table Tent" kit for print
 */
#[Layout('layouts.app')]
class Index extends Component
{
    public Tenant $tenant;

    // ═══════════════════════════════════════════════════════════════════════
    // WHATSAPP CONFIGURATION
    // ═══════════════════════════════════════════════════════════════════════

    #[Validate('nullable|string|min:8|max:20')]
    public string $whatsapp_number = '';

    #[Validate('nullable|string|max:500')]
    public string $whatsapp_template = '';

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

        // Load existing data
        $this->whatsapp_number = $this->tenant->whatsapp_number ?? '';

        // Smart Default: High-conversion template
        $this->whatsapp_template = $this->tenant->whatsapp_template
            ?? "Hola {name}, vi su menú en Conecta HN y quisiera pedir:\n\n";
    }

    // ═══════════════════════════════════════════════════════════════════════
    // COMPUTED PROPERTIES
    // ═══════════════════════════════════════════════════════════════════════

    #[Computed]
    public function isPro(): bool
    {
        return (bool) $this->tenant->is_pro;
    }

    /**
     * Public storefront URL for QR
     */
    #[Computed]
    public function storefrontUrl(): string
    {
        return url("/{$this->tenant->slug}");
    }

    /**
     * QR Code image URL (using Google Charts API)
     */
    #[Computed]
    public function qrCodeUrl(): string
    {
        $url = urlencode($this->storefrontUrl);
        $size = '300x300';

        return "https://chart.googleapis.com/chart?chs={$size}&cht=qr&chl={$url}&choe=UTF-8";
    }

    /**
     * Preview of WhatsApp message with variables replaced
     */
    #[Computed]
    public function messagePreview(): string
    {
        return str_replace('{name}', $this->tenant->name, $this->whatsapp_template);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ACTIONS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Save WhatsApp configuration
     */
    public function save(): void
    {
        $this->validate();

        $this->tenant->update([
            'whatsapp_number' => $this->whatsapp_number ?: null,
            'whatsapp_template' => $this->whatsapp_template ?: null,
        ]);

        session()->flash('toast', '✅ Configuración guardada');
    }

    /**
     * Download QR PDF Kit (stream, no disk storage)
     */
    public function downloadQrPdf()
    {
        // Step 1: Fetch QR image and encode as Base64
        $qrImageData = @file_get_contents($this->qrCodeUrl);

        if (! $qrImageData) {
            session()->flash('error', 'Error al generar el código QR');

            return;
        }

        $qrBase64 = 'data:image/png;base64,'.base64_encode($qrImageData);

        // Step 2: Render PDF view
        $pdf = Pdf::loadView('pdf.qr-table-kit', [
            'tenant' => $this->tenant,
            'qrBase64' => $qrBase64,
            'storefrontUrl' => $this->storefrontUrl,
        ]);

        // Step 3: Stream download (no disk storage)
        $filename = 'Menu_QR_'.preg_replace('/[^A-Za-z0-9]/', '_', $this->tenant->name).'.pdf';

        return response()->streamDownload(function () use ($pdf) {
            echo $pdf->output();
        }, $filename);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RENDER
    // ═══════════════════════════════════════════════════════════════════════

    public function render()
    {
        return view('livewire.tenant.marketing.index')
            ->title('Marketing | '.$this->tenant->name);
    }
}
