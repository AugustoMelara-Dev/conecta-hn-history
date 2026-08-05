<?php

declare(strict_types=1);

namespace App\Filament\App\Resources\AppointmentResource\Pages;

// @withoutGlobalScopes-safety INFRA_TENANT_FILTER: the canonical command receives the authenticated Filament tenant.

use App\Application\Reservations\CanonicalReservationWriter;
use App\Application\Reservations\Commands\CreateReservationCommand;
use App\Application\Reservations\Commands\TransitionReservationCommand;
use App\Filament\App\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Location;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAppointment extends CreateRecord
{
    protected static string $resource = AppointmentResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $tenant = Filament::getTenant();
        $actor = auth()->user();
        if (! $tenant instanceof Tenant || ! $actor instanceof User) {
            throw new \RuntimeException('Authenticated tenant context is required.');
        }
        $location = Location::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->findOrFail($data['location_id']);
        $writer = app(CanonicalReservationWriter::class);
        $idempotencyKey = 'filament-reservation:'.hash('sha256', json_encode([
            'tenant_id' => (string) $tenant->getKey(),
            'actor_id' => (string) $actor->getKey(),
            'service_id' => (int) $data['service_id'],
            'provider_id' => (int) $data['service_provider_id'],
            'location_id' => (string) $location->getKey(),
            'scheduled_at' => (string) $data['scheduled_at'],
            'customer_email' => mb_strtolower(trim((string) $data['customer_email'])),
            'customer_phone' => trim((string) $data['customer_phone']),
        ], JSON_THROW_ON_ERROR));
        $result = $writer->create(new CreateReservationCommand(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            idempotencyKey: $idempotencyKey,
            serviceId: (int) $data['service_id'],
            providerId: (int) $data['service_provider_id'],
            locationId: (string) $location->getKey(),
            startsAtUtc: CarbonImmutable::parse(
                (string) $data['scheduled_at'],
                (string) $location->timezone_name,
            )->utc(),
            customerName: (string) $data['customer_name'],
            customerEmail: (string) $data['customer_email'],
            customerPhone: (string) $data['customer_phone'],
            customerNotes: $data['customer_notes'] ?? null,
            userId: filled($data['user_id'] ?? null) ? (string) $data['user_id'] : null,
            source: 'filament',
        ));
        if (($data['status'] ?? Appointment::STATUS_PENDING) === Appointment::STATUS_CONFIRMED) {
            return $writer->transition(new TransitionReservationCommand(
                tenantId: (string) $tenant->getKey(),
                actorId: (string) $actor->getKey(),
                appointmentId: (int) $result->appointment->getKey(),
                targetStatus: Appointment::STATUS_CONFIRMED,
            ))->appointment;
        }

        return $result->appointment;
    }
}
