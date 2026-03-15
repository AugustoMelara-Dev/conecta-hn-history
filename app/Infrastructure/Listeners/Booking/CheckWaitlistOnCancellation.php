<?php

declare(strict_types=1);

namespace App\Infrastructure\Listeners\Booking;

use App\Domain\Booking\Events\AppointmentCancelled;
use App\Infrastructure\Queue\Middleware\TenantQueueMiddleware;
use App\Mail\WaitlistSlotFreedMail;
use App\Models\Waitlist;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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

        $tenant = \App\Models\Tenant::find($event->tenantId);
        $service = \App\Models\Service::find($event->serviceId);

        if (! $tenant || ! $service) {
            Log::warning('CheckWaitlistOnCancellation: Missing Tenant or Service info for email dispatch');
            return;
        }

        $bookingUrl = url("/t/{$tenant->slug}/book");

        foreach ($waitlistedCustomers as $customer) {
            try {
                Mail::to($customer->customer_email)->send(new WaitlistSlotFreedMail(
                    customerName: $customer->customer_name ?? 'Cliente',
                    tenantName: $tenant->name,
                    serviceName: $service->name,
                    date: $date,
                    bookingUrl: $bookingUrl
                ));

                $customer->update(['status' => 'notified']);
            } catch (\Exception $e) {
                Log::error('CheckWaitlistOnCancellation: Failed to send waitlist email', [
                    'waitlist_id' => $customer->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}