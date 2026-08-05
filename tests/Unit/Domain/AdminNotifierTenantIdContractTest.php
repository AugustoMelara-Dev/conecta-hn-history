<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Contracts\AdminNotifier;
use App\Domain\Shared\Contracts\AdminNotifierInterface;
use App\Infrastructure\Notifications\FilamentAdminNotifier;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class AdminNotifierTenantIdContractTest extends TestCase
{
    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function notifierMethods(): iterable
    {
        foreach ([AdminNotifier::class, AdminNotifierInterface::class, FilamentAdminNotifier::class] as $class) {
            yield "{$class} order" => [$class, 'notifyNewOrder'];
            yield "{$class} payment" => [$class, 'notifyPaymentUpdate'];
        }
    }

    #[Test]
    #[DataProvider('notifierMethods')]
    public function tenant_identifier_is_a_string(string $class, string $method): void
    {
        $parameter = (new ReflectionMethod($class, $method))->getParameters()[0];

        self::assertSame('string', (string) $parameter->getType());
    }
}
