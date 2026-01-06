<?php

declare(strict_types=1);

namespace App\Livewire\Tenant\Settings;

use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * Schedule Manager - Bifurcated Persistence
 *
 * FREE: simple_schedule (String) - Static text display
 * PRO: hours_data (JSON) - Real-time "Open Now" calculation
 */
#[Layout('layouts.app')]
class Schedule extends Component
{
    public Tenant $tenant;

    // FREE: Simple text schedule
    #[Validate('nullable|string|max:100')]
    public string $simpleSchedule = '';

    // PRO: Structured schedule matrix
    public array $structuredSchedule = [];

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

        if ($this->isPro) {
            // PRO: Load structured schedule with defaults
            $this->structuredSchedule = $this->tenant->hours_data ?? $this->getDefaultSchedule();
        } else {
            // FREE: Load simple text
            $this->simpleSchedule = $this->tenant->simple_schedule ?? '';
        }
    }

    /**
     * Generate default 9-5 schedule
     */
    private function getDefaultSchedule(): array
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $schedule = [];

        foreach ($days as $day) {
            $schedule[$day] = [
                'isOpen' => $day !== 'sunday',
                'open' => '09:00',
                'close' => '17:00',
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
     * Save with bifurcated persistence logic
     */
    public function save(): void
    {
        if ($this->isPro) {
            $this->saveProSchedule();
        } else {
            $this->saveFreeSchedule();
        }

        session()->flash('toast', '✅ Horario actualizado');
    }

    /**
     * PRO: Validate and save structured schedule
     */
    private function saveProSchedule(): void
    {
        // Validate close > open for each open day
        foreach ($this->structuredSchedule as $day => $hours) {
            if (($hours['isOpen'] ?? false) === true) {
                $open = $hours['open'] ?? '00:00';
                $close = $hours['close'] ?? '00:00';

                if ($close <= $open) {
                    throw ValidationException::withMessages([
                        "structuredSchedule.{$day}" => "La hora de cierre del {$this->dayLabels[$day]} debe ser posterior a la apertura.",
                    ]);
                }
            }
        }

        // Sanitized persistence: Use hours_data, clear simple_schedule
        $this->tenant->update([
            'hours_data' => $this->structuredSchedule,
            'simple_schedule' => null,
        ]);
    }

    /**
     * FREE: Validate and save simple text
     */
    private function saveFreeSchedule(): void
    {
        $this->validate([
            'simpleSchedule' => 'required|string|max:100',
        ]);

        // Sanitized persistence: Use simple_schedule, clear hours_data
        $this->tenant->update([
            'simple_schedule' => $this->simpleSchedule,
            'hours_data' => null,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // RENDER
    // ═══════════════════════════════════════════════════════════════════════

    public function render()
    {
        return view('livewire.tenant.settings.schedule')
            ->title('Horarios | '.$this->tenant->name);
    }
}
