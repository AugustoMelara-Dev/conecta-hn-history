<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Exceptions;

use RuntimeException;

final class MenuSectionNotFoundException extends RuntimeException
{
    public static function withId(int $menuSectionId): self
    {
        return new self("Menu section not found: {$menuSectionId}");
    }
}
