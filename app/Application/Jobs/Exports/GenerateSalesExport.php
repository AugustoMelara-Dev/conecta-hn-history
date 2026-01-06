<?php

declare(strict_types=1);

namespace App\Application\Jobs\Exports;

use App\Exports\Criteria\SalesExportCriteria;
use App\Exports\SalesExport;
use App\Domain\Identity\Models\User;
use App\Notifications\ExportReadyNotification;
use App\Domain\Shared\ValueObjects\TenantId;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;
use App\Contracts\DataExportInterface;
use App\Domain\Shared\Middleware\TenantQueueMiddleware;

final class GenerateSalesExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public int $timeout = 600;

    public function __construct(
        public readonly int $userId,
        public readonly int $tenantId,
        public readonly array $criteriaData,
        public readonly string $requestIp,
        public readonly string $userAgent
    ) {}

    public function handle(DataExportInterface $exporter): void
    {
        $user = User::findOrFail($this->userId);
        $tenantId = TenantId::fromInt($this->tenantId);
        $criteria = SalesExportCriteria::fromArray($this->criteriaData);

        $filename = sprintf('exports/tenant_%d/sales_%s.xlsx', $this->tenantId, Str::uuid());
        $exporter->export(new SalesExport($tenantId, $criteria), $filename);

        $downloadUrl = route('exports.download', ['path' => $filename]);
        $user->notify(new ExportReadyNotification($downloadUrl, 'Sales Export', now()->addHours(24)));
    }

    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->tenantId)];
    }

    public function tags(): array
    {
        return ['exports', 'sales', 'tenant:' . $this->tenantId];
    }
}
