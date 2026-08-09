<?php

declare(strict_types=1);

namespace App\Application\Identity;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class IdentityGovernanceBackfill
{
    public function run(bool $dryRun = false, int $limit = 0, int $offset = 0): IdentityGovernanceBackfillReport
    {
        if ($limit < 0 || $offset < 0) {
            throw new \InvalidArgumentException('The backfill limit and offset must be zero or greater.');
        }

        $roleConflicts = 0;

        $operation = function () use (&$roleConflicts, $limit, $offset): void {
            $now = now();

            $owners = DB::table('tenants')
                ->whereNotNull('user_id')
                ->orderBy('id');
            $acceptedMembers = DB::table('tenant_users')
                ->whereNotNull('accepted_at')
                ->orderBy('id');

            if ($limit > 0) {
                $owners->offset($offset)->limit($limit);
                $acceptedMembers->offset($offset)->limit($limit);
            } elseif ($offset > 0) {
                $owners->offset($offset);
                $acceptedMembers->offset($offset);
            }

            $owners->each(function (object $tenant) use ($now): void {
                $this->upsertMembership(
                    tenantId: (string) $tenant->id,
                    userId: (string) $tenant->user_id,
                    role: 'owner',
                    permissions: [],
                    invitedAt: $tenant->created_at ?? $now,
                    acceptedAt: $tenant->created_at ?? $now,
                    now: $now,
                    ownerIsAuthoritative: true,
                );
            });

            $acceptedMembers->each(function (object $legacy) use (&$roleConflicts, $now): void {
                $role = $this->canonicalRole((string) $legacy->role);
                $existingRole = DB::table('tenant_memberships')
                    ->where('tenant_id', $legacy->tenant_id)
                    ->where('user_id', $legacy->user_id)
                    ->value('role');

                if (is_string($existingRole) && $existingRole !== $role) {
                    $roleConflicts++;

                    return;
                }

                $this->upsertMembership(
                    tenantId: (string) $legacy->tenant_id,
                    userId: (string) $legacy->user_id,
                    role: $role,
                    permissions: $this->legacyPermissions($legacy),
                    invitedAt: $legacy->invited_at,
                    acceptedAt: $legacy->accepted_at,
                    now: $now,
                );
            });
        };

        if ($dryRun) {
            DB::beginTransaction();

            try {
                $operation();
                $report = $this->report($roleConflicts);
                DB::rollBack();

                return $report;
            } catch (\Throwable $exception) {
                if (DB::transactionLevel() > 0) {
                    DB::rollBack();
                }

                throw $exception;
            }
        }

        DB::transaction($operation);

        return $this->report($roleConflicts);
    }

    private function report(int $roleConflicts): IdentityGovernanceBackfillReport
    {
        return new IdentityGovernanceBackfillReport(
            ownersMapped: DB::table('tenants')->whereNotNull('user_id')->count(),
            acceptedMembersMapped: DB::table('tenant_users')->whereNotNull('accepted_at')->count(),
            pendingInvitationsRequiringReissue: DB::table('tenant_users')->whereNull('accepted_at')->count(),
            roleConflicts: $roleConflicts,
        );
    }

    /**
     * @param  list<string>  $permissions
     */
    private function upsertMembership(
        string $tenantId,
        string $userId,
        string $role,
        array $permissions,
        mixed $invitedAt,
        mixed $acceptedAt,
        mixed $now,
        bool $ownerIsAuthoritative = false,
    ): void {
        $existing = DB::table('tenant_memberships')
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->first();
        $values = [
            'role' => $role,
            'status' => 'active',
            'invited_at' => $invitedAt,
            'accepted_at' => $acceptedAt,
            'updated_at' => $now,
        ];

        if ($existing === null) {
            DB::table('tenant_memberships')->insert($values + [
                'id' => (string) Str::uuid(),
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'preset_key' => null,
                'granted_permissions' => json_encode($permissions, JSON_THROW_ON_ERROR),
                'created_at' => $now,
            ]);

            return;
        }

        if ($ownerIsAuthoritative) {
            DB::table('tenant_memberships')
                ->where('id', $existing->id)
                ->update($values);
        }
    }

    private function canonicalRole(string $legacyRole): string
    {
        return match ($legacyRole) {
            'owner' => 'owner',
            'manager' => 'manager',
            default => 'operator',
        };
    }

    /**
     * @return list<string>
     */
    private function legacyPermissions(object $legacy): array
    {
        $map = [
            'can_manage_products' => [
                'catalog.view',
                'catalog.create',
                'catalog.update',
                'catalog.delete',
                'catalog.publish',
            ],
            'can_manage_orders' => [
                'orders.view',
                'orders.create',
                'orders.manage',
                'orders.fulfill',
                'orders.cancel',
                'orders.export',
            ],
            'can_view_analytics' => ['analytics.view'],
            'can_manage_settings' => ['settings.view', 'settings.update'],
            'can_manage_team' => ['staff.view', 'staff.manage', 'staff.assign_roles'],
            'can_manage_billing' => ['billing.view', 'billing.manage'],
        ];
        $permissions = [];

        foreach ($map as $column => $columnPermissions) {
            if ((bool) ($legacy->{$column} ?? false)) {
                array_push($permissions, ...$columnPermissions);
            }
        }

        return $permissions;
    }
}
