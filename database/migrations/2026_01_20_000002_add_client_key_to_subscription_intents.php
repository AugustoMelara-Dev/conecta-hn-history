<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('subscription_intents', function (Blueprint $table) {
            // The Client's Idempotency Key (from Header)
            $table->string('client_idempotency_key')->after('tenant_id');

            // The Mathematical Guardian (Composite Unique Index)
            // Ensures strict 1:1 mapping between Client Intent and System Intent per Tenant.
            $table->unique(['tenant_id', 'client_idempotency_key'], 'unique_client_intent');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_intents', function (Blueprint $table) {
            $table->dropUnique('unique_client_intent');
            $table->dropColumn('client_idempotency_key');
        });
    }
};
