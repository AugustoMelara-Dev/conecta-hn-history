<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add Logo Storage Path to Tenants
 *
 * ╔══════════════════════════════════════════════════════════════════════════╗
 * ║  BRANDING ASSETS: Tenant logo for storefront and admin panel            ║
 * ╚══════════════════════════════════════════════════════════════════════════╝
 *
 * USAGE:
 * - HandleInertiaRequests middleware injects logo_url into global state
 * - React components display tenant branding automatically
 *
 * STORAGE STRATEGY:
 * - Logos stored in: storage/app/public/logos/
 * - Accessed via: Storage::url($tenant->logo_path)
 * - External CDNs supported via full URL storage (hence 2048 char limit)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (! Schema::hasColumn('tenants', 'logo_path')) {
                $table->string('logo_path', 2048)->nullable()
                    ->after('slug')
                    ->comment('Relative path to logo in storage/app/public/');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            if (Schema::hasColumn('tenants', 'logo_path')) {
                $table->dropColumn('logo_path');
            }
        });
    }
};
