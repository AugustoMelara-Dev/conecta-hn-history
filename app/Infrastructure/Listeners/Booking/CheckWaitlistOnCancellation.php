<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Booking;

use App\Domain\Booking\Events\AppointmentCancelled;
use App\Infrastructure\Queue\Middleware\TenantQueueMiddleware;
use App\Models\Waitlist;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

final class CheckWaitlistOnCancellation implements ShouldQueue
{
    use InteractsWithQueue;

    public int $tries = 3;

    public function middleware(): array
    {
        return [app(TenantQueueMiddleware::class)];
    }

    public function handle(AppointmentCancelled $event): void
    {
        $date = Carbon::parse($event->scheduledAt)->toDateString();

        $waitlistedCustomers = Waitlist::where('tenant_id', $event->tenantId)
            ->where('service_id', $event->serviceId)
            ->where('requested_date', $date)
            ->active()
            ->get();

        if ($waitlistedCustomers->isEmpty()) {
            return;
        }

        Log::info('CheckWaitlistOnCancellation: Found waitlisted customers for freed slot', [
            'tenant_id' => $event->tenantId,
            'service_id' => $event->serviceId,
            'date' => $date,
            'match_count' => $waitlistedCustomers->count(),
        ]);

        foreach ($waitlistedCustomers as $customer) {
            // Future step: Dispatch email/SMS notification to $customer->customer_email
            // and update status to 'notified'.
        }
    }
}
