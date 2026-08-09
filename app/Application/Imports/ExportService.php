<?php

declare(strict_types=1);

namespace App\Application\Imports;

use App\Application\Imports\Exports\ContactsExport;
use App\Application\Imports\Exports\CustomersExport;

/**
 * ExportService - Registry de export classes por entity.
 */
class ExportService
{
    /** @var array<string, callable> */
    private array $registry = [];

    public function __construct()
    {
        $this->registry = [
            'contacts' => $this->makeFactory(ContactsExport::class),
            'customers' => $this->makeFactory(CustomersExport::class),
        ];
    }

    private function makeFactory(string $class): callable
    {
        return fn (string $tenantId, array $filters = []) => new $class($tenantId, $filters);
    }

    public function has(string $entity): bool
    {
        return isset($this->registry[$entity]);
    }

    public function getExporterClass(string $entity, string $tenantId, array $filters = []): object
    {
        if (! $this->has($entity)) {
            throw new \InvalidArgumentException("No exporter registered for: {$entity}");
        }

        return ($this->registry[$entity])($tenantId, $filters);
    }

    public function register(string $entity, callable $factory): void
    {
        $this->registry[$entity] = $factory;
    }

    /**
     * @return array<int, string>
     */
    public function entities(): array
    {
        return array_keys($this->registry);
    }
}
