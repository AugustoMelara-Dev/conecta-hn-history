<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('social_identities')) {
            Schema::create('social_identities', function (Blueprint $table): void {
                $table->id();
                $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('user_type')->default('App\\Models\\User');
                $table->string('provider_name', 64);
                $table->string('provider_id', 191);
                $table->text('token')->nullable();
                $table->text('refresh_token')->nullable();
                $table->timestamp('token_expires_at')->nullable();
                $table->string('avatar', 2048)->nullable();
                $table->timestamps();

                $table->unique(['provider_name', 'provider_id'], 'social_identities_provider_unique');
                $table->index(['user_id', 'provider_name'], 'social_identities_user_provider_idx');
            });

            return;
        }

        Schema::table('social_identities', function (Blueprint $table): void {
            if (! Schema::hasColumn('social_identities', 'user_type')) {
                $table->string('user_type')->default('App\\Models\\User')->after('user_id');
            }

            if (! Schema::hasColumn('social_identities', 'refresh_token')) {
                $table->text('refresh_token')->nullable()->after('token');
            }

            if (! Schema::hasColumn('social_identities', 'token_expires_at')) {
                $table->timestamp('token_expires_at')->nullable()->after('refresh_token');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('social_identities')) {
            return;
        }

        Schema::table('social_identities', function (Blueprint $table): void {
            foreach (['refresh_token', 'token_expires_at'] as $column) {
                if (Schema::hasColumn('social_identities', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
