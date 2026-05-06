<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table): void {
            if (! Schema::hasColumn('tenants', 'verification_status')) {
                $table->string('verification_status', 32)
                    ->default('provisional')
                    ->after('subscription_status');
                $table->index('verification_status', 'tenants_verification_status_idx');
            }

            if (! Schema::hasColumn('tenants', 'verified_at')) {
                $table->timestamp('verified_at')->nullable()->after('verification_status');
                $table->index('verified_at', 'tenants_verified_at_idx');
            }

            if (! Schema::hasColumn('tenants', 'verification_reviewed_at')) {
                $table->timestamp('verification_reviewed_at')->nullable()->after('verified_at');
            }

            if (! Schema::hasColumn('tenants', 'verification_rejection_reason')) {
                $table->text('verification_rejection_reason')->nullable()->after('verification_reviewed_at');
            }

            if (! Schema::hasColumn('tenants', 'publication_paused_at')) {
                $table->timestamp('publication_paused_at')->nullable()->after('verification_rejection_reason');
                $table->index('publication_paused_at', 'tenants_publication_paused_at_idx');
            }

            if (! Schema::hasColumn('tenants', 'publication_pause_reason')) {
                $table->text('publication_pause_reason')->nullable()->after('publication_paused_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('tenants')) {
            return;
        }

        Schema::table('tenants', function (Blueprint $table): void {
            $drops = [
                'publication_pause_reason',
                'publication_paused_at',
                'verification_rejection_reason',
                'verification_reviewed_at',
                'verified_at',
                'verification_status',
            ];

            foreach ($drops as $column) {
                if (Schema::hasColumn('tenants', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
