<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Services\EventArchitectureMetricsService;
use Filament\Pages\Page;

/**
 * Architecture Health Dashboard (ADR-002 Governance)
 *
 * Shows real-time compliance metrics for Domain Events.
 * Accessible only to super admins at /admin/architecture-health
 */
class ArchitectureHealth extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = 'Architecture Health';
    protected static ?string $navigationGroup = 'Configuración';
    protected static ?int $navigationSort = 100;
    protected static ?string $title = 'Event Architecture Health';
    protected static string $view = 'filament.pages.architecture-health';

    public array $metrics = [];
    public array $summary = [];
    public array $events = [];
    public array $violations = [];

    public function mount(): void
    {
        $service = app(EventArchitectureMetricsService::class);
        $this->metrics = $service->getMetrics();
        $this->summary = $this->metrics['summary'];
        $this->events = $this->metrics['events'];
        $this->violations = $this->metrics['violations'];
    }

    public static function canAccess(): bool
    {
        // Only super admins can access this page
        $user = auth()->user();
        return $user && method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
    }
}
