<?php

declare(strict_types=1);

namespace App\Application\Identity\Validators\Rules;

use App\Application\Shared\Contracts\ValidationRuleInterface;
use App\Domain\TenantGovernance\Repositories\TenantRepositoryInterface;
use Closure;

/**
 * UniqueTenantSlugRule - Optimized for Performance
 */
final readonly class UniqueTenantSlugRule implements ValidationRuleInterface
{
    public function __construct(
        private TenantRepositoryInterface $tenantRepository
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        if ($this->tenantRepository->existsBySlug($value)) {
            $fail('La URL del negocio ya está en uso.');
        }
    }
}
