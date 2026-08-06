<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;

/**
 * JobsQueue - Queue monitoring.
 */
class JobsQueue extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-queue-list';
    protected static ?string $navigationGroup = 'Plataforma';
    protected static ?string $navigationLabel = 'Jobs / Queue';
    protected static ?string $title = 'Jobs & Queue';
    protected static ?int $navigationSort = 7;
    protected static string $view = 'filament.admin.pages.jobs-queue';

    public function getQueueStats(): array
    {
        try {
            return [
                'jobs_count' => DB::table('jobs')->count(),
                'failed_count' => DB::table('failed_jobs')->count(),
            ];
        } catch (\Throwable $e) {
            return ['jobs_count' => 0, 'failed_count' => 0, 'error' => $e->getMessage()];
        }
    }
}
