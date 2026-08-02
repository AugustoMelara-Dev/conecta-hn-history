<?php

declare(strict_types=1);

namespace App\Infrastructure\Tenancy;

use App\Domain\Shared\Services\TenantContext;
use Closure;
use Illuminate\Support\Facades\DB;
use LogicException;

final class PostgresTenantScope
{
    public function __construct(private readonly TenantContext $context) {}

    /**
     * Execute all tenant SQL inside one transaction-scoped PostgreSQL RLS context.
     * SET LOCAL semantics prevent context from leaking through pooled connections.
     */
    public function run(Closure $callback): mixed
    {
        if (! $this->enabled()) {
            return $callback();
        }

        if (! $this->context->isInitialized()) {
            return $callback();
        }

        if ($this->context->isRoot()) {
            throw new LogicException('System-root work requires the dedicated platform database connection.');
        }

        return DB::transaction(function () use ($callback): mixed {
            DB::select("select set_config('app.tenant_id', ?, true)", [$this->context->id()]);

            return $callback();
        });
    }

    public function enabled(): bool
    {
        return (bool) config('tenancy.postgres_rls.enabled', false)
            && DB::connection()->getDriverName() === 'pgsql';
    }
}
