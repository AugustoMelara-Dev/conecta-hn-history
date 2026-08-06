<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * PermissionSeeder - Crea todos los permisos del sistema y los roles principales.
 *
 * Roles: super_admin, tenant_owner, tenant_manager, tenant_agent, tenant_viewer
 * Permisos por dominio: crm., automation., import., export., reports., ai.assistants.
 */
class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            // CRM
            'crm.contacts.view', 'crm.contacts.create', 'crm.contacts.update', 'crm.contacts.delete',
            'crm.companies.view', 'crm.companies.create', 'crm.companies.update', 'crm.companies.delete',
            'crm.deals.view', 'crm.deals.create', 'crm.deals.update', 'crm.deals.delete',
            'crm.activities.view', 'crm.activities.create', 'crm.activities.update', 'crm.activities.delete',
            'crm.notes.view', 'crm.notes.create', 'crm.notes.update', 'crm.notes.delete',
            'crm.tasks.view', 'crm.tasks.create', 'crm.tasks.update', 'crm.tasks.delete',
            'crm.tags.view', 'crm.tags.create', 'crm.tags.update', 'crm.tags.delete',
            'crm.custom_fields.view', 'crm.custom_fields.create', 'crm.custom_fields.update', 'crm.custom_fields.delete',

            // Automation
            'automation.workflows.view', 'automation.workflows.create', 'automation.workflows.update', 'automation.workflows.delete', 'automation.workflows.run',
            'automation.policies.manage',
            'automation.templates.view', 'automation.templates.clone',

            // Import/Export
            'import.run', 'import.cancel', 'import.retry',
            'export.run',

            // Reports
            'reports.view', 'reports.schedule', 'reports.export',

            // AI Assistants
            'ai.assistants.use',
            'ai.assistants.configure',

            // Admin
            'admin.tenants.view', 'admin.tenants.manage',
            'admin.users.view', 'admin.users.manage',
            'admin.billing.view', 'admin.billing.manage',
            'admin.audit.view',
            'admin.settings.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        $roles = [
            'super_admin' => $permissions, // All perms
            'tenant_owner' => array_filter($permissions, fn ($p) => ! str_starts_with($p, 'admin.')),
            'tenant_manager' => array_merge(
                array_filter($permissions, fn ($p) => str_starts_with($p, 'crm.') || str_starts_with($p, 'automation.')),
                ['automation.policies.manage', 'reports.schedule'],
            ),
            'tenant_agent' => array_filter($permissions, fn ($p) => str_ends_with($p, '.view') || str_ends_with($p, '.create') || str_ends_with($p, '.update')),
            'tenant_viewer' => array_filter($permissions, fn ($p) => str_ends_with($p, '.view')),
        ];

        foreach ($roles as $roleName => $rolePerms) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($rolePerms);
        }
    }
}
