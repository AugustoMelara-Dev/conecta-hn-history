<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds social authentication fields for OAuth providers.
     * Strategy: "Match by Email" - links existing accounts silently.
     */
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // Social Provider IDs (nullable for traditional email/password users)
            if (! Schema::hasColumn('users', 'google_id')) {
                $table->string('google_id')->nullable()->after('password');
            }

            if (! Schema::hasColumn('users', 'facebook_id')) {
                $table->string('facebook_id')->nullable()->after('google_id');
            }

            // Avatar from social provider
            if (! Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable()->after('facebook_id');
            }

            // Indexes for fast lookups
            if (! $this->indexExists('users', 'users_google_id_index')) {
                $table->index('google_id');
            }

            if (! $this->indexExists('users', 'users_facebook_id_index')) {
                $table->index('facebook_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['google_id']);
            $table->dropIndex(['facebook_id']);
            $table->dropColumn(['google_id', 'facebook_id', 'avatar']);
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return Schema::getConnection()
            ->table('information_schema.statistics')
            ->where('table_schema', Schema::getConnection()->getDatabaseName())
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
