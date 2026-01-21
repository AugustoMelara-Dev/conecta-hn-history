<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Analytics Tables Migration
 *
 * Creates tables optimized for write-heavy time-series data:
 * - tenant_visits: Page view tracking with daily deduplication
 * - interactions: Conversion events (WhatsApp clicks, calls)
 *
 * PRIVACY: IP addresses are hashed with daily salt (no PII stored)
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ═══════════════════════════════════════════════════════════════════════
        // TENANT VISITS: Page view tracking with session fingerprinting
        // ═══════════════════════════════════════════════════════════════════════
        // Note: May already exist from earlier migration - use conditional creation
        if (! Schema::hasTable('tenant_visits')) {
            Schema::create('tenant_visits', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('tenant_id')->constrained()->onDelete('cascade');

                // Privacy-compliant tracking (no raw IPs)
                // Hash = sha256(IP + User-Agent + Tenant ID + Daily Salt)
                $table->string('session_hash', 64)->comment('Daily unique fingerprint');
                $table->string('user_agent', 512)->nullable();
                $table->string('referer', 512)->nullable()->comment('Traffic source');

                // Device classification (computed from UA)
                $table->enum('device_type', ['mobile', 'tablet', 'desktop', 'bot'])->default('desktop');

                $table->timestamps();

                // ═══════════════════════════════════════════════════════════════
                // INDEXES: Optimized for time-range queries and deduplication
                // ═══════════════════════════════════════════════════════════════
                $table->index(['tenant_id', 'created_at']);   // Dashboard queries
                $table->index(['tenant_id', 'session_hash']); // Deduplication lookup
            });
        } else {
            // Add missing columns to existing table if needed
            Schema::table('tenant_visits', function (Blueprint $table) {
                if (! Schema::hasColumn('tenant_visits', 'session_hash')) {
                    $table->string('session_hash', 64)->nullable()->after('user_agent');
                }
                if (! Schema::hasColumn('tenant_visits', 'referer')) {
                    $table->string('referer', 512)->nullable()->after('session_hash');
                }
                if (! Schema::hasColumn('tenant_visits', 'device_type')) {
                    $table->string('device_type', 20)->default('desktop')->after('referer');
                }
            });
        }

        // ═══════════════════════════════════════════════════════════════════════
        // INTERACTIONS: Conversion event tracking
        // ═══════════════════════════════════════════════════════════════════════
        // Note: May already exist from earlier migration - use conditional creation
        if (! Schema::hasTable('interactions')) {
            Schema::create('interactions', function (Blueprint $table) {
                $table->id();
                $table->foreignUuid('tenant_id')->constrained()->onDelete('cascade');
                $table->foreignId('product_id')->nullable()->constrained()->onDelete('set null');

                // Event type (hard conversions)
                $table->enum('type', [
                    'whatsapp_click',   // Intent to purchase
                    'call',             // Phone call intent
                    'location_view',    // Map/directions view
                    'cart_add',         // Added to cart
                    'social_share',     // Shared on social media
                ]);

                // Contextual data (JSON for flexibility)
                $table->json('metadata')->nullable()->comment('Event context');

                $table->timestamps();

                // ═══════════════════════════════════════════════════════════════
                // INDEXES: Product performance and daily aggregation
                // ═══════════════════════════════════════════════════════════════
                $table->index(['tenant_id', 'created_at']);
                $table->index(['tenant_id', 'type']);
                $table->index(['product_id', 'created_at']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('interactions');
        Schema::dropIfExists('tenant_visits');
    }
};
