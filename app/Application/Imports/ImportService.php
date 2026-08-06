<?php

declare(strict_types=1);

namespace App\Application\Imports;

use App\Application\Imports\Concerns\Importable;
use App\Application\Imports\Imports\AppointmentsImport;
use App\Application\Imports\Imports\CompaniesImport;
use App\Application\Imports\Imports\ContactsImport;
use App\Application\Imports\Imports\CouponsImport;
use App\Application\Imports\Imports\CustomersImport;
use App\Application\Imports\Imports\DealsImport;
use App\Application\Imports\Imports\OrdersImport;
use App\Application\Imports\Imports\ProductsImport;
use App\Application\Imports\Imports\UsersImport;
use InvalidArgumentException;

/**
 * ImportService - Registry de import classes por entity.
 *
 * Soporta sync (inline) y async (queue).
 */
class ImportService
{
    /** @var array<string, class-string<Importable>> */
    private array $registry = [
        'contacts' => ContactsImport::class,
        'companies' => CompaniesImport::class,
        'deals' => DealsImport::class,
        'products' => ProductsImport::class,
        'orders' => OrdersImport::class,
        'users' => UsersImport::class,
        'coupons' => CouponsImport::class,
        'appointments' => AppointmentsImport::class,
        'customers' => CustomersImport::class,
    ];

    /**
     * @return array<int, string>
     */
    public function entities(): array
    {
        return array_keys($this->registry);
    }

    public function has(string $entity): bool
    {
        return isset($this->registry[$entity]);
    }

    public function getImporterClass(string $entity): string
    {
        if (! $this->has($entity)) {
            throw new InvalidArgumentException("Unknown entity: {$entity}");
        }
        return $this->registry[$entity];
    }

    /**
     * Build an import instance with tenant context.
     */
    public function makeImporter(string $entity, string $tenantId, array $extraArgs = []): Importable
    {
        $class = $this->getImporterClass($entity);
        $args = array_merge([$tenantId], $extraArgs);
        return new $class(...$args);
    }

    public function register(string $entity, string $importClass): void
    {
        $this->registry[$entity] = $importClass;
    }

    /**
     * @return array<string, array{headers: array<int, string>, sample: array<string, mixed>}>
     */
    public function templates(): array
    {
        $templates = [];
        foreach ($this->registry as $entity => $class) {
            $instance = new $class('');
            $templates[$entity] = [
                'headers' => $instance->templateHeaders(),
                'sample' => $instance->templateSampleRow(),
            ];
        }
        return $templates;
    }
}





