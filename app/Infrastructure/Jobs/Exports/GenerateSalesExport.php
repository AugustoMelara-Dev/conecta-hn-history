<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\Exports;

use App\Contracts\DataExportInterface;
use App\Domain\Identity\Exceptions\UserNotFoundException;
use App\Domain\Shared\ValueObjects\TenantId;
use App\Exports\Criteria\SalesExportCriteria;
use App\Exports\SalesExport;
use App\Infrastructure\Queue\Middleware\TenantQueueMiddleware;
use App\Models\User;
use App\Notifications\ExportReadyNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;

/**
 * GenerateSalesExport - Sales Export Use Case
 */
final class GenerateSalesExport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    /**
     * @param  array{start_date?: ?string, end_date?: ?string, status?: ?string}  $criteriaData
     */
    public function __construct(
        public readonly int|string $userId,
        public readonly string $tenantId,
        public readonly array $criteriaData,
        public readonly string $requestIp,
        public readonly string $userAgent,
    ) {}

    public function handle(DataExportInterface $exporter): void
    {
        $user = User::query()->find($this->userId);

        if ($user === null) {
            throw UserNotFoundException::withId((string) $this->userId);
        }

        $criteria = SalesExportCriteria::fromArray($this->criteriaData);
        $filename = sprintf('exports/tenant_%s/sales_%s.xlsx', $this->tenantId, (string) Str::uuid());
        $export = SalesExport::create(
            tenantId: TenantId::fromString($this->tenantId),
            criteria: $criteria,
            userId: (string) $user->getKey(),
            ipAddress: $this->requestIp,
            userAgent: $this->userAgent,
        );

        if (! $exporter->store($export, $filename, (string) config('filesystems.exports', 'local'))) {
            throw new \RuntimeException('The sales export could not be stored.');
        }

        $user->notify(new ExportReadyNotification(
            filePath: $filename,
            tenantId: $this->tenantId,
            exportType: 'sales',
        ));
    }

    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->tenantId)];
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['exports', 'sales', 'tenant:'.$this->tenantId];
    }
}
