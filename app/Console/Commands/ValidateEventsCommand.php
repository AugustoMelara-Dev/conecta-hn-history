<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use ReflectionClass;
use Symfony\Component\Finder\Finder;

/**
 * ValidateEventsCommand - ADR-002 Compliance Scanner
 *
 * Used by CI/CD to BLOCK PRs that violate event architecture standards.
 *
 * Usage:
 *   php artisan app:validate-events                    # All checks
 *   php artisan app:validate-events --check=final-readonly
 *   php artisan app:validate-events --check=interface
 *   php artisan app:validate-events --check=serializable
 *   php artisan app:validate-events --check=no-eloquent
 *   php artisan app:validate-events --check=no-serializes-models
 */
class ValidateEventsCommand extends Command
{
    protected $signature = 'app:validate-events 
                            {--check=all : Check type (all, final-readonly, interface, serializable, no-eloquent, no-serializes-models)}';

    protected $description = 'Validate all domain events comply with ADR-002 standards';

    protected array $violations = [];

    protected int $eventsChecked = 0;

    public function handle(): int
    {
        $checkType = $this->option('check');
        $this->violations = [];

        $events = $this->findAllEvents();

        if (empty($events)) {
            $this->warn('No events found in app/Events/');

            return 0;
        }

        $this->info('🔍 Scanning '.count($events)." events for ADR-002 compliance...\n");

        foreach ($events as $eventClass) {
            // Skip Broadcast events (different rules)
            if (str_contains($eventClass, '\\Broadcast\\')) {
                continue;
            }

            $this->eventsChecked++;
            $reflection = new ReflectionClass($eventClass);

            if ($checkType === 'all' || $checkType === 'final-readonly') {
                $this->checkFinalReadonly($eventClass, $reflection);
            }

            if ($checkType === 'all' || $checkType === 'interface') {
                $this->checkInterface($eventClass, $reflection);
            }

            if ($checkType === 'all' || $checkType === 'serializable') {
                $this->checkSerializable($eventClass, $reflection);
            }

            if ($checkType === 'all' || $checkType === 'no-eloquent') {
                $this->checkNoEloquent($eventClass, $reflection);
            }

            if ($checkType === 'all' || $checkType === 'no-serializes-models') {
                $this->checkNoSerializesModels($eventClass, $reflection);
            }
        }

        // Report results
        if (empty($this->violations)) {
            $this->newLine();
            $this->info("✅ All {$this->eventsChecked} events pass ADR-002 validation");
            $this->newLine();

            return 0;
        }

        $this->newLine();
        $this->error('❌ ADR-002 VALIDATION FAILED');
        $this->error('   '.count($this->violations)." violation(s) found in {$this->eventsChecked} events:");
        $this->newLine();

        foreach ($this->violations as $violation) {
            $this->line("  <fg=red>•</> {$violation}");
        }

        $this->newLine();
        $this->line('📚 See: docs/architecture/decisions/ADR-002-domain-events-governance.md');
        $this->newLine();

        return 1;
    }

    private function findAllEvents(): array
    {
        $eventsPath = app_path('Events');

        if (! is_dir($eventsPath)) {
            return [];
        }

        $finder = new Finder;
        $finder->in($eventsPath)->name('*.php')->files();

        $events = [];
        foreach ($finder as $file) {
            $relativePath = $file->getRelativePathname();
            $className = str_replace(['/', '.php'], ['\\', ''], $relativePath);
            $fullClass = 'App\\Events\\'.$className;

            if (class_exists($fullClass) && ! interface_exists($fullClass)) {
                $events[] = $fullClass;
            }
        }

        return $events;
    }

    private function checkFinalReadonly(string $eventClass, ReflectionClass $reflector): void
    {
        $shortName = $reflector->getShortName();

        if (! $reflector->isFinal()) {
            $this->violations[] = "{$shortName}: Missing 'final' keyword";
        }

        if (! $reflector->isReadOnly()) {
            $this->violations[] = "{$shortName}: Missing 'readonly' keyword";
        }

        if ($this->option('verbose')) {
            $this->line("  Checked final/readonly: {$shortName}");
        }
    }

    private function checkInterface(string $eventClass, ReflectionClass $reflector): void
    {
        $shortName = $reflector->getShortName();
        $interfaces = $reflector->getInterfaceNames();

        $hasDomainEvent = in_array('App\\Domain\\Shared\\Contracts\\Events\\DomainEvent', $interfaces, true);
        $hasAuditEvent = in_array('App\\Contracts\\Events\\AuditEvent', $interfaces, true);

        if (! $hasDomainEvent && ! $hasAuditEvent) {
            $this->violations[] = "{$shortName}: Must implement DomainEvent or AuditEvent";
        }

        if ($this->option('verbose')) {
            $this->line("  Checked interface: {$shortName}");
        }
    }

    private function checkSerializable(string $eventClass, ReflectionClass $reflector): void
    {
        $shortName = $reflector->getShortName();
        $interfaces = $reflector->getInterfaceNames();

        // AuditEvents use toAuditLog(), DomainEvents use toPrimitives()
        $hasAuditEvent = in_array('App\\Contracts\\Events\\AuditEvent', $interfaces, true);

        $hasToPrimitives = $reflector->hasMethod('toPrimitives');
        $hasToAuditLog = $reflector->hasMethod('toAuditLog');

        if (! $hasToPrimitives && ! $hasToAuditLog) {
            $method = $hasAuditEvent ? 'toAuditLog()' : 'toPrimitives()';
            $this->violations[] = "{$shortName}: Missing {$method} method";
        }

        if ($this->option('verbose')) {
            $this->line("  Checked serialization: {$shortName}");
        }
    }

    private function checkNoEloquent(string $eventClass, ReflectionClass $reflector): void
    {
        $shortName = $reflector->getShortName();

        // Check typed properties
        foreach ($reflector->getProperties() as $prop) {
            $type = $prop->getType();

            if ($type === null) {
                continue;
            }

            if (! $type instanceof \ReflectionNamedType) {
                continue;
            }

            $typeName = $type->getName();

            if (class_exists($typeName) && is_subclass_of($typeName, 'Illuminate\\Database\\Eloquent\\Model')) {
                $this->violations[] = "{$shortName}: Property \${$prop->getName()} is Eloquent Model ({$typeName})";
            }
        }

        // Also check file content for common Eloquent patterns (untyped properties)
        $filename = $reflector->getFileName();
        if ($filename && file_exists($filename)) {
            $content = file_get_contents($filename);

            // Detect common Eloquent model property patterns
            $eloquentPatterns = [
                '/use\s+SerializesModels\s*;/' => 'Uses SerializesModels trait',
                '/\$this->(order|user|customer|product|payment|tenant|invoice)\s*[,;\)]/' => 'References Eloquent model property',
            ];

            foreach ($eloquentPatterns as $pattern => $message) {
                if (preg_match($pattern, $content)) {
                    $this->violations[] = "{$shortName}: {$message}";
                    break; // Only report first pattern match
                }
            }
        }

        if ($this->option('verbose')) {
            $this->line("  Checked no-eloquent: {$shortName}");
        }
    }

    private function checkNoSerializesModels(string $eventClass, ReflectionClass $reflector): void
    {
        $shortName = $reflector->getShortName();
        $traits = $reflector->getTraitNames();

        if (in_array('Illuminate\\Queue\\SerializesModels', $traits, true)) {
            $this->violations[] = "{$shortName}: Uses SerializesModels trait (not allowed)";
        }

        if ($this->option('verbose')) {
            $this->line("  Checked no-serializes-models: {$shortName}");
        }
    }
}
