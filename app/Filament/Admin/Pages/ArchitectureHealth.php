<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;
use Spatie\Health\Facades\Health;

/**
 * ArchitectureHealth - Tool status, queue, cache.
 */
class ArchitectureHealth extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-server-stack';
    protected static ?string $navigationGroup = 'Plataforma';
    protected static ?string $navigationLabel = 'Health';
    protected static ?string $title = 'Architecture Health';
    protected static ?int $navigationSort = 5;
    protected static string $view = 'filament.admin.pages.architecture-health';

    public function getHealthResults(): array
    {
        try {
            $results = Health::check();
            return $results->map(fn ($r) => [
                'name' => $r->label ?? 'Unknown',
                'status' => $r->status?->getValue() ?? 'unknown',
                'message' => $r->shortSummary ?? '',
            ])->toArray();
        } catch (\Throwable $e) {
            return [['name' => 'Error', 'status' => 'failed', 'message' => $e->getMessage()]];
        }
    }
}
