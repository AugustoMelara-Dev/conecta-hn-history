<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BookingController - Public Appointment Booking Flow
 * 
 * Handles the customer-facing booking wizard for SERVICE-type tenants.
 * This is a PUBLIC controller - no authentication required.
 * 
 * SECURITY: All queries are scoped to the resolved tenant via route binding.
 */
class BookingController extends Controller
{
    /**
     * Display the booking wizard page.
     * 
     * GET /{tenant:slug}/book
     */
    public function index(Tenant $tenant): Response
    {
        // Ensure tenant has service-type business
        $services = $tenant->services()
            ->active()
            ->get()
            ->map(fn (Service $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'description' => $s->description,
                'price' => $s->formatted_price,
                'duration_minutes' => $s->duration_minutes,
                'duration_formatted' => $s->formatted_duration,
            ]);

        $providers = $tenant->serviceProviders()
            ->active()
            ->get(['id', 'name']);

        return Inertia::render('Public/Book/Index', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'logo_url' => $tenant->logo_url,
                'primary_color' => $tenant->primary_color ?? '#000000',
            ],
            'services' => $services,
            'providers' => $providers,
        ]);
    }

    /**
     * Get available providers for a specific service.
     * 
     * GET /{tenant:slug}/book/providers/{service}
     */
    public function getProviders(Tenant $tenant, Service $service): JsonResponse
    {
        // Security: Ensure service belongs to this tenant
        if ($service->tenant_id !== $tenant->id) {
            abort(404, 'Service not found');
        }

        $providers = $tenant->serviceProviders()
            ->active()
            ->get(['id', 'name']);

        return response()->json([
            'providers' => $providers,
        ]);
    }

    /**
     * Get available time slots for a specific date.
     * 
     * GET /{tenant:slug}/book/slots
     */
    public function getSlots(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'service_id' => 'required|exists:services,id',
            'provider_id' => 'nullable|exists:service_providers,id',
        ]);

        $date = Carbon::parse($validated['date']);
        $service = Service::find($validated['service_id']);
        
        // Security: Ensure service belongs to tenant
        if ($service->tenant_id !== $tenant->id) {
            abort(404, 'Service not found');
        }

        // Generate slots based on actual opening hours (Spatie integration)
        $slots = $this->generateAvailableSlots(
            $tenant,
            $date,
            $service->duration_minutes,
            $validated['provider_id'] ?? null
        );

        return response()->json(['slots' => $slots]);
    }

    /**
     * Create a new appointment.
     * 
     * POST /{tenant:slug}/book
     */
    public function store(Request $request, Tenant $tenant)
    {
        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'service_provider_id' => 'required|exists:service_providers,id',
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_notes' => 'nullable|string|max:500',
        ]);

        $service = Service::findOrFail($validated['service_id']);
        $provider = ServiceProvider::findOrFail($validated['service_provider_id']);

        // Security: Verify both belong to this tenant
        if ($service->tenant_id !== $tenant->id || $provider->tenant_id !== $tenant->id) {
            abort(403, 'Invalid service or provider');
        }

        $scheduledAt = Carbon::parse($validated['date'] . ' ' . $validated['time']);
        $endAt = $scheduledAt->copy()->addMinutes($service->duration_minutes);

        // Check provider availability (prevent double-booking)
        if (!$provider->isAvailableAt($scheduledAt, $endAt)) {
            return back()->withErrors([
                'time' => 'Este horario ya no está disponible. Por favor selecciona otro.',
            ]);
        }

        $appointment = new Appointment([
            'tenant_id' => $tenant->id,
            'service_id' => $service->id,
            'service_provider_id' => $provider->id,
            'scheduled_at' => $scheduledAt,
            'end_at' => $endAt,
            'customer_name' => $validated['customer_name'],
            'customer_email' => $validated['customer_email'],
            'customer_phone' => $validated['customer_phone'],
            'customer_notes' => $validated['customer_notes'] ?? null,
            'status' => Appointment::STATUS_PENDING,
        ]);

        $appointment->save();

        return redirect()->route('public.booking.success', [
            'tenant' => $tenant,
            'appointment' => $appointment->id,
        ]);
    }

    /**
     * Display booking success page.
     * 
     * GET /{tenant:slug}/book/success
     */
    public function success(Tenant $tenant, Request $request): Response
    {
        $appointmentId = $request->query('appointment');
        $appointment = null;

        if ($appointmentId) {
            $appointment = Appointment::with(['service', 'provider'])
                ->where('tenant_id', $tenant->id)
                ->find($appointmentId);
        }

        return Inertia::render('Public/Book/Success', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'appointment' => $appointment ? [
                'id' => $appointment->id,
                'service_name' => $appointment->service->name,
                'provider_name' => $appointment->provider->name,
                'scheduled_at' => $appointment->scheduled_at->format('l, d M Y'),
                'time' => $appointment->scheduled_at->format('h:i A'),
                'customer_name' => $appointment->customer_name,
            ] : null,
        ]);
    }

    /**
     * Generate available time slots for a given date.
     *
     * Uses spatie/opening-hours to respect tenant business hours
     * and provider schedule overrides.
     *
     * @param Tenant $tenant The tenant for fallback hours
     * @param Carbon $date The date to generate slots for
     * @param int $durationMinutes Service duration in minutes
     * @param int|null $providerId Optional provider ID for schedule override
     * @return array<array{time: string, display: string}>
     */
    private function generateAvailableSlots(
        Tenant $tenant,
        Carbon $date,
        int $durationMinutes,
        ?int $providerId
    ): array {
        $slots = [];

        // Resolve OpeningHours source (Provider > Tenant hierarchy)
        $provider = $providerId ? ServiceProvider::find($providerId) : null;
        $openingHours = $provider?->getOpeningHours() ?? $tenant->getOpeningHours();

        // Get open ranges for the requested date (handles closed days automatically)
        $ranges = $openingHours->forDate($date);

        // Each range is a Spatie\OpeningHours\TimeRange with start/end
        foreach ($ranges as $range) {
            $rangeStart = $date->copy()->setTimeFromTimeString($range->start()->format('H:i:s'));
            $rangeEnd = $date->copy()->setTimeFromTimeString($range->end()->format('H:i:s'));

            // Round duration to nearest 15 minutes for reasonable slot intervals
            $interval = max(15, min($durationMinutes, 60));
            $current = $rangeStart->copy();

            while ($current < $rangeEnd) {
                $slotEnd = $current->copy()->addMinutes($durationMinutes);

                // Slot must fit strictly within the open range
                if ($slotEnd <= $rangeEnd) {
                    $isAvailable = true;

                    // If provider specified, check for appointment conflicts
                    if ($provider) {
                        $isAvailable = $provider->isAvailableAt($current, $slotEnd);
                    }

                    if ($isAvailable) {
                        $slots[] = [
                            'time' => $current->format('H:i'),
                            'display' => $current->format('h:i A'),
                        ];
                    }
                }

                $current->addMinutes($interval);
            }
        }

        return $slots;
    }
}
