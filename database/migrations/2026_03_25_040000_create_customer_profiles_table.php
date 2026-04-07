<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('customer_profiles')) {
            Schema::create('customer_profiles', function (Blueprint $table): void {
                $table->id();
                $table->char('tenant_id', 36);
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
                $table->string('normalized_email')->nullable();
                $table->string('normalized_phone', 32)->nullable();
                $table->string('name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->string('preferred_channel')->default('whatsapp');
                $table->string('lifecycle_stage')->default('prospect');
                $table->string('customer_segment')->default('new');
                $table->string('manual_segment')->nullable();
                $table->text('crm_notes')->nullable();
                $table->json('tags')->nullable();
                $table->boolean('marketing_opt_in')->default(false);
                $table->boolean('whatsapp_opt_in')->default(false);
                $table->boolean('email_opt_in')->default(false);
                $table->unsignedInteger('orders_count')->default(0);
                $table->unsignedInteger('appointments_count')->default(0);
                $table->unsignedInteger('leads_count')->default(0);
                $table->decimal('total_spent', 12, 2)->default(0);
                $table->decimal('average_ticket', 12, 2)->default(0);
                $table->timestamp('first_seen_at')->nullable();
                $table->timestamp('last_seen_at')->nullable();
                $table->timestamp('last_order_at')->nullable();
                $table->timestamp('last_appointment_at')->nullable();
                $table->timestamp('last_lead_at')->nullable();
                $table->timestamp('last_contacted_at')->nullable();
                $table->unsignedSmallInteger('reactivation_score')->default(0);
                $table->timestamps();

                $table->unique(['tenant_id', 'normalized_email'], 'customer_profiles_tenant_email_unique');
                $table->unique(['tenant_id', 'normalized_phone'], 'customer_profiles_tenant_phone_unique');
                $table->index(['tenant_id', 'lifecycle_stage'], 'customer_profiles_tenant_lifecycle_idx');
                $table->index(['tenant_id', 'customer_segment'], 'customer_profiles_tenant_segment_idx');
                $table->index(['tenant_id', 'reactivation_score'], 'customer_profiles_tenant_reactivation_idx');
                $table->index(['tenant_id', 'last_seen_at'], 'customer_profiles_tenant_last_seen_idx');
            });
        }

        if (! Schema::hasColumn('orders', 'customer_profile_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->foreignId('customer_profile_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('customer_profiles')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('appointments', 'customer_profile_id')) {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->foreignId('customer_profile_id')
                    ->nullable()
                    ->after('user_id')
                    ->constrained('customer_profiles')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('leads', 'customer_profile_id')) {
            Schema::table('leads', function (Blueprint $table): void {
                $table->foreignId('customer_profile_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('customer_profiles')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('leads', 'customer_profile_id')) {
            Schema::table('leads', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('customer_profile_id');
            });
        }

        if (Schema::hasColumn('appointments', 'customer_profile_id')) {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('customer_profile_id');
            });
        }

        if (Schema::hasColumn('orders', 'customer_profile_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('customer_profile_id');
            });
        }

        Schema::dropIfExists('customer_profiles');
    }
};
