<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant;

use App\Domain\Tenant\Contracts\SlugAvailabilityCheckerInterface;
use App\Infrastructure\Tenant\Policies\SlugPolicy;
use PHPUnit\Framework\TestCase;

final class SlugPolicyTest extends TestCase
{
    public function test_slug_availability_uses_injected_checker_after_reserved_firewall(): void
    {
        $checker = new class implements SlugAvailabilityCheckerInterface
        {
            public array $checked = [];

            public function isAvailable(string $slug): bool
            {
                $this->checked[] = $slug;

                return $slug === 'cafe-vito';
            }
        };

        self::assertFalse(SlugPolicy::isSlugAvailable('admin', $checker));
        self::assertTrue(SlugPolicy::isSlugAvailable('cafe-vito', $checker));
        self::assertSame(['cafe-vito'], $checker->checked);
    }

    public function test_suggestions_use_injected_checker_without_database_coupling(): void
    {
        $checker = new class implements SlugAvailabilityCheckerInterface
        {
            public function isAvailable(string $slug): bool
            {
                return in_array($slug, ['cafe-hn', 'cafe-oficial'], true);
            }
        };

        self::assertSame(
            ['cafe-hn', 'cafe-oficial'],
            SlugPolicy::generateSuggestions('cafe', 2, $checker)
        );
    }
}
