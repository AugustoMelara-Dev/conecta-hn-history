<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\Exports;

use App\Contracts\DataExportInterface;
use App\Domain\Shared\ValueObjects\TenantId;
use App\Exports\Criteria\SalesExportCriteria;
use App\Exports\SalesExport;
use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use App\Models\User;
use App\Notifications\ExportReadyNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;

/**
 * GenerateSalesExport - Sales Export Use Case
 *
 * APPLICATION JOB: Uses Repository pattern for entity hydration.
 */
final class GenerateSalesExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public function __construct(
        public readonly string $userId,
        public readonly string $tenantId,
        public readonly array $criteriaData,
        public readonly string $ipAddress,
        public readonly string $userAgent
    ) {}

    public function handle(DataExportInterface $exporter): void
    {
        $user = User::query()->find($this->userId);
        if ($user === null) {
            throw new ModelNotFoundException("User {$this->userId} not found");
        }

        $tenantId = TenantId::fromString($this->tenantId);
        $criteria = SalesExportCriteria::fromArray($this->criteriaData);

        $filename = sprintf('exports/tenant_%s/sales_%s.xlsx', $this->tenantId, Str::uuid());
        $exporter->store(SalesExport::create(
            tenantId: $tenantId,
            criteria: $criteria,
            userId: $this->userId,
            ipAddress: $this->ipAddress,
            userAgent: $this->userAgent,
        ), $filename);

        $user->notify(new ExportReadyNotification($filename, $this->tenantId, 'sales'));
    }

    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->tenantId)];
    }

    public function tags(): array
    {
        return ['exports', 'sales', 'tenant:'.$this->tenantId];
    }
}
