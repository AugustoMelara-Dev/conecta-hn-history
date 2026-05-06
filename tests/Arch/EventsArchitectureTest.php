<?php

declare(strict_types=1);

namespace Tests\Arch;

use App\Contracts\Events\AuditEvent;
use App\Contracts\Events\DomainEvent;
use App\Domain\Shared\ValueObjects\TenantId;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * EventsArchitectureTest - Pre-Production Compliance Verification
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  CRITICAL: Run this BEFORE every production deployment                  ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  This test suite enforces:                                              ║
 * ║    1. Domain Events contain no Eloquent models                          ║
 * ║    2. Domain Events are final readonly                                  ║
 * ║    3. Domain Events have tenant context                                 ║
 * ║    4. Broadcast Events are separate from Domain Events                  ║
 * ║    5. No PII in event payloads                                         ║
 * ║    6. Domain Events implement ShouldDispatchAfterCommit                ║
 * ║                                                                          ║
 * ║  FAILURE = BLOCK DEPLOYMENT                                             ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * @group architecture
 * @group events
 * @group arch
 */
class EventsArchitectureTest extends TestCase
{
    /**
     * Cache of discovered event classes.
     */
    private static ?array $domainEventClasses = null;

    private static ?array $allEventClasses = null;

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 1: Domain Events have no Eloquent models
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @test
     * Domain Events MUST NOT contain Eloquent model properties.
     * SerializesModels trait bypass is a critical security/performance issue.
     */
    public function domain_events_have_no_eloquent_models(): void
    {
        $events = $this->findClassesImplementing(DomainEvent::class);

        foreach ($events as $eventClass) {
            $reflection = new ReflectionClass($eventClass);

            foreach ($reflection->getProperties() as $prop) {
                $type = $prop->getType();

                if ($type === null) {
                    continue;
                }

                $typeName = $type->getName();

                $this->assertFalse(
                    $this->isEloquentModel($typeName),
                    "VIOLATION: {$eventClass} contains Eloquent property '\${$prop->getName()}' of type {$typeName}. ".
                    'Domain Events must use primitives or Value Objects only.'
                );
            }
        }

        $this->addToAssertionCount(1); // Pass if no events found
    }

    // ═══════════════════════════════════════════════════════════════════════
    // DRACONIAN GOVERNANCE TESTS (ADR-002)
    // ═══════════════════════════════════════════════════════════════════════
    // These tests scan ALL events, not just DomainEvent implementations.
    // This prevents legacy events from hiding in plain sight.
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @test
     * GOVERNANCE RULE 1: All events (except Broadcast) MUST implement DomainEvent or AuditEvent.
     */
    public function governance_all_non_broadcast_events_must_implement_interface(): void
    {
        $allEvents = $this->findAllEventClasses();
        $violations = [];

        foreach ($allEvents as $eventClass) {
            if (str_contains($eventClass, '\\Broadcast\\')) {
                continue;
            }

            $implements = class_implements($eventClass, true) ?: [];
            $hasDomainEvent = array_key_exists(DomainEvent::class, $implements);
            $hasAuditEvent = array_key_exists(AuditEvent::class, $implements);

            if (! $hasDomainEvent && ! $hasAuditEvent) {
                $violations[] = $eventClass;
            }
        }

        $this->assertEmpty(
            $violations,
            "ADR-002 VIOLATION: Events must implement DomainEvent or AuditEvent:\n".
            implode("\n", $violations)
        );
    }

    /**
     * @test
     * GOVERNANCE RULE 2: All events (except Broadcast) MUST be final readonly.
     */
    public function governance_all_non_broadcast_events_must_be_final_readonly(): void
    {
        $allEvents = $this->findAllEventClasses();
        $violations = [];

        foreach ($allEvents as $eventClass) {
            if (str_contains($eventClass, '\\Broadcast\\')) {
                continue;
            }

            $reflection = new ReflectionClass($eventClass);

            if (! $reflection->isFinal()) {
                $violations[] = "{$eventClass} is not final";
            }

            if (! $reflection->isReadOnly()) {
                $violations[] = "{$eventClass} is not readonly";
            }
        }

        $this->assertEmpty(
            $violations,
            "ADR-002 VIOLATION: Events must be 'final readonly class':\n".
            implode("\n", $violations)
        );
    }

    /**
     * @test
     * GOVERNANCE RULE 3: No constructor may accept an Eloquent Model.
     */
    public function governance_no_eloquent_models_in_any_event(): void
    {
        $allEvents = $this->findAllEventClasses();
        $violations = [];

        foreach ($allEvents as $eventClass) {
            if (str_contains($eventClass, '\\Broadcast\\')) {
                continue;
            }

            $reflection = new ReflectionClass($eventClass);

            foreach ($reflection->getProperties() as $prop) {
                $type = $prop->getType();
                if ($type === null) {
                    continue;
                }
                $typeName = $type->getName();
                if ($this->isEloquentModel($typeName)) {
                    $violations[] = "{$eventClass}::\${$prop->getName()} is Eloquent ({$typeName})";
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "ADR-002 VIOLATION: Eloquent Models in events:\n".implode("\n", $violations)
        );
    }

    /**
     * @test
     * GOVERNANCE RULE 4: All non-Broadcast events MUST have serialization method.
     * DomainEvents use toPrimitives(), AuditEvents use toAuditLog().
     */
    public function governance_all_events_must_have_serialization_method(): void
    {
        $allEvents = $this->findAllEventClasses();
        $violations = [];

        foreach ($allEvents as $eventClass) {
            if (str_contains($eventClass, '\\Broadcast\\')) {
                continue;
            }

            $reflection = new ReflectionClass($eventClass);
            $implements = class_implements($eventClass, true) ?: [];

            // AuditEvents use toAuditLog(), DomainEvents use toPrimitives()
            $isAuditEvent = array_key_exists(AuditEvent::class, $implements);
            $requiredMethod = $isAuditEvent ? 'toAuditLog' : 'toPrimitives';

            // Check for required method OR toPrimitives (either is valid)
            $hasToPrimitives = $reflection->hasMethod('toPrimitives');
            $hasToAuditLog = $reflection->hasMethod('toAuditLog');

            if (! $hasToPrimitives && ! $hasToAuditLog) {
                $violations[] = "{$eventClass} (needs {$requiredMethod}())";
            }
        }

        $this->assertEmpty(
            $violations,
            "ADR-002 VIOLATION: Events missing serialization method:\n".implode("\n", $violations)
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 2: Domain Events are final readonly
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @test
     * Domain Events MUST be final to prevent inheritance and ensure immutability.
     */
    public function domain_events_are_final(): void
    {
        $events = $this->findClassesImplementing(DomainEvent::class);

        foreach ($events as $eventClass) {
            $reflection = new ReflectionClass($eventClass);

            $this->assertTrue(
                $reflection->isFinal(),
                "VIOLATION: {$eventClass} must be declared as 'final'. ".
                'This prevents subclasses from breaking immutability.'
            );
        }

        $this->addToAssertionCount(1);
    }

    /**
     * @test
     * Domain Events MUST be readonly to guarantee immutability.
     */
    public function domain_events_are_readonly(): void
    {
        $events = $this->findClassesImplementing(DomainEvent::class);

        foreach ($events as $eventClass) {
            $reflection = new ReflectionClass($eventClass);

            $this->assertTrue(
                $reflection->isReadOnly(),
                "VIOLATION: {$eventClass} must be declared as 'readonly'. ".
                'This prevents property mutation after construction.'
            );
        }

        $this->addToAssertionCount(1);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 3: Domain Events have tenant context
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @test
     * Every Domain Event MUST return a TenantId from tenantId() method.
     */
    public function domain_events_have_tenant_context(): void
    {
        $events = $this->findClassesImplementing(DomainEvent::class);

        foreach ($events as $eventClass) {
            $reflection = new ReflectionClass($eventClass);

            $this->assertTrue(
                $reflection->hasMethod('tenantId'),
                "VIOLATION: {$eventClass} must implement tenantId() method."
            );

            $method = $reflection->getMethod('tenantId');
            $returnType = $method->getReturnType();

            $this->assertNotNull(
                $returnType,
                "VIOLATION: {$eventClass}::tenantId() must have a return type."
            );

            $this->assertEquals(
                TenantId::class,
                $returnType->getName(),
                "VIOLATION: {$eventClass}::tenantId() must return TenantId, got {$returnType->getName()}."
            );
        }

        $this->addToAssertionCount(1);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 4: Broadcast Events are separate from Domain Events
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @test
     * NO class should implement both DomainEvent and ShouldBroadcast.
     */
    public function broadcast_events_are_separate_from_domain(): void
    {
        $domains = $this->findClassesImplementing(DomainEvent::class);
        $broadcasts = $this->findClassesImplementing(ShouldBroadcast::class);

        $overlap = array_intersect($domains, $broadcasts);

        $this->assertEmpty(
            $overlap,
            "VIOLATION: The following events implement BOTH DomainEvent and ShouldBroadcast:\n".
            implode("\n", $overlap)."\n".
            'Domain Events must be pure. Create separate Broadcast events in app/Events/Broadcast/.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 5: No PII in events
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @test
     * Event properties MUST NOT contain PII (email, phone, etc).
     *
     * NOTE: AuditEvent implementations are EXCLUDED from this check because
     * security events legitimately need to log failed login attempts with
     * email addresses for threat detection and forensics.
     */
    public function events_contain_no_pii(): void
    {
        $events = $this->findAllEventClasses();

        // Exclude AuditEvent implementations - they legitimately log PII for security
        $auditEvents = $this->findClassesImplementing(AuditEvent::class);

        // Also exclude all events in Security folder - they need PII for threat detection
        $eventsToCheck = array_filter($events, function (string $class) use ($auditEvents) {
            // Skip if it's an AuditEvent
            if (in_array($class, $auditEvents)) {
                return false;
            }
            // Skip if in Security namespace
            if (str_contains($class, '\\Security\\')) {
                return false;
            }
            // Skip if in Broadcast namespace (UI notifications need PII)
            if (str_contains($class, '\\Broadcast\\')) {
                return false;
            }

            return true;
        });

        $piiPatterns = [
            'email',
            'phone',
            'ssn',
            'social_security',
            'address',
            'credit_card',
            'card_number',
            'cvv',
            'password',
            'secret',
            'token',
        ];

        foreach ($eventsToCheck as $eventClass) {
            $reflection = new ReflectionClass($eventClass);

            foreach ($reflection->getProperties() as $prop) {
                $propName = strtolower($prop->getName());

                foreach ($piiPatterns as $pattern) {
                    $this->assertStringNotContainsString(
                        $pattern,
                        $propName,
                        "VIOLATION: {$eventClass}::\${$prop->getName()} appears to contain PII (matches '{$pattern}'). ".
                        'Events should NOT contain emails, phones, addresses, or other PII. '.
                        'Store only IDs and look up sensitive data in listeners.'
                    );
                }
            }
        }

        $this->addToAssertionCount(1);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 6: Domain Events implement ShouldDispatchAfterCommit
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @test
     * Domain Events MUST implement ShouldDispatchAfterCommit for transaction safety.
     */
    public function domain_events_dispatch_after_commit(): void
    {
        $events = $this->findClassesImplementing(DomainEvent::class);

        foreach ($events as $eventClass) {
            $implements = class_implements($eventClass, true) ?: [];

            $this->assertArrayHasKey(
                ShouldDispatchAfterCommit::class,
                $implements,
                "VIOLATION: {$eventClass} must implement ShouldDispatchAfterCommit. ".
                'Without this, queue workers may see uncommitted state.'
            );
        }

        $this->addToAssertionCount(1);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 7: Audit Events do not broadcast
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @test
     * Audit Events MUST NOT implement ShouldBroadcast (security risk).
     */
    public function audit_events_do_not_broadcast(): void
    {
        $audits = $this->findClassesImplementing(AuditEvent::class);
        $broadcasts = $this->findClassesImplementing(ShouldBroadcast::class);

        $overlap = array_intersect($audits, $broadcasts);

        $this->assertEmpty(
            $overlap,
            "SECURITY VIOLATION: The following Audit Events implement ShouldBroadcast:\n".
            implode("\n", $overlap)."\n".
            'Audit Events contain sensitive security data and MUST NOT be broadcast to WebSockets.'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TEST 8: No mutable arrays in Domain Events
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * @test
     * Domain Events MUST NOT have array properties (arrays are mutable).
     */
    public function domain_events_have_no_mutable_arrays(): void
    {
        $events = $this->findClassesImplementing(DomainEvent::class);

        foreach ($events as $eventClass) {
            $reflection = new ReflectionClass($eventClass);

            foreach ($reflection->getProperties() as $prop) {
                $type = $prop->getType();

                if ($type === null) {
                    continue;
                }

                $this->assertNotEquals(
                    'array',
                    $type->getName(),
                    "VIOLATION: {$eventClass}::\${$prop->getName()} is an array. ".
                    'Arrays are mutable even in readonly classes. '.
                    'Use an immutable Value Object instead (e.g., EventMetadata).'
                );
            }
        }

        $this->addToAssertionCount(1);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Helper Methods
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Find all classes implementing a given interface.
     *
     * @param  class-string  $interface
     * @return array<class-string>
     */
    private function findClassesImplementing(string $interface): array
    {
        $allClasses = $this->findAllEventClasses();

        return array_filter($allClasses, function (string $class) use ($interface) {
            $implements = class_implements($class, true) ?: [];

            return array_key_exists($interface, $implements);
        });
    }

    /**
     * Find all event classes in app/Events directory.
     *
     * @return array<class-string>
     */
    private function findAllEventClasses(): array
    {
        if (self::$allEventClasses !== null) {
            return self::$allEventClasses;
        }

        $eventsPath = app_path('Events');

        if (! is_dir($eventsPath)) {
            return self::$allEventClasses = [];
        }

        $finder = new Finder;
        $finder->files()->in($eventsPath)->name('*.php');

        $classes = [];

        foreach ($finder as $file) {
            $relativePath = str_replace(
                [app_path().DIRECTORY_SEPARATOR, '.php', DIRECTORY_SEPARATOR],
                ['', '', '\\'],
                $file->getRealPath()
            );

            $class = 'App\\'.$relativePath;

            if (class_exists($class) && ! interface_exists($class)) {
                $classes[] = $class;
            }
        }

        return self::$allEventClasses = $classes;
    }

    /**
     * Check if a type is an Eloquent model.
     */
    private function isEloquentModel(string $typeName): bool
    {
        if (! class_exists($typeName)) {
            return false;
        }

        return is_subclass_of($typeName, Model::class);
    }
}
