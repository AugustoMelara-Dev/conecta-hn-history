<?php

declare(strict_types=1);

namespace App\Domain\Identity\Entities;

use Carbon\Carbon;

/**
 * UserEntity - Pure Domain Entity for User
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  PURE: No Eloquent dependencies.                                        ║
 * ║  IMMUTABLE: Use wither methods for state transitions.                   ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UserEntity
{
    public function __construct(
        public int $id,
        public int $tenantId,
        public string $email,
        public string $name,
        public string $role,
        public ?string $password = null,
        public ?string $rememberToken = null,
        public ?string $avatar = null,
        public ?Carbon $emailVerifiedAt = null,
    ) {}

    // ═══════════════════════════════════════════════════════════════════════
    // WITHER METHODS (Immutable State Transitions)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Create a new instance with email verified.
     */
    public function withEmailVerified(?Carbon $date): self
    {
        return new self(
            id: $this->id,
            tenantId: $this->tenantId,
            email: $this->email,
            name: $this->name,
            role: $this->role,
            password: $this->password,
            rememberToken: $this->rememberToken,
            avatar: $this->avatar,
            emailVerifiedAt: $date,
        );
    }

    /**
     * Create a new instance with updated avatar.
     */
    public function withAvatar(?string $avatarUrl): self
    {
        return new self(
            id: $this->id,
            tenantId: $this->tenantId,
            email: $this->email,
            name: $this->name,
            role: $this->role,
            password: $this->password,
            rememberToken: $this->rememberToken,
            avatar: $avatarUrl,
            emailVerifiedAt: $this->emailVerifiedAt,
        );
    }

    /**
     * Create a new instance with updated name.
     */
    public function withName(string $name): self
    {
        return new self(
            id: $this->id,
            tenantId: $this->tenantId,
            email: $this->email,
            name: $name,
            role: $this->role,
            password: $this->password,
            rememberToken: $this->rememberToken,
            avatar: $this->avatar,
            emailVerifiedAt: $this->emailVerifiedAt,
        );
    }

    /**
     * Create a new instance with updated email.
     */
    public function withEmail(string $email): self
    {
        return new self(
            id: $this->id,
            tenantId: $this->tenantId,
            email: $email,
            name: $this->name,
            role: $this->role,
            password: $this->password,
            rememberToken: $this->rememberToken,
            avatar: $this->avatar,
            emailVerifiedAt: $this->emailVerifiedAt,
        );
    }

    /**
     * Create a new instance with updated password (hashed).
     */
    public function withPassword(string $hashedPassword): self
    {
        return new self(
            id: $this->id,
            tenantId: $this->tenantId,
            email: $this->email,
            name: $this->name,
            role: $this->role,
            password: $hashedPassword,
            rememberToken: $this->rememberToken,
            avatar: $this->avatar,
            emailVerifiedAt: $this->emailVerifiedAt,
        );
    }

    /**
     * Create a new instance with refreshed remember token.
     */
    public function withRememberToken(?string $token): self
    {
        return new self(
            id: $this->id,
            tenantId: $this->tenantId,
            email: $this->email,
            name: $this->name,
            role: $this->role,
            password: $this->password,
            rememberToken: $token,
            avatar: $this->avatar,
            emailVerifiedAt: $this->emailVerifiedAt,
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // QUERY METHODS
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Check if user is a seller.
     */
    public function isSeller(): bool
    {
        return $this->role === 'seller';
    }

    /**
     * Check if user is a customer.
     */
    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    /**
     * Check if email has been verified.
     */
    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }
}
