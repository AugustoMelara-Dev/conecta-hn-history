<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Application\Shared\Contracts\AppointmentRepositoryInterface;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\ServiceProviderRepositoryInterface;
use App\Application\Shared\Contracts\ServiceRepositoryInterface;
use App\Application\Tenant\Commands\CreateAppointmentCommand;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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
final class BookingController extends Controller
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository,
        private ServiceRepositoryInterface $serviceRepository,
        private ServiceProviderRepositoryInterface $providerRepository,
        private AppointmentRepositoryInterface $appointmentRepository,
        private CommandBusInterface $commandBus,
        private \App\Domain\Booking\Services\SlotCalculator $slotCalculator
    ) {}

    /**
     * Display the booking wizard page.
     *
     * GET /{tenant:slug}/book
     */
    public function index(string $tenantSlug): Response
    {
        $tenant = $this->tenantRepository->findBySlug($tenantSlug);
        if (! $tenant) {
            abort(404, 'Tenant not found.');
        }

        $services = collect($this->serviceRepository->getActiveForTenant($tenant->id()))
            ->map(fn (array $s) => [
                'id' => $s['id'],
                'name' => $s['name'],
                'description' => $s['description'],
                'price' => $s['formatted_price'],
                'duration_minutes' => $s['duration_minutes'],
                'duration_formatted' => $s['formatted_duration'],
            ])
            ->values()
            ->all();

        $providers = $this->providerRepository->getActiveForTenant($tenant->id());

        return Inertia::render('Public/Book/Index', [
            'tenant' => [
                'id' => $tenant->id(),
                'name' => $tenant->name(),
                'slug' => $tenant->slug(),
                'logo_url' => null,
                'primary_color' => '#000000',
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
    public function getProviders(string $tenantSlug, string $serviceId): JsonResponse
    {
        $tenant = $this->tenantRepository->findBySlug($tenantSlug);
        $service = $this->serviceRepository->findById($serviceId);

        if (! $tenant || ! $service) {
            abort(404, 'Tenant or Service not found.');
        }

        if ((string) $service->tenant_id !== $tenant->id()) {
            abort(404, 'Service not found');
        }

        $providers = $this->providerRepository->getActiveForTenant($tenant->id());

        return response()->json([
            'providers' => $providers,
        ]);
    }

    /**
     * Get available time slots for a specific date.
     *
     * GET /{tenant:slug}/book/slots
     */
    public function getSlots(Request $request, string $tenantSlug): JsonResponse
    {
        $tenant = $this->tenantRepository->findBySlug($tenantSlug);
        if (! $tenant) {
            abort(404, 'Tenant not found.');
        }

        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'service_id' => 'required|string',
            'provider_id' => 'nullable|string',
        ]);

        $date = Carbon::parse($validated['date']);
        $service = $this->serviceRepository->findById($validated['service_id']);

        if (! $service) {
            abort(404, 'Service not found.');
        }

        if ((string) $service->tenant_id !== $tenant->id()) {
            abort(404, 'Service not found');
        }

        $slots = $this->slotCalculator->calculate(
            $tenant->id(),
            $date,
            $service->duration_minutes,
            $validated['provider_id'] ?? null,
            $service->buffer_before,
            $service->buffer_after
        );

        return response()->json(['slots' => $slots]);
    }

    /**
     * Create a new appointment.
     *
     * POST /{tenant:slug}/book
     */
    public function store(Request $request, string $tenantSlug): RedirectResponse
    {
        $tenant = $this->tenantRepository->findBySlug($tenantSlug);
        if (! $tenant) {
            abort(404, 'Tenant not found.');
        }

        $validated = $request->validate([
            'service_id' => 'required|string',
            'service_provider_id' => 'required|string',
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_notes' => 'nullable|string|max:500',
        ]);

        $service = $this->serviceRepository->findById($validated['service_id']);
        $provider = $this->providerRepository->findById($validated['service_provider_id']);

        if (! $service || ! $provider) {
            abort(404, 'Service or Provider not found.');
        }

        if ((string) $service->tenant_id !== $tenant->id() || (string) $provider->tenant_id !== $tenant->id()) {
            abort(403, 'Invalid service or provider');
        }

        $scheduledAt = Carbon::parse($validated['date'].' '.$validated['time']);
        $endAt = $scheduledAt->copy()->addMinutes($service->duration_minutes);

        if (! $this->providerRepository->isAvailableAt(
            $provider->id,
            $scheduledAt,
            $endAt,
            $service->buffer_before,
            $service->buffer_after
        )) {
            return back()->withErrors([
                'time' => 'Este horario ya no esta disponible. Por favor selecciona otro.',
            ]);
        }

        $command = new CreateAppointmentCommand(
            tenantId: $tenant->id(),
            serviceId: $service->id,
            serviceProviderId: $provider->id,
            scheduledAt: $scheduledAt,
            endAt: $endAt,
            customerName: $validated['customer_name'],
            customerEmail: $validated['customer_email'],
            customerPhone: $validated['customer_phone'],
            customerNotes: $validated['customer_notes'] ?? null
        );

        $result = $this->commandBus->dispatch($command);

        return redirect()->route('public.booking.success', [
            'tenant' => $tenant->slug(),
            'appointment' => $result['id'],
        ]);
    }

    /**
     * Display booking success page.
     *
     * GET /{tenant:slug}/book/success
     */
    public function success(string $tenantSlug, Request $request): Response
    {
        $tenant = $this->tenantRepository->findBySlug($tenantSlug);
        if (! $tenant) {
            abort(404, 'Tenant not found.');
        }

        $appointmentId = $request->query('appointment');
        $appointment = null;

        if ($appointmentId) {
            $appointment = $this->appointmentRepository->findWithRelations($appointmentId);
        }

        return Inertia::render('Public/Book/Success', [
            'tenant' => [
                'id' => $tenant->id(),
                'name' => $tenant->name(),
                'slug' => $tenant->slug(),
            ],
            'appointment' => $appointment ? [
                'id' => $appointment->id,
                'service_name' => $appointment->service->name ?? 'Service',
                'provider_name' => $appointment->provider->name ?? 'Provider',
                'scheduled_at' => Carbon::parse($appointment->scheduled_at)->format('l, d M Y'),
                'time' => Carbon::parse($appointment->scheduled_at)->format('h:i A'),
                'customer_name' => $appointment->customer_name,
            ] : null,
        ]);
    }
}
