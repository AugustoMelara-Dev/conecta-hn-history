<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence;

use App\Domain\Identity\Entities\UserEntity;
use App\Infrastructure\Persistence\EloquentUserRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EloquentUserRepositoryTest extends TestCase
{
    // use RefreshDatabase; // Disabled due to broken project-wide migrations in SQLite

    private EloquentUserRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        // MANUAL SCHEMA PROVISIONING (Isolation Strategy)
        // We create only the table needed for this repository, bypassing broken migrations.
        \Illuminate\Support\Facades\Schema::create('users', function (\Illuminate\Database\Schema\Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->string('role');
            $table->string('avatar')->nullable();
            $table->string('provider')->nullable(); // Legacy col support
            $table->string('provider_id')->nullable(); // Legacy col support
            $table->timestamps();
        });

        // REAL Integration: No Mocks allowed.
        $this->repository = new EloquentUserRepository;
    }

    /** @test */
    public function it_persists_a_new_user_entity_with_pregenerated_uuid(): void
    {
        // 1. Arrange: Create Valid Domain Entity with UUID v7
        $tenantId = (string) Str::uuid();
        $user = UserEntity::registerViaSocial(
            tenantId: $tenantId,
            name: 'John Doe',
            email: 'john@example.com',
            avatar: 'https://example.com/avatar.jpg',
            provider: 'google', // Used for entity creation but persists to logic
            providerId: '123456'
        );

        $uuid = $user->id();

        // 2. Act: Persist via Repository
        $this->repository->save($user);

        // 3. Assert: Verify SQL Rows
        $this->assertDatabaseHas('users', [
            'id' => $uuid,
            'tenant_id' => $tenantId,
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'avatar' => 'https://example.com/avatar.jpg',
        ]);

        $this->assertDatabaseCount('users', 1);
    }

    /** @test */
    public function it_updates_an_existing_user_without_creating_duplicates(): void
    {
        // 1. Arrange: Persist Initial State
        $tenantId = (string) Str::uuid();
        $user = UserEntity::registerViaSocial(
            tenantId: $tenantId,
            name: 'Original Name',
            email: 'test@example.com',
            avatar: 'https://placeholder.com/avatar.jpg',
            provider: 'google',
            providerId: '123'
        );

        $this->repository->save($user);
        $this->assertDatabaseCount('users', 1);

        // 2. Act: Mutate State & Re-save
        // Domain Logic: Update Profile
        $user->updateProfile(
            name: 'Updated Name',
            email: 'updated@example.com'
        );

        $this->repository->save($user);

        // 3. Assert: Verify Upsert Logic (Check-then-Act)
        // Should STILL be 1 row, but with updated values
        $this->assertDatabaseCount('users', 1);

        $this->assertDatabaseHas('users', [
            'id' => $user->id(),
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
        ]);

        $this->assertDatabaseMissing('users', [
            'name' => 'Original Name',
        ]);
    }

    /** @test */
    public function it_reconstitutes_domain_entity_from_database_accurately(): void
    {
        // 1. Arrange: Persist User
        $tenantId = (string) Str::uuid();
        $original = UserEntity::registerViaSocial(
            tenantId: $tenantId,
            name: 'Hydration Test',
            email: 'hydration@example.com',
            avatar: 'avatar.png',
            provider: 'github',
            providerId: '999'
        );
        $this->repository->save($original);

        // 2. Act: Retrieve via Repository (Round-Trip)
        $retrieved = $this->repository->findById($original->id(), $tenantId);

        // 3. Assert: Fidelity Check
        $this->assertNotNull($retrieved, 'Repository returned null for existing user');
        $this->assertInstanceOf(UserEntity::class, $retrieved);

        // Value Equality
        $this->assertSame($original->id(), $retrieved->id());
        $this->assertSame($original->tenantId(), $retrieved->tenantId());
        $this->assertSame($original->email(), $retrieved->email());
        $this->assertSame($original->name(), $retrieved->name());
        $this->assertSame($original->avatar(), $retrieved->avatar());

        // Check Timestamps exist
        $this->assertNotNull($retrieved->createdAt());
    }

    /** @test */
    public function it_returns_null_when_user_does_not_exist(): void
    {
        // 1. Arrange: Generate Non-Existent UUID
        $randomUuid = (string) Str::uuid();
        $tenantId = (string) Str::uuid();

        // 2. Act
        $result = $this->repository->findById($randomUuid, $tenantId);

        // 3. Assert
        $this->assertNull($result);
    }
}
