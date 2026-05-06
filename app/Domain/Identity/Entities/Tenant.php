<?php

declare(strict_types=1);

namespace App\Domain\Identity\Entities;

use DateTimeImmutable;

/**
 * Class Tenant
 *
 * Use Case Agnostic Domain Entity.
 * Represents a workspace or organization. Pure PHP object.
 */
final class Tenant
{
    public function __construct(
        private string $id,
        private string $name,
        private DateTimeImmutable $createdAt
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }
}
