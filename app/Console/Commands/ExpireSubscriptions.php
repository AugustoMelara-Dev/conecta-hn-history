<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Shared\DTOs\Mail\TenantMailData;
use App\Domain\TenantGovernance\Enums\TenantPlan;
use App\Infrastructure\Jobs\Governance\AuditTenantLimits;
use App\Mail\SubscriptionExpiredMail;
use App\Mail\SubscriptionExpiringMail;
use App\Models\Tenant;
use App\Models\User;
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
    /** @var array<int, string> */
    private const PAID_PLAN_KEYS = [
        TenantPlan::PRO->value,
        Tenant::PLAN_CRECIMIENTO,
        Tenant::PLAN_COSECHA,
    ];

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
    public function handle(): int
    {
        // ═══════════════════════════════════════════════════════════════════
        // INYECCION DE CONFIGURACION DESDE "TORRE DE CONTROL"
        // ═══════════════════════════════════════════════════════════════════
        $settings = app(GeneralSettings::class);

        // Prioridad: Argumento manual > Configuracion global
        $optionGraceDays = $this->option('grace-days');
        $graceDays = $optionGraceDays !== null
            ? (int) $optionGraceDays
            : $settings->grace_period_days;

        $freeProductLimit = $settings->free_plan_product_limit;
        $dryRun = (bool) $this->option('dry-run');

        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('   AUTOMATA DE SUSCRIPCIONES - '.now()->format('Y-m-d H:i:s'));
        $this->info('═══════════════════════════════════════════════════════════');

        if ($optionGraceDays !== null) {
            $this->warn("   Dias de gracia: {$graceDays} (SOBREESCRITURA MANUAL)");
        } else {
            $this->info("   Ejecutando con politica global de [{$graceDays}] dias de gracia");
        }

        $this->info("   Limite productos Free: {$freeProductLimit}");

        if ($dryRun) {
            $this->warn('   MODO SIMULACION - No se realizaran cambios');
        }
        $this->info('═══════════════════════════════════════════════════════════');

        // ═══════════════════════════════════════════════════════════════════
        // FASE 1: SISTEMA DE ALERTAS PREVENTIVAS
        // ═══════════════════════════════════════════════════════════════════
        $this->sendExpiringAlerts([5, 1], $dryRun);

        // ═══════════════════════════════════════════════════════════════════
        // FASE 2: REAPER - DEGRADACION AUTOMATICA
        // ═══════════════════════════════════════════════════════════════════
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

            $tenants = Tenant::whereIn('plan_type', self::PAID_PLAN_KEYS)
                ->where('subscription_status', 'active')
                ->whereBetween('subscription_ends_at', [$targetDate, $nextDay])
                ->with('user')
                ->get();

            $this->info("  Vencen en {$days} dia(s): {$tenants->count()} tenant(s)");

            foreach ($tenants as $tenant) {
                $user = $this->resolveTenantUser($tenant);

                if ($user === null) {
                    continue;
                }

                $this->line("    -> Alertando: {$tenant->name} ({$user->email})");

                if (! $dryRun) {
                    try {
                        Mail::to($user->email)->queue(
                            new SubscriptionExpiringMail($this->toTenantMailData($tenant, $user), $days)
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

        $expiredTenants = Tenant::whereIn('plan_type', self::PAID_PLAN_KEYS)
            ->where('subscription_status', 'active')
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
            $user = $this->resolveTenantUser($tenant);

            $this->warn("  [{$tenant->id}] {$tenant->name}");
            $this->line("      Expiro hace: {$expiredDaysAgo} dias");
            $this->line('      Email: '.($user instanceof User ? $user->email : 'sin usuario valido'));

            if ($dryRun) {
                $this->comment('      (Simulacion - sin cambios)');

                continue;
            }

            try {
                // Soft Downgrade con limite dinamico
                $tenant->update([
                    'plan_type' => TenantPlan::SEMILLA->value,
                    'subscription_status' => 'expired',
                    'product_limit' => $freeProductLimit, // Limite desde GeneralSettings
                ]);

                // Disparar auditoria de limites
                if (class_exists(AuditTenantLimits::class)) {
                    AuditTenantLimits::dispatch();
                }

                // Enviar notificacion de suspension
                if ($user !== null) {
                    try {
                        Mail::to($user->email)->queue(
                            new SubscriptionExpiredMail($this->toTenantMailData($tenant, $user))
                        );
                        $this->info('      Email de suspension enviado.');
                    } catch (\Exception $e) {
                        $this->error("      Error enviando email: {$e->getMessage()}");
                    }
                }

                $downgraded++;
                $this->info('      DEGRADADO exitosamente.');

            } catch (\Exception $e) {
                $errors++;
                $this->error("      ERROR: {$e->getMessage()}");
            }

            $this->newLine();
        }

        $this->info('═══════════════════════════════════════════════════════════');
        $this->info("  RESUMEN: {$downgraded} degradados, {$errors} errores");
        $this->info('═══════════════════════════════════════════════════════════');
    }

    private function resolveTenantUser(Tenant $tenant): ?User
    {
        $user = $tenant->user;

        return $user instanceof User ? $user : null;
    }

    private function toTenantMailData(Tenant $tenant, User $user): TenantMailData
    {
        return TenantMailData::fromTenant($tenant, (string) $user->email);
    }
}
