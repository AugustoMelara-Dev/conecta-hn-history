<?php

declare(strict_types=1);

namespace App\Application\Tenant\Handlers;

use App\Application\Shared\Traits\LogSanitizer;
use App\Application\Tenant\Commands\UpdateLandingPageCommand;
use App\Domain\Tenant\Exceptions\FeatureRequiresProException;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Application\Shared\Contracts\LoggerInterface;
use Illuminate\Support\Facades\Schema;

/**
 * UpdateLandingPageHandler - Landing Page Configuration Handler
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  FEATURE GATING: Only PRO tenants can customize landing pages.          ║
 * ║  HTTP AGNOSTIC: No Illuminate\Http references.                          ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 */
final class UpdateLandingPageHandler
{
    use LogSanitizer;

    /**
     * Handle landing page update command.
     *
     * @throws ModelNotFoundException If tenant not found
     * @throws FeatureRequiresProException If tenant is not PRO
     */
    public function handle(UpdateLandingPageCommand $command): void
    {
        $tenant = Tenant::query()->find($command->tenantId);

        if (! $tenant instanceof Tenant) {
            throw new ModelNotFoundException("Tenant not found: {$command->tenantId}");
        }

        $tenantAttributes = $tenant->getAttributes();
        $planValue = $tenantAttributes['plan_type'] ?? $tenantAttributes['plan'] ?? null;

        if ($planValue instanceof \BackedEnum) {
            $planValue = $planValue->value;
        }

        $isPro = Schema::hasColumn($tenant->getTable(), 'is_pro')
            ? (bool) ($tenantAttributes['is_pro'] ?? false)
            : in_array($planValue, ['pro', 'enterprise'], true);

        if (! $isPro) {
            throw FeatureRequiresProException::forLandingPage();
        }

        if (! Schema::hasColumn($tenant->getTable(), 'landing_page_data')) {
            throw new \RuntimeException('La configuracion de landing page no esta disponible en este entorno.');
        }

        $tenant->landing_page_data = [
            'sections' => $command->landingData,
            'updated_at' => now()->toIso8601String(),
        ];

        $tenant->save();

        $this->logger->info('Tenant landing page updated', $this->sanitizePayload([
            'tenant_id' => $command->tenantId,
        ]));
    }
}