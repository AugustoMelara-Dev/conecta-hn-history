<?php

declare(strict_types=1);

namespace App\Livewire\Tenant\Settings;

use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Tenant Settings Profile - Branding & Configuration Center
 *
 * Features:
 * - Logo & Cover image upload with garbage collection
 * - Schedule matrix with validation (close > open)
 * - Social media links (Omnichannel)
 * - Contact information
 */
#[Layout('layouts.app')]
class Profile extends Component
{
    use WithFileUploads;

    public Tenant $tenant;

    // ═══════════════════════════════════════════════════════════════════════
    // BRANDING IMAGES
    // ═══════════════════════════════════════════════════════════════════════

    #[Validate('nullable|image|max:2048|mimes:png,jpg,jpeg,webp')]
    public $logo = null;

    public ?string $existingLogo = null;

    #[Validate('nullable|image|max:2048|mimes:png,jpg,jpeg,webp')]
    public $cover = null;

    public ?string $existingCover = null;

    // ═══════════════════════════════════════════════════════════════════════
    // PROFILE FIELDS
    // ═══════════════════════════════════════════════════════════════════════

    #[Validate('required|string|max:255')]
    public string $name = '';

    #[Validate('nullable|string|max:1000')]
    public ?string $description = '';

    #[Validate('nullable|string|max:2000')]
    public ?string $about = '';

    #[Validate('nullable|string|max:500')]
    public ?string $address = '';

    #[Validate('nullable|string|max:20')]
    public ?string $phone = '';

    #[Validate('nullable|string|max:20')]
    public ?string $whatsapp_number = '';

    // ═══════════════════════════════════════════════════════════════════════
    // SOCIAL MEDIA
    // ═══════════════════════════════════════════════════════════════════════

    #[Validate('nullable|url|max:255')]
    public ?string $facebook_url = '';

    #[Validate('nullable|url|max:255')]
    public ?string $instagram_url = '';

    #[Validate('nullable|url|max:255')]
    public ?string $tiktok_url = '';

    #[Validate('nullable|url|max:255')]
    public ?string $website_url = '';

    // ═══════════════════════════════════════════════════════════════════════
    // SCHEDULE MATRIX
    // ═══════════════════════════════════════════════════════════════════════

    public array $schedule = [];

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
        $this->name = $this->tenant->name ?? '';
        $this->description = $this->tenant->description ?? '';
        $this->about = $this->tenant->about ?? '';
        $this->address = $this->tenant->address ?? '';
        $this->phone = $this->tenant->phone ?? '';
        $this->whatsapp_number = $this->tenant->whatsapp_number ?? '';

        // Social media
        $this->facebook_url = $this->tenant->facebook_url ?? '';
        $this->instagram_url = $this->tenant->instagram_url ?? '';
        $this->tiktok_url = $this->tenant->tiktok_url ?? '';
        $this->website_url = $this->tenant->website_url ?? '';

        // Images
        $this->existingLogo = $this->tenant->logo_path;
        $this->existingCover = $this->tenant->cover_path;

        // Schedule with defaults
        $this->schedule = $this->tenant->hours_data ?? $this->getDefaultSchedule();
    }

    /**
     * Generate default schedule structure
     */
    private function getDefaultSchedule(): array
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $schedule = [];

        foreach ($days as $day) {
            $schedule[$day] = [
                'isOpen' => $day !== 'sunday',
                'open' => '08:00',
                'close' => '18:00',
            ];
        }

        return $schedule;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // COMPUTED
    // ═══════════════════════════════════════════════════════════════════════

    #[Computed]
    public function isPro(): bool
    {
        return (bool) $this->tenant->is_pro;
    }

    #[Computed]
    public function dayLabels(): array
    {
        return [
            'monday' => 'Lunes',
            'tuesday' => 'Martes',
            'wednesday' => 'Miércoles',
            'thursday' => 'Jueves',
            'friday' => 'Viernes',
            'saturday' => 'Sábado',
            'sunday' => 'Domingo',
        ];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ACTIONS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Save profile with validation and garbage collection
     */
    public function save(): void
    {
        $this->validate();

        // Validate schedule (close > open)
        $this->validateSchedule();

        // ─────────────────────────────────────────────────────────────────────
        // Handle Logo Upload (with Garbage Collection)
        // ─────────────────────────────────────────────────────────────────────
        $logoPath = $this->existingLogo;

        if ($this->logo) {
            // Delete old logo if exists
            if ($this->existingLogo) {
                Storage::disk('public')->delete($this->existingLogo);
            }

            $folder = "tenants/{$this->tenant->id}/branding";
            $logoPath = $this->logo->store($folder, 'public');
        }

        // ─────────────────────────────────────────────────────────────────────
        // Handle Cover Upload (with Garbage Collection)
        // ─────────────────────────────────────────────────────────────────────
        $coverPath = $this->existingCover;

        if ($this->cover) {
            // Delete old cover if exists
            if ($this->existingCover) {
                Storage::disk('public')->delete($this->existingCover);
            }

            $folder = "tenants/{$this->tenant->id}/branding";
            $coverPath = $this->cover->store($folder, 'public');
        }

        // ─────────────────────────────────────────────────────────────────────
        // Persist Tenant
        // ─────────────────────────────────────────────────────────────────────
        $this->tenant->update([
            'name' => $this->name,
            'description' => $this->description,
            'about' => $this->about,
            'address' => $this->address,
            'phone' => $this->phone,
            'whatsapp_number' => $this->whatsapp_number,
            'facebook_url' => $this->facebook_url ?: null,
            'instagram_url' => $this->instagram_url ?: null,
            'tiktok_url' => $this->tiktok_url ?: null,
            'website_url' => $this->website_url ?: null,
            'logo_path' => $logoPath,
            'cover_path' => $coverPath,
            'hours_data' => $this->schedule,
        ]);

        // Update local references
        $this->existingLogo = $logoPath;
        $this->existingCover = $coverPath;
        $this->logo = null;
        $this->cover = null;

        session()->flash('toast', '✅ Perfil actualizado correctamente');
    }

    /**
     * Validate schedule (close time must be after open time)
     */
    private function validateSchedule(): void
    {
        foreach ($this->schedule as $day => $hours) {
            if (($hours['isOpen'] ?? false) === true) {
                $open = $hours['open'] ?? '00:00';
                $close = $hours['close'] ?? '00:00';

                if ($close <= $open) {
                    throw ValidationException::withMessages([
                        "schedule.{$day}" => "La hora de cierre del {$this->dayLabels[$day]} debe ser posterior a la apertura.",
                    ]);
                }
            }
        }
    }

    /**
     * Remove logo
     */
    public function removeLogo(): void
    {
        if ($this->existingLogo) {
            Storage::disk('public')->delete($this->existingLogo);
            $this->tenant->update(['logo_path' => null]);
            $this->existingLogo = null;
        }

        $this->logo = null;
    }

    /**
     * Remove cover
     */
    public function removeCover(): void
    {
        if ($this->existingCover) {
            Storage::disk('public')->delete($this->existingCover);
            $this->tenant->update(['cover_path' => null]);
            $this->existingCover = null;
        }

        $this->cover = null;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RENDER
    // ═══════════════════════════════════════════════════════════════════════

    public function render()
    {
        return view('livewire.tenant.settings.profile')
            ->title('Configuración | '.$this->tenant->name);
    }
}
