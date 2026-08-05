<?php

declare(strict_types=1);

namespace App\Application\Reservations;

// @withoutGlobalScopes-safety CANONICAL_WRITER_TENANT_FILTER: every bypass is constrained by command tenant ownership.

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Application\Availability\AvailabilityQuery;
use App\Application\Markets\TenantMarketResolver;
use App\Application\Reservations\Commands\CreateReservationCommand;
use App\Application\Reservations\Commands\TransitionReservationCommand;
use App\Domain\Access\Enums\Permission;
use App\Domain\Operations\Events\BusinessOperationChanged;
use App\Domain\Shared\OutboxRepositoryInterface;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Appointment;
use App\Models\Location;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Authoritative transaction owner for reservation creation and lifecycle changes.
 */
final readonly class CanonicalReservationWriter
{
    public function __construct(
        private AvailabilityQuery $availability,
        private TenantMarketResolver $marketResolver,
        private TenantPermissionGuardInterface $permissionGuard,
        private OutboxRepositoryInterface $outbox,
    ) {}

    public function create(CreateReservationCommand $command): ReservationWriteResult
    {
        $this->assertCreateCommand($command);

        return DB::transaction(function () use ($command): ReservationWriteResult {
            $tenant = Tenant::query()
                ->withoutGlobalScopes()
                ->whereKey($command->tenantId)
                ->where('is_active', true)
                ->when(
                    $command->publiclyBookableOnly,
                    fn ($query) => $query->whereNull('publication_paused_at'),
                )
                ->lockForUpdate()
                ->firstOrFail();
            $actor = $command->actorId === null
                ? null
                : $this->authorize($tenant, $command->actorId);
            $payloadHash = $this->hash($command);
            $existing = Appointment::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $command->tenantId)
                ->where('idempotency_key', $command->idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof Appointment) {
                if (! hash_equals((string) $existing->idempotency_hash, $payloadHash)) {
                    throw ValidationException::withMessages([
                        'idempotency_key' => ['Reservation idempotency key was reused with another payload.'],
                    ]);
                }

                return new ReservationWriteResult($existing, true);
            }

            $service = Service::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $command->tenantId)
                ->whereKey($command->serviceId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();
            $provider = ServiceProvider::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $command->tenantId)
                ->whereKey($command->providerId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();
            $location = Location::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $command->tenantId)
                ->whereKey($command->locationId)
                ->where('is_active', true)
                ->lockForUpdate()
                ->firstOrFail();
            $this->assertCatalogLinks($command);
            $market = $this->marketResolver->resolve($tenant);
            if (strtoupper((string) $service->currency) !== strtoupper($market->currencyCode)
                || strtoupper((string) $location->currency_code) !== strtoupper($market->currencyCode)) {
                throw ValidationException::withMessages([
                    'currency' => ['Reservation catalog currency does not match the tenant market.'],
                ]);
            }
            $startsAtUtc = $command->startsAtUtc->utc();
            $endsAtUtc = $startsAtUtc->addMinutes((int) $service->duration_minutes);
            Appointment::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $command->tenantId)
                ->where('service_provider_id', $command->providerId)
                ->whereIn('status', [Appointment::STATUS_PENDING, Appointment::STATUS_CONFIRMED])
                ->where('scheduled_at', '<', $endsAtUtc)
                ->where('end_at', '>', $startsAtUtc)
                ->lockForUpdate()
                ->get(['id']);
            $decision = $this->availability->assertAvailable(
                $command->tenantId,
                $command->serviceId,
                $command->providerId,
                $command->locationId,
                $startsAtUtc,
                $endsAtUtc,
            );
            if (! $decision->available) {
                throw ValidationException::withMessages([
                    'starts_at_utc' => ["Reservation is unavailable: {$decision->reason}."],
                ]);
            }
            $user = $command->userId === null
                ? null
                : User::query()
                    ->withoutGlobalScopes()
                    ->where('tenant_id', $command->tenantId)
                    ->findOrFail($command->userId);

            $appointment = new Appointment;
            $appointment->forceFill([
                'tenant_id' => $command->tenantId,
                'user_id' => $user?->getKey(),
                'service_id' => $service->getKey(),
                'service_provider_id' => $provider->getKey(),
                'location_id' => $location->getKey(),
                'service_name_snapshot' => (string) $service->name,
                'duration_minutes' => (int) $service->duration_minutes,
                'currency' => strtoupper((string) $service->currency),
                'price_minor' => (int) $service->price_minor,
                'timezone' => (string) $location->timezone_name,
                'local_date' => $startsAtUtc->setTimezone((string) $location->timezone_name)->format('Y-m-d'),
                'idempotency_key' => $command->idempotencyKey,
                'idempotency_hash' => $payloadHash,
                'source' => $command->source,
                'scheduled_at' => $startsAtUtc,
                'end_at' => $endsAtUtc,
                'status' => Appointment::STATUS_PENDING,
                'customer_name' => trim($command->customerName),
                'customer_email' => mb_strtolower(trim($command->customerEmail)),
                'customer_phone' => trim($command->customerPhone),
                'customer_notes' => $command->customerNotes,
            ])->save();
            $this->audit($actor, $appointment, 'reservation.created', [
                'tenant_id' => $command->tenantId,
                'status' => Appointment::STATUS_PENDING,
                'currency' => strtoupper((string) $service->currency),
            ]);

            return new ReservationWriteResult($appointment);
        }, 3);
    }

    public function transition(TransitionReservationCommand $command): ReservationWriteResult
    {
        $this->assertTransitionCommand($command);

        return DB::transaction(function () use ($command): ReservationWriteResult {
            $appointment = Appointment::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $command->tenantId)
                ->lockForUpdate()
                ->findOrFail($command->appointmentId);
            $tenant = Tenant::query()->withoutGlobalScopes()->findOrFail($command->tenantId);
            $actor = $command->actorId === null
                ? null
                : $this->authorize($tenant, $command->actorId);
            if ($command->targetStatus === Appointment::STATUS_CANCELLED) {
                return $this->cancelWithinTransaction($appointment, $actor, $command);
            }
            $this->assertAllowedTransition((string) $appointment->status, $command->targetStatus);
            if ($command->targetStatus === Appointment::STATUS_NO_SHOW
                && trim((string) $command->reason) === '') {
                throw ValidationException::withMessages([
                    'reason' => ['A no-show reason is required.'],
                ]);
            }
            $attributes = ['status' => $command->targetStatus];
            match ($command->targetStatus) {
                Appointment::STATUS_CONFIRMED => $attributes['confirmed_at'] = now(),
                Appointment::STATUS_COMPLETED => $attributes['completed_at'] = now(),
                Appointment::STATUS_NO_SHOW => $attributes['no_show_at'] = now(),
                default => null,
            };
            if ($command->reason !== null) {
                $attributes['status_reason'] = trim($command->reason);
            }
            $appointment->forceFill($attributes)->save();
            $this->audit($actor, $appointment, 'reservation.transitioned', [
                'tenant_id' => $command->tenantId,
                'status' => $command->targetStatus,
            ]);

            return new ReservationWriteResult($appointment);
        }, 3);
    }

    public function cancel(TransitionReservationCommand $command): ReservationWriteResult
    {
        if ($command->targetStatus !== Appointment::STATUS_CANCELLED) {
            throw new \InvalidArgumentException('Reservation cancellation requires the cancelled target status.');
        }

        return $this->transition($command);
    }

    private function cancelWithinTransaction(
        Appointment $appointment,
        ?User $actor,
        TransitionReservationCommand $command,
    ): ReservationWriteResult {
        if ($appointment->status === Appointment::STATUS_CANCELLED) {
            return new ReservationWriteResult($appointment, true);
        }
        $this->assertAllowedTransition((string) $appointment->status, Appointment::STATUS_CANCELLED);
        if (trim((string) $command->reason) === '') {
            throw ValidationException::withMessages([
                'reason' => ['Cancellation reason is required.'],
            ]);
        }
        $appointment->forceFill([
            'status' => Appointment::STATUS_CANCELLED,
            'status_reason' => trim((string) $command->reason),
            'cancelled_at' => now(),
        ])->save();
        $this->audit($actor, $appointment, 'reservation.cancelled', [
            'tenant_id' => $command->tenantId,
            'status' => Appointment::STATUS_CANCELLED,
            'actor_type' => $command->guestInitiated ? 'guest' : 'user',
        ]);

        return new ReservationWriteResult($appointment);
    }

    private function authorize(Tenant $tenant, string $actorId): User
    {
        $actor = User::query()->withoutGlobalScopes()->findOrFail($actorId);
        if (! $this->permissionGuard->allows(
            $actor,
            $tenant,
            Capability::APPOINTMENT_BOOKING,
            Permission::APPOINTMENTS_MANAGE,
        )) {
            throw new AuthorizationException('Actor cannot manage reservations for this tenant.');
        }

        return $actor;
    }

    private function assertCatalogLinks(CreateReservationCommand $command): void
    {
        if (! DB::table('service_locations')
            ->where('tenant_id', $command->tenantId)
            ->where('service_id', $command->serviceId)
            ->where('location_id', $command->locationId)
            ->exists()) {
            $this->notFound(Service::class, $command->serviceId);
        }
        if (! DB::table('service_provider_locations')
            ->where('tenant_id', $command->tenantId)
            ->where('service_provider_id', $command->providerId)
            ->where('location_id', $command->locationId)
            ->exists()) {
            $this->notFound(ServiceProvider::class, $command->providerId);
        }
    }

    private function assertCreateCommand(CreateReservationCommand $command): void
    {
        if (! Str::isUuid($command->tenantId)
            || ($command->actorId !== null && ! Str::isUuid($command->actorId))
            || ($command->userId !== null && ! Str::isUuid($command->userId))
            || ! Str::isUuid($command->locationId)
            || $command->serviceId < 1
            || $command->providerId < 1
            || trim($command->idempotencyKey) === ''
            || mb_strlen($command->idempotencyKey) > 160
            || trim($command->customerName) === ''
            || filter_var($command->customerEmail, FILTER_VALIDATE_EMAIL) === false
            || trim($command->customerPhone) === '') {
            throw new \InvalidArgumentException('Reservation creation requires valid tenant, catalog, time, customer, and idempotency data.');
        }
    }

    private function assertTransitionCommand(TransitionReservationCommand $command): void
    {
        $statuses = [
            Appointment::STATUS_PENDING,
            Appointment::STATUS_CONFIRMED,
            Appointment::STATUS_COMPLETED,
            Appointment::STATUS_CANCELLED,
            Appointment::STATUS_NO_SHOW,
        ];
        if (! Str::isUuid($command->tenantId)
            || ($command->actorId !== null && ! Str::isUuid($command->actorId))
            || ($command->actorId === null
                && ! ($command->guestInitiated && $command->targetStatus === Appointment::STATUS_CANCELLED))
            || $command->appointmentId < 1
            || ! in_array($command->targetStatus, $statuses, true)) {
            throw new \InvalidArgumentException('Reservation transition requires valid tenant, authority, appointment, and status data.');
        }
    }

    private function assertAllowedTransition(string $from, string $to): void
    {
        $allowed = [
            Appointment::STATUS_PENDING => [
                Appointment::STATUS_CONFIRMED,
                Appointment::STATUS_CANCELLED,
            ],
            Appointment::STATUS_CONFIRMED => [
                Appointment::STATUS_COMPLETED,
                Appointment::STATUS_CANCELLED,
                Appointment::STATUS_NO_SHOW,
            ],
            Appointment::STATUS_COMPLETED => [],
            Appointment::STATUS_CANCELLED => [],
            Appointment::STATUS_NO_SHOW => [],
        ];
        if (! in_array($to, $allowed[$from] ?? [], true)) {
            throw ValidationException::withMessages([
                'status' => ["Reservation cannot transition from {$from} to {$to}."],
            ]);
        }
    }

    private function hash(CreateReservationCommand $command): string
    {
        return hash('sha256', json_encode([
            'tenant_id' => $command->tenantId,
            'service_id' => $command->serviceId,
            'provider_id' => $command->providerId,
            'location_id' => $command->locationId,
            'starts_at_utc' => $command->startsAtUtc->utc()->toIso8601String(),
            'customer_name' => trim($command->customerName),
            'customer_email' => mb_strtolower(trim($command->customerEmail)),
            'customer_phone' => trim($command->customerPhone),
            'customer_notes' => $command->customerNotes,
            'user_id' => $command->userId,
            'source' => $command->source,
            'publicly_bookable_only' => $command->publiclyBookableOnly,
        ], JSON_THROW_ON_ERROR));
    }

    /** @param array<string, scalar|null> $properties */
    private function audit(
        ?User $actor,
        Appointment $appointment,
        string $event,
        array $properties,
    ): void {
        $activity = activity('business-operations')
            ->performedOn($appointment)
            ->withProperties($properties)
            ->event($event);
        if ($actor instanceof User) {
            $activity->causedBy($actor);
        }
        $activity->log($event);
        $this->outbox->append(new BusinessOperationChanged(
            aggregateId: 'appointment:'.$appointment->getKey(),
            aggregateType: 'appointment',
            tenantId: (string) ($properties['tenant_id'] ?? ''),
            actorId: $actor instanceof User ? (string) $actor->getKey() : 'guest',
            eventName: $event,
            attributes: $properties,
            occurredAt: now()->toImmutable(),
        ));
    }

    private function notFound(string $model, int|string $id): never
    {
        $exception = new ModelNotFoundException;
        $exception->setModel($model, [$id]);

        throw $exception;
    }
}
