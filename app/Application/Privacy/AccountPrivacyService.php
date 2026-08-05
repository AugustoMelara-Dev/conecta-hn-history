<?php

declare(strict_types=1);

namespace App\Application\Privacy;

use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class AccountPrivacyService
{
    /** @return array<string, mixed> */
    public function export(User $user): array
    {
        $userId = (string) $user->getKey();

        return [
            'format' => 'conecta-hn-account-export-v1',
            'generated_at' => now()->toIso8601String(),
            'profile' => [
                'id' => $userId,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'email_verified_at' => $this->iso8601($user->email_verified_at),
                'created_at' => $this->iso8601($user->created_at),
                'updated_at' => $this->iso8601($user->updated_at),
            ],
            'owned_businesses' => $this->rows('tenants', 'user_id', $userId, ['api_key', 'secret', 'webhook_secret']),
            'memberships' => $this->rows('tenant_memberships', 'user_id', $userId),
            'orders' => $this->rows('orders', 'user_id', $userId, ['payment_reference'], $this->crossTenantConnectionName()),
            'appointments' => $this->rows('appointments', 'user_id', $userId, [], $this->crossTenantConnectionName()),
            'reviews' => $this->rows('reviews', 'user_id', $userId),
            'favorites' => $this->rows('favorites', 'user_id', $userId),
            'follows' => $this->rows('follows', 'user_id', $userId),
            'loyalty_ledger' => $this->rows('loyalty_ledger', 'user_id', $userId, [], $this->crossTenantConnectionName()),
            'ai_usage' => $this->rows('ai_usage_logs', 'user_id', $userId, ['request_payload', 'response_payload', 'provider_request_id'], $this->crossTenantConnectionName()),
            'ai_tool_confirmations' => $this->rows('ai_tool_confirmations', 'actor_id', $userId, [
                'payload',
                'payload_hash',
                'confirmation_token',
                'confirmation_token_hash',
                'result',
            ], $this->crossTenantConnectionName()),
            'onboarding' => $this->rows('tenant_onboarding_states', 'user_id', $userId),
            'notifications' => $this->rows('notifications', 'notifiable_id', $userId),
        ];
    }

    private function iso8601(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->toIso8601String();
        }

        return CarbonImmutable::parse((string) $value)->toIso8601String();
    }

    public function delete(User $user): void
    {
        if ($user->isSuperAdmin()) {
            throw ValidationException::withMessages([
                'password' => 'La cuenta de plataforma debe transferirse y eliminarse mediante el procedimiento administrativo auditado.',
            ]);
        }

        if (Schema::hasTable('tenants') && DB::table('tenants')->where('user_id', $user->getKey())->exists()) {
            throw ValidationException::withMessages([
                'password' => 'Antes de eliminar tu cuenta debes transferir o cerrar los negocios que administras.',
            ]);
        }

        DB::transaction(function () use ($user): void {
            $userId = (string) $user->getKey();

            $this->deleteRows('sessions', 'user_id', $userId);
            $this->deleteRows('personal_access_tokens', 'tokenable_id', $userId);
            $this->deleteRows('push_subscriptions', 'subscribable_id', $userId);
            $this->deleteRows(
                'ai_tool_confirmations',
                'actor_id',
                $userId,
                connectionName: $this->crossTenantConnectionName(),
            );

            $user->forceFill([
                'remember_token' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->saveQuietly();

            $user->delete();
        });
    }

    /**
     * @param  list<string>  $hidden
     * @return list<array<string, mixed>>
     */
    private function rows(
        string $table,
        string $column,
        string $value,
        array $hidden = [],
        ?string $connectionName = null,
    ): array {
        $schema = Schema::connection($connectionName);
        if (! $schema->hasTable($table) || ! $schema->hasColumn($table, $column)) {
            return [];
        }

        return DB::connection($connectionName)->table($table)
            ->where($column, $value)
            ->orderBy($this->orderColumn($table, $connectionName))
            ->get()
            ->map(static function (object $row) use ($hidden): array {
                $data = (array) $row;

                foreach ($hidden as $key) {
                    unset($data[$key]);
                }

                foreach (array_keys($data) as $key) {
                    if (preg_match('/(?:password|secret|token|recovery_code)$/i', (string) $key) === 1) {
                        unset($data[$key]);
                    }
                }

                return $data;
            })
            ->values()
            ->all();
    }

    private function orderColumn(string $table, ?string $connectionName = null): string
    {
        $schema = Schema::connection($connectionName);
        foreach (['created_at', 'id'] as $column) {
            if ($schema->hasColumn($table, $column)) {
                return $column;
            }
        }

        return $schema->getColumnListing($table)[0];
    }

    private function deleteRows(
        string $table,
        string $column,
        string $value,
        ?string $typeColumn = null,
        ?string $type = null,
        ?string $connectionName = null,
    ): void {
        $schema = Schema::connection($connectionName);
        if (! $schema->hasTable($table) || ! $schema->hasColumn($table, $column)) {
            return;
        }

        $query = DB::connection($connectionName)->table($table)->where($column, $value);

        if ($typeColumn !== null && $type !== null && $schema->hasColumn($table, $typeColumn)) {
            $query->where($typeColumn, $type);
        }

        $query->delete();
    }

    private function crossTenantConnectionName(): ?string
    {
        return DB::connection()->getDriverName() === 'pgsql'
            && (bool) config('tenancy.postgres_rls.enabled', false)
                ? 'pgsql_platform'
                : null;
    }
}
