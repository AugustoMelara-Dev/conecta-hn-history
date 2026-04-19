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
        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table): void {
                if (! Schema::hasColumn('tenants', 'verification_status')) {
                    $table->string('verification_status', 32)->default('provisional')->index();
                }

                if (! Schema::hasColumn('tenants', 'verified_at')) {
                    $table->timestamp('verified_at')->nullable()->index();
                }

                if (! Schema::hasColumn('tenants', 'verification_reviewed_at')) {
                    $table->timestamp('verification_reviewed_at')->nullable();
                }

                if (! Schema::hasColumn('tenants', 'verification_rejection_reason')) {
                    $table->text('verification_rejection_reason')->nullable();
                }

                if (! Schema::hasColumn('tenants', 'publication_paused_at')) {
                    $table->timestamp('publication_paused_at')->nullable()->index();
                }

                if (! Schema::hasColumn('tenants', 'publication_pause_reason')) {
                    $table->text('publication_pause_reason')->nullable();
                }
            });

            DB::table('tenants')
                ->where('is_active', true)
                ->whereIn('subscription_status', ['active', 'trial'])
                ->update([
                    'verification_status' => 'verified',
                    'verified_at' => now(),
                ]);
        }

        if (! Schema::hasTable('tenant_business_claims')) {
            Schema::create('tenant_business_claims', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id')->index();
                $table->uuid('user_id')->index();
                $table->string('status', 32)->default('pending')->index();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('whatsapp_number', 30)->nullable();
                $table->timestamp('whatsapp_otp_verified_at')->nullable();
                $table->string('evidence_type', 32)->nullable();
                $table->string('evidence_url')->nullable();
                $table->text('evidence_notes')->nullable();
                $table->uuid('reviewed_by')->nullable()->index();
                $table->timestamp('reviewed_at')->nullable();
                $table->text('decision_notes')->nullable();
                $table->timestamps();

                $table->unique('tenant_id');
            });
        }

        if (! Schema::hasTable('business_claim_disputes')) {
            Schema::create('business_claim_disputes', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('tenant_id')->index();
                $table->uuid('reporter_user_id')->nullable()->index();
                $table->string('reporter_email')->nullable();
                $table->string('status', 32)->default('open')->index();
                $table->string('reason', 120);
                $table->text('details')->nullable();
                $table->uuid('resolved_by')->nullable()->index();
                $table->timestamp('resolved_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('legal_consents')) {
            Schema::create('legal_consents', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->index();
                $table->uuid('tenant_id')->nullable()->index();
                $table->string('terms_version', 50);
                $table->string('privacy_version', 50);
                $table->timestamp('accepted_at')->index();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('legal_consents')) {
            Schema::drop('legal_consents');
        }

        if (Schema::hasTable('business_claim_disputes')) {
            Schema::drop('business_claim_disputes');
        }

        if (Schema::hasTable('tenant_business_claims')) {
            Schema::drop('tenant_business_claims');
        }

        if (Schema::hasTable('tenants')) {
            Schema::table('tenants', function (Blueprint $table): void {
                $drops = [
                    'verification_status',
                    'verified_at',
                    'verification_reviewed_at',
                    'verification_rejection_reason',
                    'publication_paused_at',
                    'publication_pause_reason',
                ];

                foreach ($drops as $column) {
                    if (Schema::hasColumn('tenants', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
