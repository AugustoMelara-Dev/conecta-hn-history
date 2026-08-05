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
        Schema::table('tenant_memberships', function (Blueprint $table): void {
            $table->string('status', 16)->default('active')->after('granted_permissions');
            $table->timestamp('invited_at')->nullable()->after('status');
            $table->timestamp('suspended_at')->nullable()->after('accepted_at');
            $table->timestamp('revoked_at')->nullable()->after('suspended_at');
            $table->foreignUuid('revoked_by_user_id')
                ->nullable()
                ->after('revoked_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->index(['tenant_id', 'status']);
        });

        DB::table('tenant_memberships')
            ->whereNull('accepted_at')
            ->update(['status' => 'pending']);

        Schema::create('tenant_invitations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('email', 254);
            $table->string('role', 32);
            $table->string('preset_key', 64)->nullable();
            $table->json('granted_permissions')->nullable();
            $table->char('token_hash', 64)->unique();
            $table->foreignUuid('invited_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->foreignUuid('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'email']);
            $table->index(['tenant_id', 'expires_at']);
        });

        Schema::create('authenticated_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->char('session_id_hash', 64)->unique();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('current_tenant_id')->nullable()->constrained('tenants')->nullOnDelete();
            $table->string('device_name', 120)->nullable();
            $table->char('ip_address_hash', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('last_authenticated_at');
            $table->timestamp('mfa_verified_at')->nullable();
            $table->timestamp('last_seen_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revocation_reason', 80)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
            $table->index(['current_tenant_id', 'revoked_at']);
            $table->index('last_seen_at');
        });

        Schema::create('impersonation_grants', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('operator_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUuid('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->char('session_id_hash', 64);
            $table->text('reason');
            $table->timestamp('expires_at');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['operator_user_id', 'revoked_at']);
            $table->index(['tenant_id', 'expires_at']);
            $table->index(['target_user_id', 'ended_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impersonation_grants');
        Schema::dropIfExists('authenticated_sessions');
        Schema::dropIfExists('tenant_invitations');

        Schema::table('tenant_memberships', function (Blueprint $table): void {
            $table->dropForeign(['revoked_by_user_id']);
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropColumn([
                'status',
                'invited_at',
                'suspended_at',
                'revoked_at',
                'revoked_by_user_id',
            ]);
        });
    }
};
