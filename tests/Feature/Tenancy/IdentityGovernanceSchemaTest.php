<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class IdentityGovernanceSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_membership_lifecycle_and_governance_tables_are_explicit(): void
    {
        $this->assertTableColumns('tenant_memberships', [
            'status',
            'invited_at',
            'suspended_at',
            'revoked_at',
            'revoked_by_user_id',
        ]);
        $this->assertTableColumns('tenant_invitations', [
            'id',
            'tenant_id',
            'email',
            'role',
            'preset_key',
            'granted_permissions',
            'token_hash',
            'invited_by_user_id',
            'expires_at',
            'accepted_at',
            'accepted_by_user_id',
            'revoked_at',
        ]);
        $this->assertTableColumns('authenticated_sessions', [
            'id',
            'session_id_hash',
            'user_id',
            'current_tenant_id',
            'device_name',
            'ip_address_hash',
            'user_agent',
            'last_authenticated_at',
            'mfa_verified_at',
            'last_seen_at',
            'revoked_at',
            'revocation_reason',
        ]);
        $this->assertTableColumns('impersonation_grants', [
            'id',
            'operator_user_id',
            'tenant_id',
            'target_user_id',
            'session_id_hash',
            'reason',
            'expires_at',
            'started_at',
            'ended_at',
            'revoked_at',
        ]);
    }

    public function test_program_03_rollback_preserves_existing_identity_and_tenancy_data(): void
    {
        $migration = require database_path('migrations/2026_07_28_000002_expand_identity_tenancy_governance.php');

        $migration->down();

        self::assertTrue(Schema::hasTable('users'));
        self::assertTrue(Schema::hasTable('tenants'));
        self::assertTrue(Schema::hasTable('tenant_memberships'));
        self::assertTrue(Schema::hasTable('tenant_users'));
        self::assertTrue(Schema::hasColumn('tenant_memberships', 'accepted_at'));
        self::assertFalse(Schema::hasColumn('tenant_memberships', 'status'));
        self::assertFalse(Schema::hasTable('tenant_invitations'));
        self::assertFalse(Schema::hasTable('authenticated_sessions'));
        self::assertFalse(Schema::hasTable('impersonation_grants'));

        $migration->up();
    }

    /**
     * @param  list<string>  $columns
     */
    private function assertTableColumns(string $table, array $columns): void
    {
        self::assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist.");

        foreach ($columns as $column) {
            self::assertTrue(
                Schema::hasColumn($table, $column),
                "Expected [{$table}.{$column}] to exist.",
            );
        }
    }
}
