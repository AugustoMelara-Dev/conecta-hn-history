<?php

declare(strict_types=1);

namespace App\Infrastructure\Booking;

// @withoutGlobalScopes-safety INFRA_TENANT_FILTER: public booking resolves tenant ownership from the canonical service context.

use App\Application\Availability\AvailabilityQuery;
use App\Application\Reservations\CanonicalReservationWriter;
use App\Application\Reservations\Commands\CreateReservationCommand;
use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Location;
use App\Models\Service;
use App\Models\Tenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BookingController - Public Appointment Booking Flow
 *
 * Handles the customer-facing booking wizard for SERVICE-type tenants.
 * This is a PUBLIC controller - no authentication required.
 *
 * SECURITY: All queries are scoped to the tenant resolved from the subdomain middleware.
 */
final class HttpPublicBookingResponder extends Controller
{
    public function __construct(
        private readonly AvailabilityQuery $availability,
        private readonly CanonicalReservationWriter $reservations,
    ) {}

    /**
     * Display the booking wizard page.
     *
     * GET /{tenant:slug}/book
     */
    public function index(Request $request): Response
    {
        $tenant = $this->resolveTenant();

        // Ensure tenant has service-type business
        $services = Service::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('is_active', true)
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
            ->where('is_active', true)
            ->get(['id', 'name']);

        return Inertia::render('Public/Book/Index', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'logo_url' => $this->tenantLogoUrl($tenant),
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
    public function getProviders(Request $request, mixed $service): JsonResponse
    {
        $tenant = $this->resolveTenant();
        $service = $service instanceof Service ? $service : Service::query()->findOrFail($service);

        // Security: Ensure service belongs to this tenant
        if ($service->tenant_id !== $tenant->id) {
            abort(404, 'Service not found');
        }

        $providers = $tenant->serviceProviders()
            ->where('is_active', true)
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
    public function getSlots(Request $request): JsonResponse
    {
        $tenant = $this->resolveTenant();

        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'service_id' => 'required|exists:services,id',
            'provider_id' => 'nullable|exists:service_providers,id',
            'location_id' => 'nullable|uuid|exists:locations,id',
        ]);

        $service = Service::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($validated['service_id']);

        $location = $this->resolveBookingLocation(
            $tenant,
            $service,
            $validated['location_id'] ?? null,
        );

        $slots = $this->availability->slots(
            (string) $tenant->id,
            (int) $service->id,
            isset($validated['provider_id']) ? (int) $validated['provider_id'] : null,
            (string) $location->id,
            CarbonImmutable::parse($validated['date'], $location->timezone_name),
        );

        return response()->json(['slots' => $slots]);
    }

    /**
     * Create a new appointment.
     *
     * POST /{tenant:slug}/book
     */
    public function store(Request $request)
    {
        $tenant = $this->resolveTenant();

        $validated = $request->validate([
            'service_id' => 'required|exists:services,id',
            'service_provider_id' => 'required|exists:service_providers,id',
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'starts_at_utc' => 'required|date',
            'location_id' => 'nullable|uuid|exists:locations,id',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_notes' => 'nullable|string|max:500',
            'idempotency_key' => 'nullable|string|max:160',
        ]);

        $service = Service::query()
            ->where('tenant_id', $tenant->id)
            ->findOrFail($validated['service_id']);

        $location = $this->resolveBookingLocation(
            $tenant,
            $service,
            $validated['location_id'] ?? null,
        );
        $scheduledAt = CarbonImmutable::parse($validated['starts_at_utc'])->utc();
        $localStart = $scheduledAt->setTimezone($location->timezone_name);
        if ($localStart->format('Y-m-d') !== $validated['date']
            || $localStart->format('H:i') !== $validated['time']) {
            return back()->withErrors([
                'time' => 'El horario seleccionado no coincide con la fecha y zona de la ubicación.',
            ]);
        }
        $idempotencyKey = (string) ($validated['idempotency_key']
            ?? $request->header('Idempotency-Key')
            ?? hash('sha256', implode('|', [
                (string) $tenant->id,
                (string) $service->id,
                (string) $validated['service_provider_id'],
                (string) $location->id,
                $scheduledAt->toIso8601String(),
                mb_strtolower(trim($validated['customer_email'])),
            ])));
        $appointment = $this->reservations->create(new CreateReservationCommand(
            tenantId: (string) $tenant->id,
            actorId: null,
            idempotencyKey: $idempotencyKey,
            serviceId: (int) $service->id,
            providerId: (int) $validated['service_provider_id'],
            locationId: (string) $location->id,
            startsAtUtc: $scheduledAt,
            customerName: $validated['customer_name'],
            customerEmail: $validated['customer_email'],
            customerPhone: $validated['customer_phone'],
            customerNotes: $validated['customer_notes'] ?? null,
            source: 'public_booking',
            publiclyBookableOnly: true,
        ))->appointment;

        return redirect()->route('public.booking.success', [
            'tenant' => $tenant->slug,
            'appointment' => $appointment->id,
        ]);
    }

    /**
     * Display booking success page.
     *
     * GET /{tenant:slug}/book/success
     */
    public function success(Request $request): Response
    {
        $tenant = $this->resolveTenant();
        $appointmentId = $request->query('appointment');
        $appointment = null;

        if ($appointmentId) {
            $appointment = Appointment::with(['service', 'provider'])
                ->where('tenant_id', $tenant->id)
                ->find($appointmentId);
        }

        $appointmentPayload = null;
        if ($appointment instanceof Appointment) {
            $timezone = (string) ($appointment->timezone ?: 'UTC');
            $scheduledAtUtc = CarbonImmutable::parse(
                (string) ($appointment->getRawOriginal('scheduled_at') ?: $appointment->scheduled_at),
                'UTC',
            )->utc();
            $scheduledAtLocal = $scheduledAtUtc->setTimezone($timezone);
            $appointmentPayload = [
                'id' => $appointment->id,
                'service_name' => $appointment->service_name_snapshot ?: $appointment->service->name,
                'provider_name' => $appointment->provider->name,
                'status' => (string) $appointment->status,
                'timezone' => $timezone,
                'local_date' => $appointment->local_date?->format('Y-m-d') ?: $scheduledAtLocal->format('Y-m-d'),
                'scheduled_at' => $scheduledAtLocal->format('l, d M Y'),
                'time' => $scheduledAtLocal->format('h:i A'),
                'customer_name' => $appointment->customer_name,
            ];
        }

        return Inertia::render('Public/Book/Success', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'appointment' => $appointmentPayload,
        ]);
    }

    private function resolveBookingLocation(
        Tenant $tenant,
        Service $service,
        mixed $requestedLocationId,
    ): Location {
        $locations = Location::query()
            ->withoutGlobalScopes()
            ->join('service_locations', 'service_locations.location_id', '=', 'locations.id')
            ->where('locations.tenant_id', $tenant->id)
            ->where('service_locations.tenant_id', $tenant->id)
            ->where('service_locations.service_id', $service->id)
            ->where('locations.is_active', true)
            ->when(
                is_string($requestedLocationId) && $requestedLocationId !== '',
                fn ($query) => $query->where('locations.id', $requestedLocationId),
            )
            ->orderByDesc('locations.is_primary')
            ->orderBy('locations.created_at')
            ->select('locations.*')
            ->get();
        if ($locations->count() === 1) {
            return $locations->first();
        }
        $primary = $locations->filter(fn (Location $location): bool => $location->is_primary);
        if ($primary->count() === 1) {
            return $primary->first();
        }

        throw ValidationException::withMessages([
            'location_id' => ['Selecciona una ubicación explícita para reservar este servicio.'],
        ]);
    }

    private function resolveTenant(): Tenant
    {
        $tenant = app('current_tenant');

        if (! $tenant instanceof Tenant) {
            abort(404, 'Business not found');
        }

        // PUBLIC_READ_WITH_TENANT_FILTER: intentional TenantScope bypass; caller must keep an explicit tenant or local-intent filter.
        return Tenant::query()->withoutGlobalScopes()->findOrFail($tenant->id);
    }

    private function tenantLogoUrl(Tenant $tenant): ?string
    {
        return $tenant->logo_path ? Storage::url($tenant->logo_path) : null;
    }
}
