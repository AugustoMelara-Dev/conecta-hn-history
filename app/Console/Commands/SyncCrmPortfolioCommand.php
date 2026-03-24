<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\TenantGovernance\Services\TenantCrmPortfolioManager;
use Illuminate\Console\Command;

final class SyncCrmPortfolioCommand extends Command
{
    protected $signature = 'crm:sync-portfolio';

    protected $description = 'Sincroniza watchlist, funnel y automatizaciones CRM de toda la cartera de tenants';

    public function handle(TenantCrmPortfolioManager $portfolioManager): int
    {
        $this->components->info('Sincronizando cartera CRM multi-negocio...');

        $portfolioManager->syncSignals();

        $this->components->info('Cartera CRM actualizada correctamente.');

        return self::SUCCESS;
    }
}
