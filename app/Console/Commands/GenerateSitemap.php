<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * GenerateSitemap - Automatización de Rastreo para Google
 *
 * Este comando construye el sitemap.xml iterando explícitamente
 * sobre la base de datos en lugar de rastrear URLs locales.
 *
 * Ventajas:
 * - No falla en producción por URLs inaccesibles
 * - Control total sobre qué incluir
 * - Más rápido que crawling
 *
 * Programación: Laravel Scheduler diariamente a medianoche
 */
class GenerateSitemap extends Command
{
    protected $signature = 'seo:generate-sitemap 
        {--dry-run : Mostrar URLs sin escribir archivo}';

    protected $description = 'Genera el sitemap.xml iterando sobre entidades activas de la base de datos';

    /**
     * Contadores para estadísticas
     */
    private int $citiesCount = 0;

    private int $categoriesCount = 0;

    private int $tenantsCount = 0;

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('   GENERADOR DE SITEMAP - '.now()->format('Y-m-d H:i:s'));
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        $sitemap = Sitemap::create();

        // ═══════════════════════════════════════════════════════════════════
        // PÁGINA PRINCIPAL
        // ═══════════════════════════════════════════════════════════════════
        $this->info('Agregando página principal...');
        $sitemap->add(
            Url::create(url('/'))
                ->setLastModificationDate(now())
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                ->setPriority(1.0)
        );

        // ═══════════════════════════════════════════════════════════════════
        // CIUDADES ACTIVAS
        // ═══════════════════════════════════════════════════════════════════
        $this->info('Procesando ciudades...');
        $this->addCities($sitemap, $dryRun);

        // ═══════════════════════════════════════════════════════════════════
        // CATEGORÍAS ACTIVAS
        // ═══════════════════════════════════════════════════════════════════
        $this->info('Procesando categorías...');
        $this->addCategories($sitemap, $dryRun);

        // ═══════════════════════════════════════════════════════════════════
        // NEGOCIOS (TENANTS) ACTIVOS
        // ═══════════════════════════════════════════════════════════════════
        $this->info('Procesando negocios...');
        $this->addTenants($sitemap, $dryRun);

        // ═══════════════════════════════════════════════════════════════════
        // ESCRIBIR ARCHIVO
        // ═══════════════════════════════════════════════════════════════════
        if (! $dryRun) {
            $path = public_path('sitemap.xml');
            $sitemap->writeToFile($path);
            $this->info("Sitemap escrito en: {$path}");
        } else {
            $this->warn('MODO SIMULACIÓN - No se escribió archivo');
        }

        // ═══════════════════════════════════════════════════════════════════
        // RESUMEN
        // ═══════════════════════════════════════════════════════════════════
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('   RESUMEN');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->table(
            ['Entidad', 'Cantidad'],
            [
                ['Ciudades', $this->citiesCount],
                ['Categorías', $this->categoriesCount],
                ['Negocios', $this->tenantsCount],
                ['TOTAL URLs', 1 + $this->citiesCount + $this->categoriesCount + $this->tenantsCount],
            ]
        );

        return Command::SUCCESS;
    }

    /**
     * Agrega ciudades al sitemap
     */
    private function addCities(Sitemap $sitemap, bool $dryRun): void
    {
        $cities = City::where('is_available', true)->get();

        foreach ($cities as $city) {
            $url = url("/ciudad/{$city->slug}");

            if ($dryRun) {
                $this->line("  → {$url}");
            }

            $sitemap->add(
                Url::create($url)
                    ->setLastModificationDate($city->updated_at ?? now())
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                    ->setPriority(0.9)
            );

            $this->citiesCount++;
        }

        $this->info("  {$this->citiesCount} ciudades agregadas");
    }

    /**
     * Agrega categorías al sitemap
     */
    private function addCategories(Sitemap $sitemap, bool $dryRun): void
    {
        $categories = Category::where('is_active', true)->get();

        foreach ($categories as $category) {
            $url = url("/categoria/{$category->slug}");

            if ($dryRun) {
                $this->line("  → {$url}");
            }

            $sitemap->add(
                Url::create($url)
                    ->setLastModificationDate($category->updated_at ?? now())
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                    ->setPriority(0.8)
            );

            $this->categoriesCount++;
        }

        $this->info("  {$this->categoriesCount} categorías agregadas");
    }

    /**
     * Agrega negocios (tenants) al sitemap
     */
    private function addTenants(Sitemap $sitemap, bool $dryRun): void
    {
        // Solo negocios activos y visibles
        $tenants = Tenant::where('is_active', true)
            ->where('status', 'approved')
            ->get();

        foreach ($tenants as $tenant) {
            $url = url("/{$tenant->slug}");

            if ($dryRun) {
                $this->line("  → {$url}");
            }

            // Los negocios PRO tienen mayor prioridad
            $planKey = $tenant->getRawOriginal('plan_type') ?: ($tenant->getAttributes()['plan_type'] ?? null);
            $priority = match (true) {
                $planKey instanceof TenantPlan && $planKey !== TenantPlan::SEMILLA => 0.9,
                is_string($planKey) && $planKey !== '' && $planKey !== Tenant::PLAN_SEMILLA => 0.9,
                default => 0.7,
            };

            $sitemap->add(
                Url::create($url)
                    ->setLastModificationDate($tenant->updated_at ?? now())
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                    ->setPriority($priority)
            );

            $this->tenantsCount++;
        }

        $this->info("  {$this->tenantsCount} negocios agregados");
    }
}
