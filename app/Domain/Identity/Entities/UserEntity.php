<?php

declare(strict_types=1);

namespace App\Domain\Identity\Entities;

use App\Domain\Shared\ValueObjects\EmailAddress;
use DateTimeImmutable;

/**
 * UserEntity - Pure Domain Entity for User
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  DEFENSIVE DOMAIN MODELING:                                             ║
 * ║  - NO Carbon Dependency (Native PHP DateTimeImmutable).                 ║
 * ║  - Strict Constructor (No magic casting).                               ║
 * ║  - Audit Fields (createdAt, updatedAt) enforced.                        ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final readonly class UserEntity
{
    public function __construct(
        public int $id,
        public int $tenantId,
        public EmailAddress $email,
        public string $name,
        public string $role,
        public DateTimeImmutable $createdAt,
        public ?DateTimeImmutable $updatedAt = null,
        public ?string $password = null,
        public ?string $rememberToken = null,
        public ?string $avatar = null,
        public ?DateTimeImmutable $emailVerifiedAt = null,
    ) {}

    // ═══════════════════════════════════════════════════════════════════════
    // WITHER METHODS (Immutable State Transitions)
    // ═══════════════════════════════════════════════════════════════════════

    public function withEmailVerified(?DateTimeImmutable $date): self
    {
        return new self(
            id: $this->id,
            tenantId: $this->tenantId,
            email: $this->email,
            name: $this->name,
            role: $this->role,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(), // Update timestamp on change
            password: $this->password,
            rememberToken: $this->rememberToken,
            avatar: $this->avatar,
            emailVerifiedAt: $date,
        );
    }

    public function withAvatar(?string $avatarUrl): self
    {
        return new self(
            id: $this->id,
            tenantId: $this->tenantId,
            email: $this->email,
            name: $this->name,
            role: $this->role,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            password: $this->password,
            rememberToken: $this->rememberToken,
            avatar: $avatarUrl,
            emailVerifiedAt: $this->emailVerifiedAt,
        );
    }

    public function withName(string $name): self
    {
        return new self(
            id: $this->id,
            tenantId: $this->tenantId,
            email: $this->email,
            name: $name,
            role: $this->role,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            password: $this->password,
            rememberToken: $this->rememberToken,
            avatar: $this->avatar,
            emailVerifiedAt: $this->emailVerifiedAt,
        );
    }

    public function withEmail(EmailAddress $email): self
    {
        return new self(
            id: $this->id,
            tenantId: $this->tenantId,
            email: $email,
            name: $this->name,
            role: $this->role,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            password: $this->password,
            rememberToken: $this->rememberToken,
            avatar: $this->avatar,
            emailVerifiedAt: $this->emailVerifiedAt,
        );
    }

    public function withPassword(string $hashedPassword): self
    {
        return new self(
            id: $this->id,
            tenantId: $this->tenantId,
            email: $this->email,
            name: $this->name,
            role: $this->role,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
            password: $hashedPassword,
            rememberToken: $this->rememberToken,
            avatar: $this->avatar,
            emailVerifiedAt: $this->emailVerifiedAt,
        );
    }

    public function withRememberToken(?string $token): self
    {
        return new self(
            id: $this->id,
            tenantId: $this->tenantId,
            email: $this->email,
            name: $this->name,
            role: $this->role,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(), // Token refresh implies update
            password: $this->password,
            rememberToken: $token,
            avatar: $this->avatar,
            emailVerifiedAt: $this->emailVerifiedAt,
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // QUERY METHODS
    // ═══════════════════════════════════════════════════════════════════════

    public function isSeller(): bool
    {
        return $this->role === 'seller';
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt !== null;
    }
}
