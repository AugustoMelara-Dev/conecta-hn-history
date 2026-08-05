<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

final class DispatchAppointmentReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public function handle(): void
    {
        Tenant::query()
            ->where('is_active', true)
            ->select('id')
            ->chunkById(100, static function ($tenants): void {
                foreach ($tenants as $tenant) {
                    SendAppointmentReminders::dispatch((string) $tenant->getKey());
                }
            });
    }
}
