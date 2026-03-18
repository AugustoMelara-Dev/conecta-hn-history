<?php

declare(strict_types=1);

/**
 * ScalabilityComplianceTest - Serialization Hygiene Governance
 *
 * Enforces:
 * - SCALE-01: Application Commands use only primitives or allowlisted VOs
 * - SCALE-02: No services, repositories, or heavy object graphs in Commands
 *
 * Prevents "Fat Event" anti-pattern that degrades Redis/SQS throughput.
 * Commands must remain lightweight DTOs for safe queue serialization.
 */
test('scalability - commands rely on primitives for fast serialization', function () {
    // Mandate from Scalability Report: Commands must use primitive types or lightweight VOs.
    // This prevents "Fat Event" anti-patterns and ensures Redis/SQS throughput.
    $allowedTypes = [
        'Carbon\Carbon',
        'Carbon\CarbonImmutable',
        'Illuminate\Support\Collection',
        'Ramsey\Uuid\Uuid',
        'Ramsey\Uuid\UuidInterface',
        'DateTimeImmutable',
        'DateTimeInterface',
        // Domain DTOs composed of primitives only (verified serialization-safe)
        'App\Domain\Shared\DTOs\FileTransferObject',
    ];

    // Exhaustive registry of Application Command classes
    $commandClasses = [
        \App\Application\Auth\Commands\DeleteUserCommand::class,
        \App\Application\Auth\Commands\GenerateOtpCommand::class,
        \App\Application\Auth\Commands\LoginViaSocialProviderCommand::class,
        \App\Application\Auth\Commands\ResetUserPasswordCommand::class,
        \App\Application\Auth\Commands\UpdateUserPasswordCommand::class,
        \App\Application\Auth\Commands\VerifyOtpCommand::class,
        \App\Application\Billing\Commands\ReportPaymentCommand::class,
        \App\Application\Billing\Commands\RequestUpgradeCommand::class,
        \App\Application\Identity\Commands\RegisterUserCommand::class,
        \App\Application\Interactions\Commands\RecordInteractionCommand::class,
        \App\Application\Payments\Commands\HandlePaymentWebhookCommand::class,
        \App\Application\Products\Commands\CreateProductCommand::class,
        \App\Application\Products\Commands\DeleteProductCommand::class,
        \App\Application\Products\Commands\ToggleProductVisibilityCommand::class,
        \App\Application\Products\Commands\UpdateProductCommand::class,
        \App\Application\Reviews\Commands\SubmitReviewCommand::class,
        \App\Application\Sales\Commands\CancelOrderCommand::class,
        \App\Application\Sales\Commands\CreateOrderCommand::class,
        \App\Application\Tenant\Commands\EnhanceTenantProfileCommand::class,
        \App\Application\Tenant\Commands\RegisterTenantCommand::class,
        \App\Application\Tenant\Commands\UpdateLandingPageCommand::class,
        \App\Application\Tenant\Commands\UpdateTenantBrandingCommand::class,
        \App\Application\Tenant\Commands\UpdateTenantScheduleCommand::class,
        \App\Application\Identity\Commands\UpdateUserProfileCommand::class,
        \App\Application\Identity\Commands\ReactivateUserCommand::class,
    ];

    expect(count($commandClasses))->toBeGreaterThanOrEqual(10, 'Registry must contain at least 10 Commands');

    foreach ($commandClasses as $className) {
        $reflection = new ReflectionClass($className);

        foreach ($reflection->getProperties() as $property) {
            $type = $property->getType();

            // Allow untyped (mixed) for legacy compatibility
            if (! $type) {
                continue;
            }

            // Collect all named types (handles Named, Union, and Intersection)
            $namedTypes = [];
            if ($type instanceof ReflectionUnionType || $type instanceof ReflectionIntersectionType) {
                foreach ($type->getTypes() as $subType) {
                    if ($subType instanceof ReflectionNamedType) {
                        $namedTypes[] = $subType;
                    }
                }
            } elseif ($type instanceof ReflectionNamedType) {
                $namedTypes[] = $type;
            }

            foreach ($namedTypes as $namedType) {
                if ($namedType->isBuiltin()) {
                    continue;
                }

                $typeName = $namedType->getName();

                // PHP Enums (backed by string/int) are lightweight VOs — safe for serialization
                if (is_a($typeName, \UnitEnum::class, true)) {
                    continue;
                }

                // Check explicit allowlist
                $isAllowed = false;
                foreach ($allowedTypes as $allowed) {
                    if ($typeName === $allowed || is_subclass_of($typeName, $allowed)) {
                        $isAllowed = true;
                        break;
                    }
                }

                if (! $isAllowed) {
                    test()->fail(
                        "Scalability Violation: Command [{$className}] property [\${$property->getName()}] ".
                        "uses heavy/unknown type [{$typeName}]. Use primitives (string, int) or IDs only."
                    );
                }
            }
        }
    }
});
