<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_billing_references', function (Blueprint $table): void {
            $table->id();
            $table->uuid('tenant_id');
            $table->string('provider', 32);
            $table->string('provider_customer_id', 128)->nullable();
            $table->string('provider_subscription_id', 128)->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->unique(['tenant_id', 'provider'], 'tenant_billing_refs_tenant_provider_unique');
            $table->index(['provider', 'provider_customer_id'], 'tenant_billing_refs_customer_idx');
            $table->index(['provider', 'provider_subscription_id'], 'tenant_billing_refs_subscription_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_billing_references');
    }
};
