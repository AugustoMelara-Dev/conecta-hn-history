<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Application\Shared\Contracts\AppointmentRepositoryInterface;
use App\Application\Shared\Contracts\CommandBusInterface;
use App\Application\Shared\Contracts\ServiceProviderRepositoryInterface;
use App\Application\Shared\Contracts\ServiceRepositoryInterface;
use App\Application\Tenant\Commands\CreateAppointmentCommand;
use App\Domain\Shared\Services\TenantContext;
use App\Domain\Tenant\Services\TenantVisibilityService;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Infrastructure\Observability\DomainTelemetry;
use App\Models\Appointment;
use App\Models\Tenant;
use App\Support\Diagnostics\SupportDiagnosticFactory;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
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
        private \App\Domain\Booking\Services\SlotCalculator $slotCalculator,
        private DomainTelemetry $telemetry,
        private SupportDiagnosticFactory $diagnostics,
        private TenantVisibilityService $visibility,
    ) {}

    /**
     * Display the booking wizard page.
     *
     * GET /{tenant:slug}/book
     */
    public function index(Request $request, string $tenantSlug): Response
    {
        if (! $this->initializeTenantContext($tenantSlug)) {
            abort(404, 'Tenant not found.');
        }

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
        $preselectedServiceId = (string) $request->input('service', '');
        $validPreselectedServiceId = collect($services)
            ->contains(fn (array $service): bool => (string) $service['id'] === $preselectedServiceId)
            ? $preselectedServiceId
            : null;

        return Inertia::render('Public/Book/Index', [
            'tenant' => [
                'id' => $tenant->id(),
                'name' => $tenant->name(),
                'slug' => $tenant->slug(),
                'logo_url' => null,
                'primary_color' => '#000000',
                'description' => $tenant->description(),
                'whatsapp' => $tenant->whatsappNumber(),
            ],
            'services' => $services,
            'providers' => $providers,
            'preselectedServiceId' => $validPreselectedServiceId,
        ]);
    }

    /**
     * Get available providers for a specific service.
     *
     * GET /{tenant:slug}/book/providers/{service}
     */
    public function getProviders(string $tenantSlug, string $serviceId): JsonResponse
    {
        if (! $this->initializeTenantContext($tenantSlug)) {
            abort(404, 'Tenant not found.');
        }

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
        $startedAt = microtime(true);

        if (! $this->initializeTenantContext($tenantSlug)) {
            abort(404, 'Tenant not found.');
        }

        $tenant = $this->tenantRepository->findBySlug($tenantSlug);
        if (! $tenant) {
            abort(404, 'Tenant not found.');
        }

        $slotsRateLimitKey = sprintf('booking:slots:%s:%s', $tenant->id(), (string) $request->ip());
        if (RateLimiter::tooManyAttempts($slotsRateLimitKey, 90)) {
            return response()->json([
                'message' => 'Demasiadas consultas de horarios. Espera un momento e intenta de nuevo.',
                'diagnostic' => $this->diagnostics->make(
                    'booking',
                    'Consulta de horarios limitada',
                    'Se detectaron demasiadas consultas de disponibilidad para este negocio desde la misma conexion.',
                    'Espera un minuto y vuelve a elegir fecha u horario. Si persiste, comparte esta referencia con soporte.'
                ),
            ], 429);
        }
        RateLimiter::hit($slotsRateLimitKey, 60);

        $validated = $request->validate([
            'date' => 'required|date|after_or_equal:today',
            'service_id' => 'required|integer',
            'provider_id' => 'nullable|integer',
        ]);

        $date = Carbon::parse($validated['date']);
        $service = $this->serviceRepository->findById($validated['service_id']);

        if (! $service) {
            abort(404, 'Service not found.');
        }

        if ((string) $service->tenant_id !== $tenant->id()) {
            abort(404, 'Service not found');
        }

        $providerId = isset($validated['provider_id']) ? (string) $validated['provider_id'] : null;
        if ($providerId !== null) {
            $provider = $this->providerRepository->findById($providerId);

            if (! $provider || (string) $provider->tenant_id !== $tenant->id()) {
                abort(404, 'Provider not found');
            }
        }

        $slots = $this->slotCalculator->calculate(
            $tenant->id(),
            $date,
            $service->duration_minutes,
            $providerId,
            $service->buffer_before,
            $service->buffer_after
        );
        $timeline = $this->slotCalculator->calculateTimeline(
            $tenant->id(),
            $date,
            $service->duration_minutes,
            $providerId,
            $service->buffer_before,
            $service->buffer_after
        );
        $durationMs = round((microtime(true) - $startedAt) * 1000, 2);
        $this->telemetry->record('booking', 'booking_slots_retrieved', [
            'tenant_id' => $tenant->id(),
            'service_id' => $service->id,
            'provider_id' => $providerId,
            'status' => 'ok',
            'slot_count' => count($slots),
        ]);
        $this->telemetry->gauge('booking', 'conecta.booking.slot_lookup.duration_ms', $durationMs, [
            'service_id' => (string) $service->id,
            'provider_id' => $providerId ?? 'unassigned',
        ], [
            'tenant_id' => $tenant->id(),
            'slot_count' => count($slots),
        ]);

        return response()->json([
            'slots' => $slots,
            'timeline' => $timeline,
        ]);
    }

    /**
     * Create a new appointment.
     *
     * POST /{tenant:slug}/book
     */
    public function store(Request $request, string $tenantSlug): RedirectResponse
    {
        if (! $this->initializeTenantContext($tenantSlug)) {
            abort(404, 'Tenant not found.');
        }

        $tenant = $this->tenantRepository->findBySlug($tenantSlug);
        if (! $tenant) {
            abort(404, 'Tenant not found.');
        }

        $bookingRateLimitKey = sprintf('booking:store:%s:%s', $tenant->id(), (string) $request->ip());
        if (RateLimiter::tooManyAttempts($bookingRateLimitKey, 8)) {
            return back()->withErrors([
                'customer_email' => 'Demasiados intentos de reserva en poco tiempo. Espera un minuto antes de reintentar.',
            ])->with('diagnostic', $this->diagnostics->make(
                'booking',
                'Reserva limitada por seguridad',
                'Se recibieron demasiados intentos de crear reserva para este negocio desde la misma conexion.',
                'Espera un minuto antes de reintentar. Si el cliente no puede reservar, comparte esta referencia con soporte.'
            ));
        }
        RateLimiter::hit($bookingRateLimitKey, 60);

        $validated = $request->validate([
            'service_id' => 'required|integer',
            'service_provider_id' => 'nullable|integer',
            'date' => 'required|date|after_or_equal:today',
            'time' => 'required|date_format:H:i',
            'customer_name' => ['required', 'string', 'max:255', 'regex:/.*\S.*/'],
            'customer_email' => 'required|email|max:255',
            'customer_phone' => ['required', 'string', 'max:20', 'regex:/^[\d\s+\-()]{7,20}$/'],
            'customer_notes' => 'nullable|string|max:500',
            'contact_consent' => 'accepted',
        ]);

        $service = $this->serviceRepository->findById($validated['service_id']);

        if (! $service) {
            abort(404, 'Service not found.');
        }

        if ((string) $service->tenant_id !== $tenant->id()) {
            abort(404, 'Service not found');
        }

        $scheduledAt = Carbon::parse($validated['date'].' '.$validated['time']);
        $endAt = $scheduledAt->copy()->addMinutes($service->duration_minutes);
        $provider = $this->resolveProviderForBooking(
            tenantId: $tenant->id(),
            requestedProviderId: isset($validated['service_provider_id']) ? (string) $validated['service_provider_id'] : null,
            scheduledAt: $scheduledAt,
            endAt: $endAt,
            bufferBefore: $service->buffer_before,
            bufferAfter: $service->buffer_after
        );

        if (! $provider) {
            return back()->withErrors([
                'service_provider_id' => 'No encontramos una persona disponible para ese horario. Elige otro horario o intenta mas tarde.',
            ])->with('diagnostic', $this->diagnostics->make(
                'booking',
                'Sin disponibilidad para atender',
                'El flujo permitio avanzar sin preferencia, pero no hay proveedor activo disponible para ese bloque.',
                'Elige otro horario o revisa disponibilidad del equipo en el panel del negocio.'
            ));
        }

        if ($this->hasExcessivePendingBookings(
            tenantId: $tenant->id(),
            customerEmail: (string) $validated['customer_email'],
            customerPhone: (string) $validated['customer_phone'],
            appointmentDate: $scheduledAt
        )) {
            return back()->withErrors([
                'customer_email' => 'Detectamos demasiadas reservas activas con este contacto para hoy. Confirma o cancela una reserva anterior antes de crear otra.',
            ])->with('diagnostic', $this->diagnostics->make(
                'booking',
                'Reserva duplicada detenida',
                'El contacto ya tiene demasiadas reservas activas para este dia y se bloqueo otra reserva preventiva.',
                'Confirma una reserva anterior o contacta al negocio. Si es un falso positivo, comparte esta referencia.'
            ));
        }

        $bookingResult = DB::transaction(function () use ($tenant, $service, $provider, $scheduledAt, $endAt, $validated): array {
            DB::table('service_providers')
                ->where('id', $provider->id)
                ->lockForUpdate()
                ->first();

            if (! $this->providerRepository->isAvailableAt(
                $provider->id,
                $scheduledAt,
                $endAt,
                $service->buffer_before,
                $service->buffer_after
            )) {
                return ['status' => 'conflict'];
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

            return [
                'status' => 'created',
                'result' => $this->commandBus->dispatch($command),
            ];
        }, 3);

        if (($bookingResult['status'] ?? null) === 'conflict') {
            $this->telemetry->record('booking', 'booking_slot_conflict_detected', [
                'tenant_id' => $tenant->id(),
                'service_id' => $service->id,
                'provider_id' => $provider->id,
                'status' => 'conflict',
            ], 'warning');

            return back()->withErrors([
                'time' => 'Este horario ya no esta disponible. Por favor selecciona otro.',
            ])->with('diagnostic', $this->diagnostics->make(
                'booking',
                'Horario tomado por otra reserva',
                'Otro cliente o el equipo del negocio ocupo el bloque mientras se completaba el formulario.',
                'Selecciona otro horario disponible. Si ocurre varias veces, comparte esta referencia con soporte.'
            ));
        }

        /** @var array{id:string|int} $result */
        $result = $bookingResult['result'];
        $this->telemetry->record('booking', 'booking_appointment_created', [
            'tenant_id' => $tenant->id(),
            'service_id' => $service->id,
            'provider_id' => $provider->id,
            'status' => 'created',
            'appointment_id' => $result['id'] ?? null,
        ]);

        return redirect()->to(URL::signedRoute('public.booking.success', [
            'tenant' => $tenant->slug(),
            'appointment' => $result['id'],
        ]));
    }

    /**
     * Display booking success page.
     *
     * GET /{tenant:slug}/book/success
     */
    public function success(string $tenantSlug, Request $request): Response
    {
        if (! $this->initializeTenantContext($tenantSlug)) {
            abort(404, 'Tenant not found.');
        }

        $tenant = $this->tenantRepository->findBySlug($tenantSlug);
        if (! $tenant) {
            abort(404, 'Tenant not found.');
        }

        $appointmentId = $request->query('appointment');
        $appointment = null;

        if ($appointmentId) {
            abort_unless($request->hasValidSignature(), 403);

            $appointment = $this->appointmentRepository->findWithRelations($appointmentId);

            if ($appointment === null || (string) $appointment->tenant_id !== $tenant->id()) {
                abort(404);
            }
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
            'diagnostic' => $this->diagnostics->make(
                'booking',
                $appointment ? 'Reserva recibida' : 'Reserva recibida sin detalle firmado',
                $appointment ? 'La cita fue creada y queda pendiente de confirmacion del negocio.' : 'El enlace no incluye una cita firmada visible.',
                $appointment ? 'Guarda esta pantalla y espera confirmacion por el canal indicado.' : 'Si necesitas soporte, comparte esta referencia y el negocio.'
            ),
        ]);
    }

    private function initializeTenantContext(string $tenantSlug): ?Tenant
    {
        $tenant = Tenant::query()
            ->withoutGlobalScopes()
            ->select([
                'id',
                'slug',
                'status',
                'is_active',
                'subscription_status',
                'verification_status',
                'publication_paused_at',
            ])
            ->where('slug', $tenantSlug)
            ->first();

        if ($tenant === null) {
            return null;
        }

        $this->visibility->check($tenant);

        app(TenantContext::class)->setTenant((string) $tenant->id);

        return $tenant;
    }

    private function hasExcessivePendingBookings(
        string $tenantId,
        string $customerEmail,
        string $customerPhone,
        Carbon $appointmentDate
    ): bool {
        $maxPerContactPerDay = max(1, (int) config('conecta.booking.max_pending_per_contact_per_day', 3));

        $normalizedEmail = strtolower(trim($customerEmail));
        $normalizedPhone = preg_replace('/\D+/', '', $customerPhone) ?? '';

        $activeStatuses = [
            Appointment::STATUS_PENDING,
            Appointment::STATUS_CONFIRMED,
        ];

        $query = Appointment::query()
            ->where('tenant_id', $tenantId)
            ->whereDate('scheduled_at', $appointmentDate->toDateString())
            ->whereIn('status', $activeStatuses);

        $matches = $query->where(function ($builder) use ($normalizedEmail, $normalizedPhone): void {
            $builder->whereRaw('LOWER(customer_email) = ?', [$normalizedEmail]);

            if ($normalizedPhone !== '') {
                $builder->orWhereRaw(
                    "REPLACE(REPLACE(REPLACE(REPLACE(customer_phone, ' ', ''), '-', ''), '(', ''), ')', '') = ?",
                    [$normalizedPhone]
                );
            }
        })->count();

        return $matches >= $maxPerContactPerDay;
    }

    private function resolveProviderForBooking(
        string $tenantId,
        ?string $requestedProviderId,
        Carbon $scheduledAt,
        Carbon $endAt,
        int $bufferBefore,
        int $bufferAfter
    ): ?object {
        if ($requestedProviderId !== null && $requestedProviderId !== '') {
            $provider = $this->providerRepository->findById($requestedProviderId);

            if (! $provider || (string) $provider->tenant_id !== $tenantId) {
                abort(404, 'Provider not found');
            }

            return $this->providerRepository->isAvailableAt($provider->id, $scheduledAt, $endAt, $bufferBefore, $bufferAfter)
                ? $provider
                : null;
        }

        foreach ($this->providerRepository->getActiveForTenant($tenantId) as $candidate) {
            $candidateId = (string) ($candidate['id'] ?? '');
            if ($candidateId === '') {
                continue;
            }

            $provider = $this->providerRepository->findById($candidateId);
            if (! $provider || (string) $provider->tenant_id !== $tenantId) {
                continue;
            }

            if ($this->providerRepository->isAvailableAt($provider->id, $scheduledAt, $endAt, $bufferBefore, $bufferAfter)) {
                return $provider;
            }
        }

        return null;
    }
}
