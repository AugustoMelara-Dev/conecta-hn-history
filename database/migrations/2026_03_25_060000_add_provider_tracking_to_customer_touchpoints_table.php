<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_touchpoints', function (Blueprint $table): void {
            $table->string('provider', 32)->nullable()->after('status');
            $table->string('provider_message_id', 128)->nullable()->after('provider');
            $table->string('delivery_status', 32)->nullable()->after('provider_message_id');
            $table->timestamp('delivered_at')->nullable()->after('sent_at');
            $table->timestamp('opened_at')->nullable()->after('delivered_at');
            $table->timestamp('clicked_at')->nullable()->after('opened_at');
            $table->timestamp('bounced_at')->nullable()->after('clicked_at');
            $table->timestamp('complained_at')->nullable()->after('bounced_at');
            $table->timestamp('replied_at')->nullable()->after('complained_at');
            $table->json('provider_payload')->nullable()->after('last_error');

            $table->index(['provider', 'provider_message_id'], 'customer_touchpoints_provider_lookup');
            $table->index(['tenant_id', 'delivery_status'], 'customer_touchpoints_delivery_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customer_touchpoints', function (Blueprint $table): void {
            $table->dropIndex('customer_touchpoints_provider_lookup');
            $table->dropIndex('customer_touchpoints_delivery_status_idx');

            $table->dropColumn([
                'provider',
                'provider_message_id',
                'delivery_status',
                'delivered_at',
                'opened_at',
                'clicked_at',
                'bounced_at',
                'complained_at',
                'replied_at',
                'provider_payload',
            ]);
        });
    }
};
