<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

/**
 * RolesAndPermissionsSeeder - Jerarquia RBAC Granular
 * 
 * Define roles operativos con permisos especificos:
 * - super_admin: Control total
 * - ventas: Adquisicion (view tenants, forbidden delete)
 * - soporte: Retencion (impersonate, forbidden payments)
 * - auditor: Solo lectura (view all, modify nothing)
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        // Resetear cache de permisos
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ═══════════════════════════════════════════════════════════════════
        // DEFINIR PERMISOS POR RECURSO
        // ═══════════════════════════════════════════════════════════════════
        
        $resources = [
            'tenant' => ['view_any', 'view', 'create', 'update', 'delete', 'force_delete', 'restore'],
            'payment' => ['view_any', 'view', 'create', 'update', 'delete', 'approve', 'reject'],
            'user' => ['view_any', 'view', 'create', 'update', 'delete', 'impersonate'],
            'category' => ['view_any', 'view', 'create', 'update', 'delete'],
            'city' => ['view_any', 'view', 'create', 'update', 'delete'],
            'activity_log' => ['view_any', 'view'],
            'settings' => ['view', 'update'],
            'widget' => ['financial_stats', 'pending_payments', 'acquisition_chart'],
        ];

        // Crear todos los permisos
        foreach ($resources as $resource => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate(['name' => "{$action}_{$resource}"]);
            }
        }

        // ═══════════════════════════════════════════════════════════════════
        // ROL: SUPER ADMIN (Control Total)
        // ═══════════════════════════════════════════════════════════════════
        $superAdmin = Role::firstOrCreate(['name' => 'super_admin']);
        $superAdmin->syncPermissions(Permission::all());

        // ═══════════════════════════════════════════════════════════════════
        // ROL: VENTAS (Hunter - Adquisicion)
        // Puede: Ver tenants para leads
        // No Puede: Eliminar, configuracion del sistema
        // ═══════════════════════════════════════════════════════════════════
        $ventas = Role::firstOrCreate(['name' => 'ventas']);
        $ventas->syncPermissions([
            // Tenants: Solo lectura para leads
            'view_any_tenant',
            'view_tenant',
            // Categories/Cities: Solo lectura
            'view_any_category',
            'view_category',
            'view_any_city',
            'view_city',
        ]);

        // ═══════════════════════════════════════════════════════════════════
        // ROL: SOPORTE TECNICO (Helper - Retencion)
        // Puede: Impersonar, ver y editar tenants
        // No Puede: Ver pagos ni widgets financieros
        // ═══════════════════════════════════════════════════════════════════
        $soporte = Role::firstOrCreate(['name' => 'soporte']);
        $soporte->syncPermissions([
            // Tenants: Control completo excepto eliminar
            'view_any_tenant',
            'view_tenant',
            'update_tenant',
            // Users: Impersonar
            'view_any_user',
            'view_user',
            'impersonate_user',
            // Categories/Cities: Solo lectura
            'view_any_category',
            'view_category',
            'view_any_city',
            'view_city',
            // NO tiene: payment_*, financial_stats_widget, settings_*
        ]);

        // ═══════════════════════════════════════════════════════════════════
        // ROL: AUDITOR (Observer - Solo Lectura)
        // Puede: Ver todo incluyendo logs y finanzas
        // No Puede: Modificar absolutamente nada
        // ═══════════════════════════════════════════════════════════════════
        $auditor = Role::firstOrCreate(['name' => 'auditor']);
        $auditor->syncPermissions([
            // View everything
            'view_any_tenant',
            'view_tenant',
            'view_any_payment',
            'view_payment',
            'view_any_user',
            'view_user',
            'view_any_category',
            'view_category',
            'view_any_city',
            'view_city',
            'view_any_activity_log',
            'view_activity_log',
            // Widgets financieros
            'financial_stats_widget',
            'pending_payments_widget',
            'acquisition_chart_widget',
            // View settings
            'view_settings',
            // NO tiene: create_*, update_*, delete_*
        ]);

        // ═══════════════════════════════════════════════════════════════════
        // ROL: PANEL_USER (Propietarios de Negocios)
        // Acceso limitado al panel para gestionar su propio negocio
        // ═══════════════════════════════════════════════════════════════════
        $panelUser = Role::firstOrCreate(['name' => 'panel_user']);
        // Este rol no necesita permisos específicos aquí porque 
        // la lógica de acceso está controlada por policies y tenant scoping

        $this->command->info('Roles y permisos creados exitosamente:');
        $this->command->info('  - super_admin: Control total');
        $this->command->info('  - ventas: Solo lectura de tenants');
        $this->command->info('  - soporte: Impersonate + edicion tenants (sin finanzas)');
        $this->command->info('  - auditor: Solo lectura total');
        $this->command->info('  - panel_user: Propietarios de negocios');
    }
}
