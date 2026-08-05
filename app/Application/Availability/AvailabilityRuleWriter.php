<?php

declare(strict_types=1);

namespace App\Application\Availability;

// @withoutGlobalScopes-safety CANONICAL_WRITER_TENANT_FILTER: every bypass is constrained by command tenant ownership.

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Application\Availability\Commands\ReplaceAvailabilityRulesCommand;
use App\Domain\Access\Enums\Permission;
use App\Domain\Operations\Events\BusinessOperationChanged;
use App\Domain\Shared\OutboxRepositoryInterface;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class AvailabilityRuleWriter
{
    public function __construct(
        private TenantPermissionGuardInterface $permissionGuard,
        private OutboxRepositoryInterface $outbox,
    ) {}

    public function replace(ReplaceAvailabilityRulesCommand $command): void
    {
        $this->assertIdentifiers($command);
        $rules = $this->normalizedRules($command->rules);
        $exceptions = $this->normalizedExceptions($command->exceptions);
        $payloadHash = $this->hash([
            'location_id' => $command->locationId,
            'service_id' => $command->serviceId,
            'provider_id' => $command->providerId,
            'rules' => $rules,
            'exceptions' => $exceptions,
        ]);

        DB::transaction(function () use ($command, $rules, $exceptions, $payloadHash): void {
            [$tenant, $actor] = $this->authority($command);
            $timezone = $this->validateScope($command);
            $existing = DB::table('availability_write_operations')
                ->where('tenant_id', $command->tenantId)
                ->where('idempotency_key', $command->idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing !== null) {
                if (! hash_equals((string) $existing->payload_hash, $payloadHash)) {
                    throw new \LogicException(
                        'Availability idempotency key was reused with an incompatible payload.',
                    );
                }

                return;
            }

            $operationId = (string) Str::uuid();
            $this->canonicalScopeQuery('availability_rules', $command)->delete();
            $this->canonicalScopeQuery('availability_exceptions', $command)->delete();

            foreach ($rules as $index => $rule) {
                DB::table('availability_rules')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $command->tenantId,
                    'location_id' => $command->locationId,
                    'service_id' => $command->serviceId,
                    'service_provider_id' => $command->providerId,
                    ...$rule,
                    'timezone' => $timezone,
                    'is_active' => true,
                    'source' => 'canonical',
                    'source_key' => "availability:{$operationId}:rule:{$index}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            foreach ($exceptions as $index => $exception) {
                DB::table('availability_exceptions')->insert([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $command->tenantId,
                    'location_id' => $command->locationId,
                    'service_id' => $command->serviceId,
                    'service_provider_id' => $command->providerId,
                    ...$exception,
                    'timezone' => $timezone,
                    'source' => 'canonical',
                    'source_key' => "availability:{$operationId}:exception:{$index}",
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            DB::table('availability_write_operations')->insert([
                'id' => $operationId,
                'tenant_id' => $command->tenantId,
                'location_id' => $command->locationId,
                'service_id' => $command->serviceId,
                'service_provider_id' => $command->providerId,
                'idempotency_key' => $command->idempotencyKey,
                'payload_hash' => $payloadHash,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            activity('business-operations')
                ->performedOn($tenant)
                ->causedBy($actor)
                ->withProperties([
                    'tenant_id' => $command->tenantId,
                    'location_id' => $command->locationId,
                    'service_id' => $command->serviceId,
                    'service_provider_id' => $command->providerId,
                ])
                ->event('availability.replaced')
                ->log('availability.replaced');
            $this->outbox->append(new BusinessOperationChanged(
                aggregateId: 'availability:'.$operationId,
                aggregateType: 'availability',
                tenantId: $command->tenantId,
                actorId: $command->actorId,
                eventName: 'availability.replaced',
                attributes: [
                    'location_id' => $command->locationId,
                    'service_id' => $command->serviceId,
                    'service_provider_id' => $command->providerId,
                ],
                occurredAt: now()->toImmutable(),
            ));
        }, 3);
    }

    /** @return array{Tenant, User} */
    private function authority(ReplaceAvailabilityRulesCommand $command): array
    {
        $tenant = Tenant::query()
            ->withoutGlobalScopes()
            ->whereKey($command->tenantId)
            ->lockForUpdate()
            ->firstOrFail();
        $actor = User::query()->whereKey($command->actorId)->firstOrFail();
        if (! $this->permissionGuard->allows(
            $actor,
            $tenant,
            Capability::APPOINTMENT_BOOKING,
            Permission::STAFF_MANAGE,
        )) {
            throw new AuthorizationException('The actor cannot replace availability.');
        }

        return [$tenant, $actor];
    }

    private function validateScope(ReplaceAvailabilityRulesCommand $command): string
    {
        $location = DB::table('locations')
            ->where('id', $command->locationId)
            ->where('tenant_id', $command->tenantId)
            ->where('is_active', true)
            ->lockForUpdate()
            ->first(['timezone_name']);
        if ($location === null || ! is_string($location->timezone_name)) {
            throw ValidationException::withMessages([
                'location_id' => ['Availability requires an active tenant-owned location.'],
            ]);
        }
        if (! DB::table('timezones')->where('name', $location->timezone_name)->exists()) {
            throw ValidationException::withMessages([
                'location_id' => ['The location timezone is not canonical.'],
            ]);
        }
        if ($command->serviceId !== null
            && ! DB::table('service_locations')
                ->where('tenant_id', $command->tenantId)
                ->where('location_id', $command->locationId)
                ->where('service_id', $command->serviceId)
                ->exists()) {
            throw ValidationException::withMessages([
                'service_id' => ['The service is not assigned to this tenant location.'],
            ]);
        }
        if ($command->providerId !== null
            && ! DB::table('service_provider_locations')
                ->where('tenant_id', $command->tenantId)
                ->where('location_id', $command->locationId)
                ->where('service_provider_id', $command->providerId)
                ->exists()) {
            throw ValidationException::withMessages([
                'provider_id' => ['The provider is not assigned to this tenant location.'],
            ]);
        }

        return $location->timezone_name;
    }

    private function assertIdentifiers(ReplaceAvailabilityRulesCommand $command): void
    {
        if (! Str::isUuid($command->tenantId)
            || ! Str::isUuid($command->actorId)
            || ! Str::isUuid($command->locationId)
            || trim($command->idempotencyKey) === ''
            || strlen($command->idempotencyKey) > 160
            || ($command->serviceId !== null && $command->serviceId < 1)
            || ($command->providerId !== null && $command->providerId < 1)) {
            throw new \InvalidArgumentException('Availability scope identifiers are invalid.');
        }
    }

    /** @param list<array<string, mixed>> $rules @return list<array<string, mixed>> */
    private function normalizedRules(array $rules): array
    {
        $normalized = [];
        foreach ($rules as $rule) {
            if (array_diff(array_keys($rule), [
                'weekday',
                'starts_at_local',
                'ends_at_local',
                'effective_from',
                'effective_until',
            ]) !== []) {
                throw ValidationException::withMessages([
                    'rules' => ['Rules contain unsupported fields.'],
                ]);
            }
            $weekday = filter_var($rule['weekday'] ?? null, FILTER_VALIDATE_INT);
            $startsAt = $this->time($rule['starts_at_local'] ?? null, 'rules');
            $endsAt = $this->time($rule['ends_at_local'] ?? null, 'rules');
            if ($weekday === false || $weekday < 1 || $weekday > 7 || $startsAt >= $endsAt) {
                throw ValidationException::withMessages([
                    'rules' => ['Rules require ISO weekday 1-7 and a positive same-day local interval.'],
                ]);
            }
            $effectiveFrom = $this->date($rule['effective_from'] ?? null, 'rules');
            $effectiveUntil = $this->date($rule['effective_until'] ?? null, 'rules');
            if ($effectiveFrom !== null && $effectiveUntil !== null && $effectiveFrom > $effectiveUntil) {
                throw ValidationException::withMessages([
                    'rules' => ['Rule effective dates are inverted.'],
                ]);
            }
            $normalized[] = [
                'weekday' => (int) $weekday,
                'starts_at_local' => $startsAt,
                'ends_at_local' => $endsAt,
                'effective_from' => $effectiveFrom,
                'effective_until' => $effectiveUntil,
            ];
        }
        usort($normalized, static fn (array $left, array $right): int => [
            $left['weekday'],
            $left['starts_at_local'],
            $left['ends_at_local'],
            $left['effective_from'],
            $left['effective_until'],
        ] <=> [
            $right['weekday'],
            $right['starts_at_local'],
            $right['ends_at_local'],
            $right['effective_from'],
            $right['effective_until'],
        ]);

        return $normalized;
    }

    /** @param list<array<string, mixed>> $exceptions @return list<array<string, mixed>> */
    private function normalizedExceptions(array $exceptions): array
    {
        $normalized = [];
        foreach ($exceptions as $exception) {
            if (array_diff(array_keys($exception), [
                'local_date',
                'kind',
                'starts_at_local',
                'ends_at_local',
                'reason',
            ]) !== []) {
                throw ValidationException::withMessages([
                    'exceptions' => ['Exceptions contain unsupported fields.'],
                ]);
            }
            $kind = strtolower(trim((string) ($exception['kind'] ?? '')));
            if (! in_array($kind, ['open', 'closed'], true)) {
                throw ValidationException::withMessages([
                    'exceptions' => ['Exception kind must be open or closed.'],
                ]);
            }
            $date = $this->date($exception['local_date'] ?? null, 'exceptions');
            if ($date === null) {
                throw ValidationException::withMessages([
                    'exceptions' => ['Exception local_date is required.'],
                ]);
            }
            $startsAt = $kind === 'open'
                ? $this->time($exception['starts_at_local'] ?? null, 'exceptions')
                : null;
            $endsAt = $kind === 'open'
                ? $this->time($exception['ends_at_local'] ?? null, 'exceptions')
                : null;
            if ($kind === 'open' && $startsAt >= $endsAt) {
                throw ValidationException::withMessages([
                    'exceptions' => ['Open exceptions require a positive same-day local interval.'],
                ]);
            }
            $reason = $exception['reason'] ?? null;
            if ($reason !== null && (! is_string($reason) || mb_strlen($reason) > 255)) {
                throw ValidationException::withMessages([
                    'exceptions' => ['Exception reason must be at most 255 characters.'],
                ]);
            }
            $normalized[] = [
                'local_date' => $date,
                'kind' => $kind,
                'starts_at_local' => $startsAt,
                'ends_at_local' => $endsAt,
                'reason' => $reason === null ? null : trim($reason),
            ];
        }
        usort($normalized, static fn (array $left, array $right): int => [
            $left['local_date'],
            $left['kind'],
            $left['starts_at_local'],
        ] <=> [
            $right['local_date'],
            $right['kind'],
            $right['starts_at_local'],
        ]);

        return $normalized;
    }

    private function time(mixed $value, string $field): string
    {
        $time = is_string($value) ? trim($value) : '';
        if (! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $time)) {
            throw ValidationException::withMessages([$field => ['Local times must use HH:MM.']]);
        }

        return $time;
    }

    private function date(mixed $value, string $field): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            throw ValidationException::withMessages([$field => ['Dates must use YYYY-MM-DD.']]);
        }
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value, 'UTC');
        } catch (\Throwable) {
            $date = null;
        }
        if (! $date instanceof CarbonImmutable || $date->format('Y-m-d') !== $value) {
            throw ValidationException::withMessages([$field => ['Dates must use YYYY-MM-DD.']]);
        }

        return $value;
    }

    private function canonicalScopeQuery(string $table, ReplaceAvailabilityRulesCommand $command): Builder
    {
        $query = DB::table($table)
            ->where('tenant_id', $command->tenantId)
            ->where('location_id', $command->locationId)
            ->where('source', 'canonical');
        $command->serviceId === null
            ? $query->whereNull('service_id')
            : $query->where('service_id', $command->serviceId);
        $command->providerId === null
            ? $query->whereNull('service_provider_id')
            : $query->where('service_provider_id', $command->providerId);

        return $query;
    }

    /** @param array<string, mixed> $payload */
    private function hash(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
