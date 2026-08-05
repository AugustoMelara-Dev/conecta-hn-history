<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Jobs\Governance\AuditTenantLimits;
use App\Mail\SubscriptionExpiredMail;
use App\Mail\SubscriptionExpiringMail;
use App\Models\Tenant;
use App\Settings\GeneralSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * CheckSubscriptionExpiry - El Automata de Ciclo de Vida
 *
 * Este comando gestiona automaticamente:
 * 1. Degradacion de planes vencidos (Reaper)
 * 2. Alertas preventivas a 5 y 1 dia de vencimiento
 *
 * Los parametros se obtienen dinamicamente de GeneralSettings (Torre de Control).
 * Debe ejecutarse diariamente via cron/scheduler.
 */
class ExpireSubscriptions extends Command
{
    /**
     * The name and signature of the console command.
     * --grace-days es una sobreescritura de emergencia (debugging)
     */
    protected $signature = 'subscriptions:expire 
        {--grace-days= : Sobreescritura manual (default: usa GeneralSettings)}
        {--dry-run : Simular sin hacer cambios}';

    /**
     * The console command description.
     */
    protected $description = 'Gestiona el ciclo de vida de suscripciones: expiracion, downgrade y alertas';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // INYECCION DE CONFIGURACION DESDE "TORRE DE CONTROL"
        $settings = app(GeneralSettings::class);

        // Prioridad: Argumento manual > Configuracion global
        $optionGraceDays = $this->option('grace-days');
        $graceDays = $optionGraceDays !== null
            ? (int) $optionGraceDays
            : $settings->grace_period_days;

        $freeProductLimit = $settings->free_plan_product_limit;
        $dryRun = $this->option('dry-run');

        $this->info('   AUTOMATA DE SUSCRIPCIONES - '.now()->format('Y-m-d H:i:s'));

        if ($optionGraceDays !== null) {
            $this->warn("   Dias de gracia: {$graceDays} (SOBREESCRITURA MANUAL)");
        } else {
            $this->info("   Ejecutando con politica global de [{$graceDays}] dias de gracia");
        }

        $this->info("   Limite productos Free: {$freeProductLimit}");

        if ($dryRun) {
            $this->warn('   MODO SIMULACION - No se realizaran cambios');
        }

        // FASE 1: SISTEMA DE ALERTAS PREVENTIVAS
        $this->sendExpiringAlerts([5, 1], $dryRun);

        // FASE 2: REAPER - DEGRADACION AUTOMATICA
        $this->processExpiredSubscriptions($graceDays, $freeProductLimit, $dryRun);

        $this->newLine();
        $this->info('Proceso completado exitosamente.');

        return Command::SUCCESS;
    }

    /**
     * Envia alertas a tenants cuyo plan vence en X dias.
     */
    protected function sendExpiringAlerts(array $daysBeforeExpiry, bool $dryRun): void
    {
        $this->newLine();
        $this->info('FASE 1: Enviando alertas de vencimiento proximo...');
        $this->newLine();

        foreach ($daysBeforeExpiry as $days) {
            $targetDate = now()->addDays($days)->startOfDay();
            $nextDay = $targetDate->copy()->endOfDay();

            $tenants = Tenant::where('is_pro', true)
                ->whereBetween('subscription_ends_at', [$targetDate, $nextDay])
                ->with('user')
                ->get();

            $this->info("  Vencen en {$days} dia(s): {$tenants->count()} tenant(s)");

            foreach ($tenants as $tenant) {
                $this->line("    -> Alertando: {$tenant->name} ({$tenant->user->email})");

                if (! $dryRun) {
                    try {
                        Mail::to($tenant->user->email)->queue(
                            new SubscriptionExpiringMail($tenant, $days)
                        );
                    } catch (\Exception $e) {
                        $this->error("    Error enviando email: {$e->getMessage()}");
                    }
                }
            }
        }
    }

    /**
     * Procesa suscripciones expiradas aplicando downgrade.
     */
    protected function processExpiredSubscriptions(int $graceDays, int $freeProductLimit, bool $dryRun): void
    {
        $this->newLine();
        $this->info('FASE 2: Procesando suscripciones expiradas (Reaper)...');
        $this->newLine();

        // Fecha limite = hoy - dias de gracia
        $cutoffDate = now()->subDays($graceDays);

        $expiredTenants = Tenant::where('is_pro', true)
            ->where('subscription_ends_at', '<', $cutoffDate)
            ->with('user')
            ->get();

        $this->info("  Tenants con plan expirado (>{$graceDays} dias de gracia): {$expiredTenants->count()}");
        $this->newLine();

        if ($expiredTenants->isEmpty()) {
            $this->info('  No hay suscripciones para degradar.');

            return;
        }

        $downgraded = 0;
        $errors = 0;

        foreach ($expiredTenants as $tenant) {
            $expiredDaysAgo = $tenant->subscription_ends_at->diffInDays(now());

            $this->warn("  [{$tenant->id}] {$tenant->name}");
            $this->line("      Expiro hace: {$expiredDaysAgo} dias");
            $this->line("      Email: {$tenant->user->email}");

            if ($dryRun) {
                $this->comment('      (Simulacion - sin cambios)');

                continue;
            }

            try {
                // Soft Downgrade con limite dinamico
                $tenant->update([
                    'is_pro' => false,
                    'subscription_status' => 'expired',
                    'plan_type' => 'semilla',
                    'product_limit' => $freeProductLimit, // Limite desde GeneralSettings
                ]);

                app(\App\Domain\Billing\Services\MonthlyAiCreditGrantService::class)->grant($tenant->refresh());

                // Disparar auditoria de limites
                if (class_exists(AuditTenantLimits::class)) {
                    AuditTenantLimits::dispatch((string) $tenant->getKey());
                }

                // Enviar notificacion de suspension
                try {
                    Mail::to($tenant->user->email)->queue(
                        new SubscriptionExpiredMail($tenant)
                    );
                    $this->info('      Email de suspension enviado.');
                } catch (\Exception $e) {
                    $this->error("      Error enviando email: {$e->getMessage()}");
                }

                $downgraded++;
                $this->info('      DEGRADADO exitosamente.');

            } catch (\Exception $e) {
                $errors++;
                $this->error("      ERROR: {$e->getMessage()}");
            }

            $this->newLine();
        }

        $this->info("  RESUMEN: {$downgraded} degradados, {$errors} errores");
    }
}
