<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\City;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

/**
 * GenerateSitemap - Automatizaci�n de Rastreo para Google
 *
 * Este comando construye el sitemap.xml iterando expl�citamente
 * sobre la base de datos en lugar de rastrear URLs locales.
 *
 * Ventajas:
 * - No falla en producci�n por URLs inaccesibles
 * - Control total sobre qu� incluir
 * - M�s r�pido que crawling
 *
 * Programaci�n: Laravel Scheduler diariamente a medianoche
 */
class GenerateSitemap extends Command
{
    protected $signature = 'seo:generate-sitemap 
        {--dry-run : Mostrar URLs sin escribir archivo}';

    protected $description = 'Genera el sitemap.xml iterando sobre entidades activas de la base de datos';

    /**
     * Contadores para estad�sticas
     */
    private int $citiesCount = 0;

    private int $categoriesCount = 0;

    private int $tenantsCount = 0;

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $this->info('�"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"�');
        $this->info('   GENERADOR DE SITEMAP - '.now()->format('Y-m-d H:i:s'));
        $this->info('�"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"�');
        $this->newLine();

        $sitemap = Sitemap::create();

        // �"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"�
        // P�GINA PRINCIPAL
        // �"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"�
        $this->info('Agregando p�gina principal...');
        $sitemap->add(
            Url::create(url('/'))
                ->setLastModificationDate(now())
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                ->setPriority(1.0)
        );

        // �"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"�
        // CIUDADES ACTIVAS
        // �"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"�
        $this->info('Procesando ciudades...');
        $this->addCities($sitemap, $dryRun);

        // �"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"�
        // CATEGOR�AS ACTIVAS
        // �"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"�
        $this->info('Procesando categor�as...');
        $this->addCategories($sitemap, $dryRun);

        // �"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"�
        // NEGOCIOS (TENANTS) ACTIVOS
        // �"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"�
        $this->info('Procesando negocios...');
        $this->addTenants($sitemap, $dryRun);

        // �"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"�
        // ESCRIBIR ARCHIVO
        // �"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"�
        if (! $dryRun) {
            Storage::disk('public')->put('sitemap.xml', $sitemap->render());
            $this->info('Sitemap escrito en el disco p�blico configurado: sitemap.xml');
        } else {
            $this->warn('MODO SIMULACI�N - No se escribi� archivo');
        }

        // �"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"�
        // RESUMEN
        // �"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"�
        $this->newLine();
        $this->info('�"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"�');
        $this->info('   RESUMEN');
        $this->info('�"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"��"�');
        $this->table(
            ['Entidad', 'Cantidad'],
            [
                ['Ciudades', $this->citiesCount],
                ['Categor�as', $this->categoriesCount],
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
                $this->line("  �  {$url}");
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
     * Agrega categor�as al sitemap
     */
    private function addCategories(Sitemap $sitemap, bool $dryRun): void
    {
        $categories = Category::where('is_active', true)->get();

        foreach ($categories as $category) {
            $url = url("/categoria/{$category->slug}");

            if ($dryRun) {
                $this->line("  �  {$url}");
            }

            $sitemap->add(
                Url::create($url)
                    ->setLastModificationDate($category->updated_at ?? now())
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                    ->setPriority(0.8)
            );

            $this->categoriesCount++;
        }

        $this->info("  {$this->categoriesCount} categor�as agregadas");
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
                $this->line("  �  {$url}");
            }

            // Los negocios PRO tienen mayor prioridad
            $priority = $tenant->is_pro ? 0.9 : 0.7;

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
