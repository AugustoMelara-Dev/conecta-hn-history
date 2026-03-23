<?php

declare(strict_types=1);

namespace App\Domain\Identity\Entities;

use App\Domain\Common\Traits\EventSourcedAggregate;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Events\UserRegistered;
use DateTimeImmutable;

/**
 * UserEntity - Aggregate Root (Titanium Immutable)
 *
 * - Identity Pre-allocation (UUIDs).
 * - Full Immutability (No setters, no Event Patching).
 * - Valid Birth State (tenant assignment may happen after registration).
 */
final class UserEntity
{
    use EventSourcedAggregate;

    private string $id;

    private string $name;

    private string $email;

    private string $password;

    private UserRole $role;

    private ?string $tenantId = null;

    private ?string $rememberToken = null;

    private ?string $avatar = null;

    private ?DateTimeImmutable $bannedAt = null;

    private ?DateTimeImmutable $createdAt = null;

    private ?DateTimeImmutable $updatedAt = null;

    private function __construct() {}

    /**
     * Factory: Register a new User with Pre-allocated Identity.
     *
     * @param  string  $uuid  Pre-allocated UUID v4/v7.
     * @param  ?string  $tenantId  Pre-allocated Tenant UUID or null for global.
     */
    public static function register(
        string $uuid,
        string $name,
        string $email,
        string $passwordHash,
        UserRole $role,
        ?string $tenantId = null
    ): self {
        $user = new self;

        // Password is set on the aggregate directly — never travels in events (A6).
        $user->password = $passwordHash;

        // The Event is recorded ONCE with the FINAL state.
        // No patching allowed.
        $event = UserRegistered::now(
            user_id: $uuid,
            name: $name,
            email: $email,
            role: $role->value,
            tenant_id: $tenantId,
        );

        $user->recordThat($event);

        return $user;
    }

    /**
     * Factory: Register via Social Provider (Named Constructor).
     *
     * Handles semantic creation for federated identities.
     * Uses Ordered UUIDs (v7) for Index Performance.
     */
    public static function registerViaSocial(
        string $tenantId,
        string $name,
        string $email,
        string $avatar,
        string $provider,
        string $providerId
    ): self {
        $user = new self;
        // OPTIMIZATION: Use Ordered UUID to prevent B-Tree fragmentation
        $user->id = \Ramsey\Uuid\Uuid::uuid7()->toString();
        $user->tenantId = $tenantId;
        $user->name = $name;
        $user->email = $email;
        $user->password = ''; // No password for social users
        $user->avatar = $avatar;
        $user->role = UserRole::CUSTOMER; // Default role for social users
        $user->createdAt = new DateTimeImmutable;
        $user->updatedAt = new DateTimeImmutable;

        return $user;
    }

    // -- MUTATORS (Internal) --

    protected function applyUserRegistered(UserRegistered $event): void
    {
        $this->id = $event->user_id;
        $this->name = $event->name;
        $this->email = \Illuminate\Support\Facades\Crypt::decryptString($event->email->value());
        // NOTE: $password is set directly in register() before recordThat().
        // It is intentionally absent from the event payload (A6 — no credentials in events).
        $this->role = UserRole::from($event->role);
        $this->tenantId = $event->tenant_id;
        $this->createdAt = $event->occurredOn();
        $this->updatedAt = $event->occurredOn();
    }

    // -- GETTERS (Strict & Explicit) --

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function password(): string
    {
        return $this->password;
    }

    public function role(): UserRole
    {
        return $this->role;
    }

    public function tenantId(): ?string
    {
        return $this->tenantId;
    }

    public function rememberToken(): ?string
    {
        return $this->rememberToken;
    }

    public function hasPassword(): bool
    {
        return ! empty($this->password);
    }

    public function avatar(): ?string
    {
        return $this->avatar;
    }

    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function bannedAt(): ?DateTimeImmutable
    {
        return $this->bannedAt;
    }

    // -- SNAPSHOT COMPATIBILITY --

    public function toStateArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
            'role' => $this->role->value,
            'tenant_id' => $this->tenantId,
            'remember_token' => $this->rememberToken,
            'avatar' => $this->avatar,
            'banned_at' => $this->bannedAt?->format(DateTimeImmutable::ATOM),
            'created_at' => $this->createdAt?->format(DateTimeImmutable::ATOM),
            'updated_at' => $this->updatedAt?->format(DateTimeImmutable::ATOM),
        ];
    }

    public static function fromStateArray(array $state): self
    {
        $user = new self;
        $user->id = (string) ($state['id'] ?? '');
        $user->name = $state['name'] ?? '';
        $user->email = $state['email'] ?? '';
        $user->password = $state['password'] ?? '';
        $user->role = UserRole::from($state['role']);
        $user->tenantId = array_key_exists('tenant_id', $state) && $state['tenant_id'] !== null
            ? (string) $state['tenant_id']
            : null;
        $user->rememberToken = $state['remember_token'] ?? null;
        $user->avatar = $state['avatar'] ?? null;
        $user->bannedAt = isset($state['banned_at'])
            ? new DateTimeImmutable($state['banned_at'])
            : null;
        $user->createdAt = isset($state['created_at']) ? new DateTimeImmutable($state['created_at']) : null;
        $user->updatedAt = isset($state['updated_at']) ? new DateTimeImmutable($state['updated_at']) : null;
        $user->version = 0;

        return $user;
    }

    // -- BEHAVIOR (Rich Domain Model) --

    public function updateProfile(string $name, string $email): void
    {
        $this->name = $name;
        $this->email = $email;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function assignToTenant(string $tenantId): void
    {
        $this->tenantId = $tenantId;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function reactivate(): void
    {
        if ($this->bannedAt === null) {
            throw new \DomainException('User is already active.');
        }

        $this->bannedAt = null;
        $this->updatedAt = new DateTimeImmutable;
    }

    public function withPassword(string $hashedPassword): self
    {
        // Clone for immutability
        $clone = clone $this;
        $clone->password = $hashedPassword;
        $clone->updatedAt = new DateTimeImmutable;

        return $clone;
    }

    public function withRememberToken(?string $token): self
    {
        $clone = clone $this;
        $clone->rememberToken = $token;

        // Token rotation doesn't necessarily update 'updated_at' but good practice.
        return $clone;
    }
}
