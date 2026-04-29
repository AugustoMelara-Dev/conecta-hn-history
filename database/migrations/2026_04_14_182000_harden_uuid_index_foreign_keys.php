<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->hardenOneTimePasswordsTenantFk();
        $this->hardenTenantOnboardingStateFks();
    }

    public function down(): void
    {
        if (Schema::hasTable('one_time_passwords') && $this->hasForeign('one_time_passwords', 'one_time_passwords_tenant_id_foreign')) {
            Schema::table('one_time_passwords', function (Blueprint $table): void {
                $table->dropForeign('one_time_passwords_tenant_id_foreign');
            });
        }

        if (Schema::hasTable('tenant_onboarding_states')) {
            Schema::table('tenant_onboarding_states', function (Blueprint $table): void {
                if ($this->hasForeign('tenant_onboarding_states', 'tenant_onboarding_states_tenant_id_foreign')) {
                    $table->dropForeign('tenant_onboarding_states_tenant_id_foreign');
                }

                if ($this->hasForeign('tenant_onboarding_states', 'tenant_onboarding_states_user_id_foreign')) {
                    $table->dropForeign('tenant_onboarding_states_user_id_foreign');
                }
            });
        }
    }

    private function hardenOneTimePasswordsTenantFk(): void
    {
        if (! Schema::hasTable('one_time_passwords') || ! Schema::hasTable('tenants') || ! Schema::hasColumn('one_time_passwords', 'tenant_id')) {
            return;
        }

        DB::table('one_time_passwords')
            ->whereNotIn('tenant_id', DB::table('tenants')->select('id'))
            ->delete();

        if (! $this->hasForeign('one_time_passwords', 'one_time_passwords_tenant_id_foreign')) {
            Schema::table('one_time_passwords', function (Blueprint $table): void {
                $table->foreign('tenant_id')
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();
            });
        }
    }

    private function hardenTenantOnboardingStateFks(): void
    {
        if (! Schema::hasTable('tenant_onboarding_states') || ! Schema::hasColumn('tenant_onboarding_states', 'tenant_id')) {
            return;
        }

        DB::table('tenant_onboarding_states')
            ->whereNotIn('tenant_id', DB::table('tenants')->select('id'))
            ->delete();

        if (! $this->hasForeign('tenant_onboarding_states', 'tenant_onboarding_states_tenant_id_foreign')) {
            Schema::table('tenant_onboarding_states', function (Blueprint $table): void {
                $table->foreign('tenant_id')
                    ->references('id')
                    ->on('tenants')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasColumn('tenant_onboarding_states', 'user_id') || ! Schema::hasTable('users')) {
            return;
        }

        DB::table('tenant_onboarding_states')
            ->whereNotNull('user_id')
            ->whereNotIn('user_id', DB::table('users')->select('id'))
            ->update(['user_id' => null]);

        if (! $this->hasForeign('tenant_onboarding_states', 'tenant_onboarding_states_user_id_foreign')) {
            Schema::table('tenant_onboarding_states', function (Blueprint $table): void {
                $table->foreign('user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    private function hasForeign(string $table, string $foreignName): bool
    {
        if (DB::getDriverName() === 'mysql') {
            return DB::table('information_schema.table_constraints')
                ->whereRaw('constraint_schema = database()')
                ->where('table_name', $table)
                ->where('constraint_type', 'FOREIGN KEY')
                ->where('constraint_name', $foreignName)
                ->exists();
        }

        return collect(Schema::getForeignKeys($table))
            ->contains(static fn (array $foreign): bool => (string) ($foreign['name'] ?? '') === $foreignName);
    }
};
