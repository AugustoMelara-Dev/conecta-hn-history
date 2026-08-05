<?php

declare(strict_types=1);

namespace App\Infrastructure\Jobs\Governance;

use App\Infrastructure\Shared\Middleware\TenantQueueMiddleware;
use App\Models\Product;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Records a product-limit compliance snapshot for exactly one tenant.
 *
 * This job intentionally audits rather than deleting or hiding catalog data.
 * A subscription downgrade must never destroy merchant inventory implicitly.
 */
final class AuditTenantLimits implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public readonly string $tenantId) {}

    /** @return array<int, TenantQueueMiddleware> */
    public function middleware(): array
    {
        return [new TenantQueueMiddleware($this->tenantId)];
    }

    public function handle(): void
    {
        $context = $this->complianceSnapshot();

        if ($context === null) {
            Log::notice('AuditTenantLimits: Active tenant not found', [
                'tenant_id' => $this->tenantId,
            ]);

            return;
        }

        if ($context['over_by'] > 0) {
            Log::warning('AuditTenantLimits: Product limit exceeded', $context);

            return;
        }

        Log::info('AuditTenantLimits: Product limit compliant', $context);
    }

    /**
     * @return array{tenant_id: string, product_count: int, product_limit: int, over_by: int, compliant: bool}|null
     */
    public function complianceSnapshot(): ?array
    {
        $tenant = Tenant::query()
            ->whereKey($this->tenantId)
            ->where('is_active', true)
            ->first();

        if ($tenant === null) {
            return null;
        }

        $limit = max(0, (int) ($tenant->getAttribute('product_limit') ?? 0));
        $productCount = Product::query()
            ->where('tenant_id', $this->tenantId)
            ->count();
        $overBy = max(0, $productCount - $limit);

        return [
            'tenant_id' => $this->tenantId,
            'product_count' => $productCount,
            'product_limit' => $limit,
            'over_by' => $overBy,
            'compliant' => $overBy === 0,
        ];
    }

    /** @return array<int, string> */
    public function tags(): array
    {
        return ['governance', 'audit', 'tenant:'.$this->tenantId];
    }
}
