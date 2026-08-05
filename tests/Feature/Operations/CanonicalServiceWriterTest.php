<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Application\ServiceCatalog\CanonicalServiceWriter;
use App\Application\ServiceCatalog\Commands\CreateServiceCommand;
use App\Application\ServiceCatalog\Commands\UpdateServiceCommand;
use App\Application\ServiceCatalog\Commands\UpdateServiceProviderCommand;
use App\Application\ServiceCatalog\Commands\UpsertServiceProviderCommand;
use App\Domain\Access\Enums\Role;
use App\Domain\Operations\Events\BusinessOperationChanged;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\GlobalMarketFixture;
use Tests\TestCase;

final class CanonicalServiceWriterTest extends TestCase
{
    use RefreshDatabase;

    public function test_canadian_service_creation_uses_minor_units_locations_and_idempotent_replay(): void
    {
        [$tenant, $owner] = $this->serviceTenant();
        $location = GlobalMarketFixture::assignCanada($tenant);
        $writer = app(CanonicalServiceWriter::class);
        $command = new CreateServiceCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            idempotencyKey: 'service:create:ca-1',
            name: 'Strategy consultation',
            priceMinor: 12_500,
            currency: 'CAD',
            durationMinutes: 45,
            locationIds: [(string) $location->id],
            description: 'A focused consultation.',
        );

        $first = $writer->create($command);
        $second = $writer->create($command);

        self::assertSame($first->getKey(), $second->getKey());
        self::assertSame('125.00', (string) $first->getRawOriginal('price'));
        self::assertSame(12_500, $first->price_minor);
        self::assertSame('CAD', $first->currency);
        self::assertSame(45, $first->duration_minutes);
        $this->assertDatabaseHas('service_locations', [
            'tenant_id' => $tenant->id,
            'service_id' => $first->id,
            'location_id' => $location->id,
        ]);
        self::assertSame(1, DB::table('services')->where('tenant_id', $tenant->id)->count());
        self::assertSame(1, DB::table('outbox_messages')
            ->where('type', BusinessOperationChanged::class)
            ->count());
    }

    public function test_create_rejects_incompatible_replay_and_cross_tenant_location(): void
    {
        [$tenant, $owner] = $this->serviceTenant();
        $location = GlobalMarketFixture::assignCanada($tenant);
        [$other] = $this->serviceTenant();
        $otherLocation = GlobalMarketFixture::assignCanada($other);
        $writer = app(CanonicalServiceWriter::class);
        $base = new CreateServiceCommand(
            (string) $tenant->id,
            (string) $owner->id,
            'service:create:replay',
            'Consultation',
            10_000,
            'CAD',
            60,
            [(string) $location->id],
        );
        $writer->create($base);

        try {
            $writer->create(new CreateServiceCommand(
                (string) $tenant->id,
                (string) $owner->id,
                'service:create:replay',
                'Consultation',
                10_001,
                'CAD',
                60,
                [(string) $location->id],
            ));
            self::fail('An idempotency key cannot be reused with a changed economic payload.');
        } catch (\LogicException) {
            self::assertSame(1, DB::table('services')->where('tenant_id', $tenant->id)->count());
        }

        $this->expectException(ValidationException::class);
        $writer->create(new CreateServiceCommand(
            (string) $tenant->id,
            (string) $owner->id,
            'service:create:foreign-location',
            'Unsafe',
            10_000,
            'CAD',
            60,
            [(string) $otherLocation->id],
        ));
    }

    public function test_writer_rejects_actor_without_catalog_permission(): void
    {
        [$tenant] = $this->serviceTenant();
        GlobalMarketFixture::assignCanada($tenant);
        $viewer = User::factory()->create();
        DB::table('tenant_memberships')->insert([
            'id' => (string) Str::uuid(),
            'tenant_id' => $tenant->id,
            'user_id' => $viewer->id,
            'role' => Role::VIEWER->value,
            'preset_key' => null,
            'granted_permissions' => json_encode([], JSON_THROW_ON_ERROR),
            'status' => 'active',
            'invited_at' => now(),
            'accepted_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(AuthorizationException::class);
        app(CanonicalServiceWriter::class)->create(new CreateServiceCommand(
            (string) $tenant->id,
            (string) $viewer->id,
            'service:create:viewer',
            'Denied',
            100,
            'CAD',
            30,
            [],
        ));
    }

    public function test_update_publish_and_delete_are_tenant_scoped_and_market_safe(): void
    {
        [$tenant, $owner] = $this->serviceTenant();
        $location = GlobalMarketFixture::assignCanada($tenant);
        [$other, $otherOwner] = $this->serviceTenant();
        GlobalMarketFixture::assignCanada($other);
        $writer = app(CanonicalServiceWriter::class);
        $service = $writer->create(new CreateServiceCommand(
            (string) $tenant->id,
            (string) $owner->id,
            'service:create:update',
            'Original',
            100,
            'CAD',
            30,
            [(string) $location->id],
        ));

        try {
            $writer->update(new UpdateServiceCommand(
                (string) $other->id,
                (string) $otherOwner->id,
                (int) $service->id,
                name: 'Hostile',
            ));
            self::fail('Cross-tenant service updates must be hidden.');
        } catch (ModelNotFoundException) {
            self::assertSame('Original', $service->fresh()->name);
        }

        $updated = $writer->update(new UpdateServiceCommand(
            (string) $tenant->id,
            (string) $owner->id,
            (int) $service->id,
            name: 'Updated',
            priceMinor: 12_345,
            currency: 'CAD',
            durationMinutes: 75,
            locationIds: [(string) $location->id],
        ));
        self::assertSame('123.45', (string) $updated->getRawOriginal('price'));
        self::assertSame(75, $updated->duration_minutes);
        self::assertTrue($writer->publish(
            (string) $tenant->id,
            (string) $owner->id,
            (int) $service->id,
        )->is_active);

        $writer->delete((string) $tenant->id, (string) $owner->id, (int) $service->id);
        $this->assertDatabaseHas('services', ['id' => $service->id, 'is_active' => false]);
    }

    public function test_provider_upsert_is_idempotent_and_location_scoped(): void
    {
        [$tenant, $owner] = $this->serviceTenant();
        $location = GlobalMarketFixture::assignCanada($tenant);
        $writer = app(CanonicalServiceWriter::class);
        $command = new UpsertServiceProviderCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            idempotencyKey: 'provider:ca-1',
            name: 'Alex Morgan',
            locationIds: [(string) $location->id],
            email: 'alex@example.test',
        );

        $first = $writer->upsertProvider($command);
        $second = $writer->upsertProvider($command);

        self::assertSame($first->getKey(), $second->getKey());
        $this->assertDatabaseHas('service_provider_locations', [
            'tenant_id' => $tenant->id,
            'service_provider_id' => $first->id,
            'location_id' => $location->id,
        ]);
    }

    public function test_provider_update_and_archive_are_tenant_scoped_and_preserve_history(): void
    {
        [$tenant, $owner] = $this->serviceTenant();
        $location = GlobalMarketFixture::assignCanada($tenant);
        [$other, $otherOwner] = $this->serviceTenant();
        GlobalMarketFixture::assignCanada($other);
        $writer = app(CanonicalServiceWriter::class);
        $provider = $writer->upsertProvider(new UpsertServiceProviderCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            idempotencyKey: 'provider:update-source',
            name: 'Original Provider',
            locationIds: [],
        ));

        try {
            $writer->updateProvider(new UpdateServiceProviderCommand(
                tenantId: (string) $other->id,
                actorId: (string) $otherOwner->id,
                providerId: (int) $provider->id,
                name: 'Hostile Rename',
                locationIds: [],
            ));
            self::fail('Cross-tenant provider updates must be hidden.');
        } catch (ModelNotFoundException) {
            self::assertSame('Original Provider', $provider->fresh()->name);
        }

        $updated = $writer->updateProvider(new UpdateServiceProviderCommand(
            tenantId: (string) $tenant->id,
            actorId: (string) $owner->id,
            providerId: (int) $provider->id,
            name: 'Updated Provider',
            email: 'UPDATED@EXAMPLE.TEST',
            phone: '+1 416 555 0100',
            active: true,
            locationIds: [(string) $location->id],
        ));

        self::assertSame('Updated Provider', $updated->name);
        self::assertSame('updated@example.test', $updated->email);
        $this->assertDatabaseHas('service_provider_locations', [
            'tenant_id' => $tenant->id,
            'service_provider_id' => $provider->id,
            'location_id' => $location->id,
        ]);

        $writer->archiveProvider(
            (string) $tenant->id,
            (string) $owner->id,
            (int) $provider->id,
        );
        $this->assertDatabaseHas('service_providers', [
            'id' => $provider->id,
            'tenant_id' => $tenant->id,
            'is_active' => false,
        ]);
    }

    /**
     * @return array{Tenant, User}
     */
    private function serviceTenant(): array
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->forUser($owner)->create([
            'business_type' => 'service',
            'commercial_mode' => 'appointments',
        ]);

        return [$tenant, $owner];
    }
}
