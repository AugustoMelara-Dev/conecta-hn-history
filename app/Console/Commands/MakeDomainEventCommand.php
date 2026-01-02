<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;

/**
 * MakeDomainEventCommand - Generate DDD-Compliant Domain Events
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  GOVERNANCE: Developer Experience (El Camino Fácil)                     ║
 * ╠══════════════════════════════════════════════════════════════════════════╣
 * ║                                                                          ║
 * ║  ADR-002 mandates all Domain Events follow strict standards.             ║
 * ║  This generator creates compliant events automatically.                  ║
 * ║                                                                          ║
 * ║  USAGE:                                                                 ║
 * ║    php artisan make:domain-event OrderCancelled --bounded=Order         ║
 * ║    php artisan make:domain-event PaymentFailed --bounded=Payment        ║
 * ║                                                                          ║
 * ║  GENERATED EVENT INCLUDES:                                              ║
 * ║    ✅ final readonly class                                              ║
 * ║    ✅ implements DomainEvent                                            ║
 * ║    ✅ use HasDomainMetadata                                             ║
 * ║    ✅ TenantId in constructor                                           ║
 * ║    ✅ toPrimitives() method                                             ║
 * ║    ✅ fromPrimitives() method                                           ║
 * ║    ✅ static create() factory                                           ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
class MakeDomainEventCommand extends GeneratorCommand
{
    /**
     * The console command signature.
     */
    protected $signature = 'make:domain-event 
                            {name : The name of the event (e.g., OrderCancelled)}
                            {--bounded= : The bounded context folder (e.g., Order, Payment)}
                            {--audit : Generate an AuditEvent instead of DomainEvent}';

    /**
     * The console command description.
     */
    protected $description = 'Create a new DDD-compliant Domain Event class';

    /**
     * The type of class being generated.
     */
    protected $type = 'Event';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        if ($this->option('audit')) {
            return $this->resolveStubPath('/stubs/audit-event.stub');
        }

        return $this->resolveStubPath('/stubs/domain-event.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     */
    protected function resolveStubPath(string $stub): string
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__ . $stub;
    }

    /**
     * Get the default namespace for the class.
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        $bounded = $this->option('bounded');

        if ($bounded) {
            return $rootNamespace . '\\Events\\' . Str::studly($bounded);
        }

        return $rootNamespace . '\\Events';
    }

    /**
     * Build the class with the given name.
     */
    protected function buildClass($name): string
    {
        $stub = parent::buildClass($name);

        // Replace additional placeholders
        $stub = str_replace(
            ['{{ aggregateName }}', '{{aggregateName}}'],
            $this->getAggregateName(),
            $stub
        );

        $stub = str_replace(
            ['{{ aggregateNameLower }}', '{{aggregateNameLower}}'],
            Str::camel($this->getAggregateName()),
            $stub
        );

        return $stub;
    }

    /**
     * Get the aggregate name from the bounded context or event name.
     */
    protected function getAggregateName(): string
    {
        if ($bounded = $this->option('bounded')) {
            return Str::singular(Str::studly($bounded));
        }

        // Extract from event name (e.g., OrderCancelled -> Order)
        $name = $this->getNameInput();
        
        // Common suffixes to remove
        $suffixes = ['Created', 'Updated', 'Deleted', 'Cancelled', 'Paid', 'Shipped', 'Refunded', 'Approved', 'Rejected'];
        
        foreach ($suffixes as $suffix) {
            if (Str::endsWith($name, $suffix)) {
                return Str::replaceLast($suffix, '', $name);
            }
        }

        return 'Entity';
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [
            ['name', \Symfony\Component\Console\Input\InputArgument::REQUIRED, 'The name of the event'],
        ];
    }

    /**
     * Execute the console command.
     */
    public function handle(): ?bool
    {
        $result = parent::handle();

        if ($result !== false) {
            $this->components->info('Remember: All Domain Events must comply with ADR-002.');
            $this->components->bulletList([
                'final readonly class',
                'implements DomainEvent or AuditEvent',
                'use HasDomainMetadata trait',
                'toPrimitives() for serialization',
                'No Eloquent models in constructor',
            ]);
        }

        return $result;
    }
}
