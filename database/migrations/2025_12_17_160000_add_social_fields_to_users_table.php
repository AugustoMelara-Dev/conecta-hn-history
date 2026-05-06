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
        Schema::table('users', function (Blueprint $table) {
            // Social Provider IDs (nullable for traditional email/password users)
            $table->string('google_id')->nullable()->after('password');
            $table->string('facebook_id')->nullable()->after('google_id');

            // Avatar from social provider
            $table->string('avatar')->nullable()->after('facebook_id');

            // Indexes for fast lookups
            $table->index('google_id');
            $table->index('facebook_id');
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
};
