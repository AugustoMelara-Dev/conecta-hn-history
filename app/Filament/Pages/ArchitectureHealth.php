<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Identity\Enums\UserRole;
use App\Models\User;
use App\Services\EventArchitectureMetricsService;
use Filament\Pages\Page;

class ArchitectureHealth extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Salud de arquitectura';

    protected static string|\UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 100;

    protected static ?string $title = 'Salud de arquitectura';

    protected string $view = 'filament.pages.architecture-health';

    public array $metrics = [];

    public array $summary = [];

    public array $events = [];

    public array $violations = [];

    public function mount(): void
    {
        try {
            $service = app(EventArchitectureMetricsService::class);
            $this->metrics = $service->getMetrics();
            $this->summary = $this->metrics['summary'] ?? [];
            $this->events = $this->metrics['events'] ?? [];
            $this->violations = $this->metrics['violations'] ?? [];
        } catch (\Throwable) {
            $this->metrics = [];
            $this->summary = [
                'total_events' => 0,
                'compliant_count' => 0,
                'non_compliant_count' => 0,
                'compliance_percentage' => 0,
                'health_status' => 'unknown',
                'last_checked_at' => now()->format('Y-m-d H:i:s'),
            ];
            $this->events = [];
            $this->violations = [];
        }
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $user->role === UserRole::SUPER_ADMIN;
    }
}
