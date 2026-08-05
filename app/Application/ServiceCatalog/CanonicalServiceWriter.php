<?php

declare(strict_types=1);

namespace App\Application\ServiceCatalog;

// @withoutGlobalScopes-safety CANONICAL_WRITER_TENANT_FILTER: every bypass is constrained by command tenant ownership.

use App\Application\Access\Contracts\TenantPermissionGuardInterface;
use App\Application\Markets\TenantMarketResolver;
use App\Application\ServiceCatalog\Commands\CreateServiceCommand;
use App\Application\ServiceCatalog\Commands\UpdateServiceCommand;
use App\Application\ServiceCatalog\Commands\UpdateServiceProviderCommand;
use App\Application\ServiceCatalog\Commands\UpsertServiceProviderCommand;
use App\Domain\Access\Enums\Permission;
use App\Domain\Operations\Events\BusinessOperationChanged;
use App\Domain\Shared\OutboxRepositoryInterface;
use App\Domain\TenantGovernance\Enums\Capability;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Authoritative operational writer for bookable services and providers.
 */
final readonly class CanonicalServiceWriter
{
    public function __construct(
        private TenantPermissionGuardInterface $permissionGuard,
        private TenantMarketResolver $marketResolver,
        private OutboxRepositoryInterface $outbox,
    ) {}

    public function create(CreateServiceCommand $command): Service
    {
        $this->assertCreateCommand($command);

        return DB::transaction(function () use ($command): Service {
            [$tenant, $actor] = $this->authority(
                $command->tenantId,
                $command->actorId,
                Permission::CATALOG_CREATE,
            );
            $market = $this->marketResolver->resolve($tenant);
            $currency = strtoupper($command->currency);
            $minorUnit = $this->assertCurrency($currency, $market->currencyCode);
            $locationIds = $this->validatedLocationIds(
                $command->tenantId,
                $command->locationIds,
                $currency,
            );
            $payloadHash = $this->hash([
                'name' => trim($command->name),
                'description' => $command->description,
                'price_minor' => $command->priceMinor,
                'currency' => $currency,
                'duration_minutes' => $command->durationMinutes,
                'location_ids' => $locationIds,
                'published' => $command->published,
            ]);

            $existing = Service::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $command->tenantId)
                ->where('write_idempotency_key', $command->idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof Service) {
                if (! hash_equals((string) $existing->write_payload_hash, $payloadHash)) {
                    throw new \LogicException('Service idempotency key was reused with an incompatible payload.');
                }

                return $existing;
            }

            $service = new Service;
            $service->forceFill([
                'tenant_id' => $command->tenantId,
                'name' => trim($command->name),
                'description' => $command->description,
                'duration_minutes' => $command->durationMinutes,
                'price' => $this->legacyDecimal($command->priceMinor, $minorUnit),
                'price_minor' => $command->priceMinor,
                'currency' => $currency,
                'is_active' => $command->published,
                'write_idempotency_key' => $command->idempotencyKey,
                'write_payload_hash' => $payloadHash,
            ])->save();

            $this->syncServiceLocations($command->tenantId, (int) $service->getKey(), $locationIds);
            $this->audit($actor, $service, 'service.created', [
                'tenant_id' => $command->tenantId,
                'currency' => $currency,
            ]);

            return $service;
        }, 3);
    }

    public function update(UpdateServiceCommand $command): Service
    {
        if (! Str::isUuid($command->tenantId)
            || ! Str::isUuid($command->actorId)
            || $command->serviceId < 1) {
            throw new \InvalidArgumentException('Service update requires tenant, actor, and service identifiers.');
        }

        return DB::transaction(function () use ($command): Service {
            [$tenant, $actor] = $this->authority(
                $command->tenantId,
                $command->actorId,
                Permission::CATALOG_UPDATE,
            );
            $service = $this->serviceForTenant($command->tenantId, $command->serviceId);
            $market = $this->marketResolver->resolve($tenant);
            $currency = strtoupper($command->currency ?? (string) $service->currency);
            $minorUnit = $this->assertCurrency($currency, $market->currencyCode);
            $priceMinor = $command->priceMinor ?? (int) $service->price_minor;
            $duration = $command->durationMinutes ?? (int) $service->duration_minutes;
            $this->assertEconomicFields($priceMinor, $minorUnit, $duration);
            $locationIds = $command->locationIds === null
                ? null
                : $this->validatedLocationIds($command->tenantId, $command->locationIds, $currency);

            $attributes = [
                'currency' => $currency,
                'price_minor' => $priceMinor,
                'price' => $this->legacyDecimal($priceMinor, $minorUnit),
                'duration_minutes' => $duration,
            ];
            if ($command->name !== null) {
                if (trim($command->name) === '') {
                    throw new \InvalidArgumentException('Service name cannot be empty.');
                }
                $attributes['name'] = trim($command->name);
            }
            if ($command->descriptionSpecified) {
                $attributes['description'] = $command->description;
            }
            if ($command->published !== null) {
                $attributes['is_active'] = $command->published;
            }

            $service->forceFill($attributes)->save();
            if ($locationIds !== null) {
                $this->syncServiceLocations($command->tenantId, $command->serviceId, $locationIds);
            }
            $this->audit($actor, $service, 'service.updated', [
                'tenant_id' => $command->tenantId,
            ]);

            return $service;
        }, 3);
    }

    public function publish(
        string $tenantId,
        string $actorId,
        int $serviceId,
        bool $published = true,
    ): Service {
        return DB::transaction(function () use ($tenantId, $actorId, $serviceId, $published): Service {
            [, $actor] = $this->authority($tenantId, $actorId, Permission::CATALOG_PUBLISH);
            $service = $this->serviceForTenant($tenantId, $serviceId);
            $service->forceFill(['is_active' => $published])->save();
            $this->audit($actor, $service, $published ? 'service.published' : 'service.unpublished', [
                'tenant_id' => $tenantId,
            ]);

            return $service;
        }, 3);
    }

    public function delete(string $tenantId, string $actorId, int $serviceId): void
    {
        DB::transaction(function () use ($tenantId, $actorId, $serviceId): void {
            [, $actor] = $this->authority($tenantId, $actorId, Permission::CATALOG_DELETE);
            $service = $this->serviceForTenant($tenantId, $serviceId);
            $service->forceFill(['is_active' => false])->save();
            $this->audit($actor, $service, 'service.archived', ['tenant_id' => $tenantId]);
        }, 3);
    }

    public function upsertProvider(UpsertServiceProviderCommand $command): ServiceProvider
    {
        if (! Str::isUuid($command->tenantId)
            || ! Str::isUuid($command->actorId)
            || trim($command->idempotencyKey) === ''
            || trim($command->name) === '') {
            throw new \InvalidArgumentException('Provider upsert requires tenant, actor, idempotency key, and name.');
        }

        return DB::transaction(function () use ($command): ServiceProvider {
            [, $actor] = $this->authority(
                $command->tenantId,
                $command->actorId,
                Permission::STAFF_MANAGE,
                Capability::STAFF_BASIC,
            );
            $locationIds = $this->validatedLocationIds($command->tenantId, $command->locationIds);
            $payloadHash = $this->hash([
                'name' => trim($command->name),
                'email' => $command->email === null ? null : strtolower(trim($command->email)),
                'phone' => $command->phone,
                'active' => $command->active,
                'location_ids' => $locationIds,
            ]);
            $existing = ServiceProvider::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $command->tenantId)
                ->where('write_idempotency_key', $command->idempotencyKey)
                ->lockForUpdate()
                ->first();
            if ($existing instanceof ServiceProvider) {
                if (! hash_equals((string) $existing->write_payload_hash, $payloadHash)) {
                    throw new \LogicException('Provider idempotency key was reused with an incompatible payload.');
                }

                return $existing;
            }

            $provider = new ServiceProvider;
            $provider->forceFill([
                'tenant_id' => $command->tenantId,
                'name' => trim($command->name),
                'email' => $command->email === null ? null : strtolower(trim($command->email)),
                'phone' => $command->phone,
                'is_active' => $command->active,
                'write_idempotency_key' => $command->idempotencyKey,
                'write_payload_hash' => $payloadHash,
            ])->save();
            $this->syncProviderLocations($command->tenantId, (int) $provider->getKey(), $locationIds);
            $this->audit($actor, $provider, 'service_provider.upserted', [
                'tenant_id' => $command->tenantId,
            ]);

            return $provider;
        }, 3);
    }

    public function updateProvider(UpdateServiceProviderCommand $command): ServiceProvider
    {
        if (! Str::isUuid($command->tenantId)
            || ! Str::isUuid($command->actorId)
            || $command->providerId < 1
            || trim($command->name) === '') {
            throw new \InvalidArgumentException('Provider update requires tenant, actor, provider, and name.');
        }

        return DB::transaction(function () use ($command): ServiceProvider {
            [, $actor] = $this->authority(
                $command->tenantId,
                $command->actorId,
                Permission::STAFF_MANAGE,
                Capability::STAFF_BASIC,
            );
            $provider = $this->providerForTenant($command->tenantId, $command->providerId);
            $locationIds = $this->validatedLocationIds($command->tenantId, $command->locationIds);
            $provider->forceFill([
                'name' => trim($command->name),
                'email' => $command->email === null ? null : strtolower(trim($command->email)),
                'phone' => $command->phone,
                'is_active' => $command->active,
            ])->save();
            $this->syncProviderLocations($command->tenantId, $command->providerId, $locationIds);
            $this->audit($actor, $provider, 'service_provider.updated', [
                'tenant_id' => $command->tenantId,
            ]);

            return $provider;
        }, 3);
    }

    public function archiveProvider(string $tenantId, string $actorId, int $providerId): void
    {
        DB::transaction(function () use ($tenantId, $actorId, $providerId): void {
            [, $actor] = $this->authority(
                $tenantId,
                $actorId,
                Permission::STAFF_MANAGE,
                Capability::STAFF_BASIC,
            );
            $provider = $this->providerForTenant($tenantId, $providerId);
            $provider->forceFill(['is_active' => false])->save();
            $this->audit($actor, $provider, 'service_provider.archived', [
                'tenant_id' => $tenantId,
            ]);
        }, 3);
    }

    /**
     * @return array{Tenant, User}
     */
    private function authority(
        string $tenantId,
        string $actorId,
        Permission $permission,
        Capability $capability = Capability::APPOINTMENT_BOOKING,
    ): array {
        if (! Str::isUuid($tenantId) || ! Str::isUuid($actorId)) {
            throw new \InvalidArgumentException('Tenant and actor identifiers must be UUIDs.');
        }

        $tenant = Tenant::query()->withoutGlobalScopes()->whereKey($tenantId)->lockForUpdate()->firstOrFail();
        $actor = User::query()->whereKey($actorId)->firstOrFail();
        if (! $this->permissionGuard->allows($actor, $tenant, $capability, $permission)) {
            throw new AuthorizationException('The actor cannot mutate this service catalog.');
        }

        return [$tenant, $actor];
    }

    private function serviceForTenant(string $tenantId, int $serviceId): Service
    {
        return Service::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($serviceId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function providerForTenant(string $tenantId, int $providerId): ServiceProvider
    {
        return ServiceProvider::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereKey($providerId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function assertCreateCommand(CreateServiceCommand $command): void
    {
        if (! Str::isUuid($command->tenantId)
            || ! Str::isUuid($command->actorId)
            || trim($command->idempotencyKey) === ''
            || trim($command->name) === '') {
            throw new \InvalidArgumentException('Service creation requires tenant, actor, idempotency key, and name.');
        }
        $this->assertEconomicFields($command->priceMinor, null, $command->durationMinutes);
    }

    private function assertEconomicFields(int $priceMinor, ?int $minorUnit, int $durationMinutes): void
    {
        if ($priceMinor < 0 || $durationMinutes < 1 || $durationMinutes > 10_080) {
            throw new \InvalidArgumentException('Service price and duration are outside supported bounds.');
        }
        if ($minorUnit !== null) {
            $scale = 10 ** $minorUnit;
            $maxMinor = 99_999_999 * $scale + ($scale - 1);
            if ($priceMinor > $maxMinor) {
                throw new \InvalidArgumentException('Service price exceeds the legacy decimal compatibility range.');
            }
        }
    }

    private function assertCurrency(string $currency, string $marketCurrency): int
    {
        if (! preg_match('/^[A-Z]{3}$/', $currency)
            || $currency !== strtoupper($marketCurrency)) {
            throw ValidationException::withMessages([
                'currency' => ['Service currency must match the tenant market currency.'],
            ]);
        }

        $minorUnit = DB::table('currencies')->where('code', $currency)->value('minor_unit');
        if (! is_numeric($minorUnit) || (int) $minorUnit < 0 || (int) $minorUnit > 6) {
            throw ValidationException::withMessages([
                'currency' => ['Service currency minor-unit metadata is unavailable.'],
            ]);
        }

        return (int) $minorUnit;
    }

    /**
     * @param  list<string>  $locationIds
     * @return list<string>
     */
    private function validatedLocationIds(
        string $tenantId,
        array $locationIds,
        ?string $currency = null,
    ): array {
        $locationIds = array_values(array_unique($locationIds));
        foreach ($locationIds as $locationId) {
            if (! is_string($locationId) || ! Str::isUuid($locationId)) {
                throw ValidationException::withMessages(['location_ids' => ['Locations must be UUIDs.']]);
            }
        }
        if ($locationIds === []) {
            return [];
        }

        $query = DB::table('locations')
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->whereIn('id', $locationIds);
        if ($currency !== null) {
            $query->where('currency_code', $currency);
        }
        if ($query->count() !== count($locationIds)) {
            throw ValidationException::withMessages([
                'location_ids' => ['Every location must be active, currency-compatible, and owned by the tenant.'],
            ]);
        }

        sort($locationIds);

        return $locationIds;
    }

    /**
     * @param  list<string>  $locationIds
     */
    private function syncServiceLocations(string $tenantId, int $serviceId, array $locationIds): void
    {
        DB::table('service_locations')
            ->where('tenant_id', $tenantId)
            ->where('service_id', $serviceId)
            ->delete();
        foreach ($locationIds as $locationId) {
            DB::table('service_locations')->insert([
                'tenant_id' => $tenantId,
                'service_id' => $serviceId,
                'location_id' => $locationId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  list<string>  $locationIds
     */
    private function syncProviderLocations(string $tenantId, int $providerId, array $locationIds): void
    {
        DB::table('service_provider_locations')
            ->where('tenant_id', $tenantId)
            ->where('service_provider_id', $providerId)
            ->delete();
        foreach ($locationIds as $locationId) {
            DB::table('service_provider_locations')->insert([
                'tenant_id' => $tenantId,
                'service_provider_id' => $providerId,
                'location_id' => $locationId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function legacyDecimal(int $minor, int $minorUnit): string
    {
        $scale = 10 ** $minorUnit;
        $major = $minor / $scale;

        return number_format($major, 2, '.', '');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hash(array $payload): string
    {
        return hash('sha256', json_encode($this->canonicalize($payload), JSON_THROW_ON_ERROR));
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    /**
     * @param  array<string, scalar|null>  $properties
     */
    private function audit(User $actor, Model $record, string $event, array $properties): void
    {
        activity('business-operations')
            ->performedOn($record)
            ->causedBy($actor)
            ->withProperties($properties)
            ->event($event)
            ->log($event);

        $tenantId = (string) ($properties['tenant_id'] ?? '');
        $aggregateType = Str::snake(class_basename($record));
        $this->outbox->append(new BusinessOperationChanged(
            aggregateId: $aggregateType.':'.$record->getKey(),
            aggregateType: $aggregateType,
            tenantId: $tenantId,
            actorId: (string) $actor->getKey(),
            eventName: $event,
            attributes: $properties,
            occurredAt: now()->toImmutable(),
        ));
    }
}
