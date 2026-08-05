<?php

declare(strict_types=1);

namespace App\Application\ServiceCatalog;

// @withoutGlobalScopes-safety CANONICAL_WRITER_TENANT_FILTER: adapter bypasses are constrained by authenticated tenant context.

use App\Application\Markets\TenantMarketResolver;
use App\Application\ServiceCatalog\Commands\CreateServiceCommand;
use App\Application\ServiceCatalog\Commands\UpdateServiceCommand;
use App\Application\ServiceCatalog\Commands\UpdateServiceProviderCommand;
use App\Application\ServiceCatalog\Commands\UpsertServiceProviderCommand;
use App\Models\Service;
use App\Models\ServiceProvider;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Filament-facing adapter. Submitted tenant and currency fields are never
 * authoritative; the authenticated actor, Filament tenant, and market are.
 */
final readonly class MerchantServiceCatalogAdapter
{
    public function __construct(
        private CanonicalServiceWriter $writer,
        private TenantMarketResolver $marketResolver,
    ) {}

    /** @param array<string, mixed> $data */
    public function createService(
        Tenant $tenant,
        User $actor,
        array $data,
        string $idempotencyKey,
    ): Service {
        [$currency, $minorUnit] = $this->currencyContext($tenant);

        return $this->writer->create(new CreateServiceCommand(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            idempotencyKey: $idempotencyKey,
            name: $this->name($data['name'] ?? null),
            priceMinor: $this->minorAmount($data['price'] ?? null, $minorUnit),
            currency: $currency,
            durationMinutes: $this->duration($data['duration_minutes'] ?? null),
            locationIds: $this->locationIds($data['location_ids'] ?? []),
            description: $this->optionalString($data['description'] ?? null),
            published: (bool) ($data['is_active'] ?? false),
        ));
    }

    /** @param array<string, mixed> $data */
    public function updateService(
        Tenant $tenant,
        User $actor,
        Service $service,
        array $data,
    ): Service {
        [$currency, $minorUnit] = $this->currencyContext($tenant);

        return $this->writer->update(new UpdateServiceCommand(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            serviceId: (int) $service->getKey(),
            name: array_key_exists('name', $data) ? $this->name($data['name']) : null,
            priceMinor: array_key_exists('price', $data)
                ? $this->minorAmount($data['price'], $minorUnit)
                : null,
            currency: $currency,
            durationMinutes: array_key_exists('duration_minutes', $data)
                ? $this->duration($data['duration_minutes'])
                : null,
            locationIds: array_key_exists('location_ids', $data)
                ? $this->locationIds($data['location_ids'])
                : null,
            description: array_key_exists('description', $data)
                ? $this->optionalString($data['description'])
                : null,
            descriptionSpecified: array_key_exists('description', $data),
            published: array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : null,
        ));
    }

    public function archiveService(Tenant $tenant, User $actor, Service $service): void
    {
        $this->writer->delete(
            (string) $tenant->getKey(),
            (string) $actor->getKey(),
            (int) $service->getKey(),
        );
    }

    /** @param array<string, mixed> $data */
    public function createProvider(
        Tenant $tenant,
        User $actor,
        array $data,
        string $idempotencyKey,
    ): ServiceProvider {
        $this->assertNoLegacyScheduleMutation($data);

        return $this->writer->upsertProvider(new UpsertServiceProviderCommand(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            idempotencyKey: $idempotencyKey,
            name: $this->name($data['name'] ?? null),
            locationIds: $this->locationIds($data['location_ids'] ?? []),
            email: $this->optionalString($data['email'] ?? null),
            phone: $this->optionalString($data['phone'] ?? null),
            active: (bool) ($data['is_active'] ?? true),
        ));
    }

    /** @param array<string, mixed> $data */
    public function updateProvider(
        Tenant $tenant,
        User $actor,
        ServiceProvider $provider,
        array $data,
    ): ServiceProvider {
        $this->assertNoLegacyScheduleMutation($data);
        $current = $this->trustedProvider($tenant, $provider);

        return $this->writer->updateProvider(new UpdateServiceProviderCommand(
            tenantId: (string) $tenant->getKey(),
            actorId: (string) $actor->getKey(),
            providerId: (int) $provider->getKey(),
            name: array_key_exists('name', $data)
                ? $this->name($data['name'])
                : (string) $current->name,
            locationIds: array_key_exists('location_ids', $data)
                ? $this->locationIds($data['location_ids'])
                : $this->providerLocationIds($tenant, $provider),
            email: array_key_exists('email', $data)
                ? $this->optionalString($data['email'])
                : $this->optionalString($current->email),
            phone: array_key_exists('phone', $data)
                ? $this->optionalString($data['phone'])
                : $this->optionalString($current->phone),
            active: array_key_exists('is_active', $data)
                ? (bool) $data['is_active']
                : (bool) $current->is_active,
        ));
    }

    public function archiveProvider(Tenant $tenant, User $actor, ServiceProvider $provider): void
    {
        $this->writer->archiveProvider(
            (string) $tenant->getKey(),
            (string) $actor->getKey(),
            (int) $provider->getKey(),
        );
    }

    /** @return array{string, int} */
    private function currencyContext(Tenant $tenant): array
    {
        $currency = strtoupper($this->marketResolver->resolve($tenant)->currencyCode);
        $minorUnit = DB::table('currencies')->where('code', $currency)->value('minor_unit');
        if (! is_numeric($minorUnit) || (int) $minorUnit < 0 || (int) $minorUnit > 6) {
            throw ValidationException::withMessages([
                'price' => ['Market currency minor-unit metadata is unavailable.'],
            ]);
        }

        return [$currency, (int) $minorUnit];
    }

    private function minorAmount(mixed $value, int $minorUnit): int
    {
        $decimal = is_int($value) || is_string($value) ? trim((string) $value) : '';
        $fractionPattern = $minorUnit === 0 ? '' : '(?:\.\d{1,'.$minorUnit.'})?';
        if (! preg_match('/^\d{1,8}'.$fractionPattern.'$/', $decimal)) {
            throw ValidationException::withMessages([
                'price' => ["Price must use no more than {$minorUnit} currency decimal places."],
            ]);
        }
        [$whole, $fraction] = array_pad(explode('.', $decimal, 2), 2, '');
        $scale = 10 ** $minorUnit;

        return ((int) $whole * $scale)
            + ($minorUnit === 0 ? 0 : (int) str_pad($fraction, $minorUnit, '0'));
    }

    private function duration(mixed $value): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false
            || (int) $value < 1
            || (int) $value > 10_080) {
            throw ValidationException::withMessages([
                'duration_minutes' => ['Duration must be a positive whole number of minutes.'],
            ]);
        }

        return (int) $value;
    }

    private function name(mixed $value): string
    {
        $name = is_string($value) ? trim($value) : '';
        if ($name === '') {
            throw ValidationException::withMessages(['name' => ['Name is required.']]);
        }

        return $name;
    }

    private function optionalString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_string($value)) {
            throw ValidationException::withMessages(['value' => ['The value must be text.']]);
        }

        return trim($value);
    }

    /** @return list<string> */
    private function locationIds(mixed $value): array
    {
        if (! is_array($value)) {
            throw ValidationException::withMessages(['location_ids' => ['Locations must be a list.']]);
        }

        return array_values($value);
    }

    private function trustedProvider(Tenant $tenant, ServiceProvider $provider): ServiceProvider
    {
        return ServiceProvider::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenant->getKey())
            ->whereKey($provider->getKey())
            ->firstOrFail();
    }

    /** @return list<string> */
    private function providerLocationIds(Tenant $tenant, ServiceProvider $provider): array
    {
        return DB::table('service_provider_locations')
            ->where('tenant_id', $tenant->getKey())
            ->where('service_provider_id', $provider->getKey())
            ->orderBy('location_id')
            ->pluck('location_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();
    }

    /** @param array<string, mixed> $data */
    private function assertNoLegacyScheduleMutation(array $data): void
    {
        if (array_key_exists('schedule_config', $data)
            && $data['schedule_config'] !== null
            && $data['schedule_config'] !== []) {
            throw ValidationException::withMessages([
                'schedule_config' => ['Use canonical availability rules to change provider schedules.'],
            ]);
        }
    }
}
