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
 * - Valid Birth State (TenantID required at register).
 */
final class UserEntity
{
    use EventSourcedAggregate;

    private string $id;
    private string $name;
    private string $email;
    private string $password;
    private UserRole $role;
    private string $tenantId;
    private ?DateTimeImmutable $createdAt = null;
    private ?DateTimeImmutable $updatedAt = null;

    private function __construct() {}

    /**
     * Factory: Register a new User with Pre-allocated Identity.
     * 
     * @param string $uuid Pre-allocated UUID v4/v7.
     * @param string $tenantId Pre-allocated Tenant UUID.
     */
    public static function register(
        string $uuid,
        string $name, 
        string $email, 
        string $passwordHash, 
        UserRole $role,
        string $tenantId
    ): self {
        $user = new self();

        // The Event is recorded ONCE with the FINAL state.
        // No patching allowed.
        $event = new UserRegistered(
            user_id: $uuid,
            name: $name,
            email: $email,
            password_hash: $passwordHash,
            role: $role->value,
            tenant_id: $tenantId
        );

        $user->recordThat($event);

        return $user;
    }

    // -- MUTATORS (Internal) --

    protected function applyUserRegistered(UserRegistered $event): void
    {
        $this->id = $event->user_id;
        $this->name = $event->name;
        $this->email = $event->email;
        $this->password = $event->password_hash;
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

    public function tenantId(): string
    {
        return $this->tenantId;
    }
    
    public function createdAt(): ?DateTimeImmutable
    {
        return $this->createdAt;
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
            'created_at' => $this->createdAt?->format(DateTimeImmutable::ATOM),
            'updated_at' => $this->updatedAt?->format(DateTimeImmutable::ATOM),
        ];
    }

    public static function fromStateArray(array $state): self
    {
        $user = new self();
        $user->id = (string) ($state['id'] ?? '');
        $user->name = $state['name'] ?? '';
        $user->email = $state['email'] ?? '';
        $user->password = $state['password'] ?? '';
        $user->role = UserRole::from($state['role']);
        $user->tenantId = (string) ($state['tenant_id'] ?? '');
        $user->createdAt = isset($state['created_at']) ? new DateTimeImmutable($state['created_at']) : null;
        $user->updatedAt = isset($state['updated_at']) ? new DateTimeImmutable($state['updated_at']) : null;
        $user->version = 0;
        return $user;
    }
}
