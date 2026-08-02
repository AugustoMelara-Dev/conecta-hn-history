<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table): void {
            $table->char('code', 2)->primary();
            $table->string('name');
            $table->string('native_name')->nullable();
            $table->string('calling_code', 8)->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('currencies', function (Blueprint $table): void {
            $table->char('code', 3)->primary();
            $table->string('name');
            $table->string('symbol', 16);
            $table->unsignedTinyInteger('minor_unit')->default(2);
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('locales', function (Blueprint $table): void {
            $table->string('code', 16)->primary();
            $table->string('name');
            $table->string('native_name');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('timezones', function (Blueprint $table): void {
            $table->string('name', 64)->primary();
            $table->string('label');
            $table->string('utc_offset', 8)->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('market_packs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key', 32)->unique();
            $table->char('country_code', 2);
            $table->string('name');
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->foreign('country_code')->references('code')->on('countries')->restrictOnDelete();
        });

        Schema::create('market_pack_versions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('market_pack_id')->constrained('market_packs')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('status', 24)->default('draft');
            $table->string('default_locale_code', 16);
            $table->char('default_currency_code', 3);
            $table->string('default_timezone_name', 64);
            $table->json('supported_locales');
            $table->json('supported_currencies');
            $table->json('supported_timezones');
            $table->json('capabilities');
            $table->json('address_schema');
            $table->timestamp('effective_from')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['market_pack_id', 'version']);
            $table->foreign('default_locale_code')->references('code')->on('locales')->restrictOnDelete();
            $table->foreign('default_currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('default_timezone_name')->references('name')->on('timezones')->restrictOnDelete();
        });

        Schema::create('administrative_areas', function (Blueprint $table): void {
            $table->id();
            $table->char('country_code', 2);
            $table->foreignId('parent_id')->nullable()->constrained('administrative_areas')->nullOnDelete();
            $table->unsignedTinyInteger('level');
            $table->string('type', 48);
            $table->string('code', 64)->nullable();
            $table->string('name');
            $table->string('slug');
            $table->unsignedBigInteger('legacy_department_id')->nullable()->unique();
            $table->unsignedBigInteger('legacy_municipality_id')->nullable()->unique();
            $table->timestamps();

            $table->unique(['country_code', 'parent_id', 'level', 'slug'], 'admin_areas_hierarchy_unique');
            $table->index(['country_code', 'level']);
            $table->foreign('country_code')->references('code')->on('countries')->restrictOnDelete();
        });

        Schema::create('localities', function (Blueprint $table): void {
            $table->id();
            $table->char('country_code', 2);
            $table->foreignId('administrative_area_id')->nullable()->constrained('administrative_areas')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('timezone_name', 64);
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedBigInteger('legacy_city_id')->nullable()->unique();
            $table->boolean('is_active')->default(false);
            $table->timestamps();

            $table->unique(['country_code', 'administrative_area_id', 'slug'], 'localities_hierarchy_unique');
            $table->index(['country_code', 'is_active']);
            $table->foreign('country_code')->references('code')->on('countries')->restrictOnDelete();
            $table->foreign('timezone_name')->references('name')->on('timezones')->restrictOnDelete();
        });

        Schema::create('tenant_markets', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->unique()->constrained('tenants')->cascadeOnDelete();
            $table->foreignUuid('market_pack_version_id')->constrained('market_pack_versions')->restrictOnDelete();
            $table->char('country_code', 2);
            $table->string('locale_code', 16);
            $table->char('currency_code', 3);
            $table->string('timezone_name', 64);
            $table->boolean('is_primary')->default(true);
            $table->timestamps();

            $table->foreign('country_code')->references('code')->on('countries')->restrictOnDelete();
            $table->foreign('locale_code')->references('code')->on('locales')->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('timezone_name')->references('name')->on('timezones')->restrictOnDelete();
        });

        Schema::create('locations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->char('country_code', 2);
            $table->foreignId('administrative_area_id')->nullable()->constrained('administrative_areas')->nullOnDelete();
            $table->foreignId('locality_id')->nullable()->constrained('localities')->nullOnDelete();
            $table->string('locale_code', 16);
            $table->char('currency_code', 3);
            $table->string('timezone_name', 64);
            $table->string('name');
            $table->string('slug');
            $table->text('address')->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('contact_name')->nullable();
            $table->json('hours_data')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['tenant_id', 'slug']);
            $table->index(['tenant_id', 'is_primary']);
            $table->foreign('country_code')->references('code')->on('countries')->restrictOnDelete();
            $table->foreign('locale_code')->references('code')->on('locales')->restrictOnDelete();
            $table->foreign('currency_code')->references('code')->on('currencies')->restrictOnDelete();
            $table->foreign('timezone_name')->references('name')->on('timezones')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
        Schema::dropIfExists('tenant_markets');
        Schema::dropIfExists('localities');
        Schema::dropIfExists('administrative_areas');
        Schema::dropIfExists('market_pack_versions');
        Schema::dropIfExists('market_packs');
        Schema::dropIfExists('timezones');
        Schema::dropIfExists('locales');
        Schema::dropIfExists('currencies');
        Schema::dropIfExists('countries');
    }
};
