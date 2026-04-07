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
            if (! Schema::hasColumn('customer_touchpoints', 'provider')) {
                $table->string('provider', 32)->nullable()->after('status');
            }

            if (! Schema::hasColumn('customer_touchpoints', 'provider_message_id')) {
                $table->string('provider_message_id', 128)->nullable()->after('provider');
            }

            if (! Schema::hasColumn('customer_touchpoints', 'delivery_status')) {
                $table->string('delivery_status', 32)->nullable()->after('provider_message_id');
            }

            if (! Schema::hasColumn('customer_touchpoints', 'delivered_at')) {
                $table->timestamp('delivered_at')->nullable()->after('sent_at');
            }

            if (! Schema::hasColumn('customer_touchpoints', 'opened_at')) {
                $table->timestamp('opened_at')->nullable()->after('delivered_at');
            }

            if (! Schema::hasColumn('customer_touchpoints', 'clicked_at')) {
                $table->timestamp('clicked_at')->nullable()->after('opened_at');
            }

            if (! Schema::hasColumn('customer_touchpoints', 'bounced_at')) {
                $table->timestamp('bounced_at')->nullable()->after('clicked_at');
            }

            if (! Schema::hasColumn('customer_touchpoints', 'complained_at')) {
                $table->timestamp('complained_at')->nullable()->after('bounced_at');
            }

            if (! Schema::hasColumn('customer_touchpoints', 'replied_at')) {
                $table->timestamp('replied_at')->nullable()->after('complained_at');
            }

            if (! Schema::hasColumn('customer_touchpoints', 'provider_payload')) {
                $table->json('provider_payload')->nullable()->after('last_error');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_touchpoints', function (Blueprint $table): void {
            $columnsToDrop = array_values(array_filter([
                Schema::hasColumn('customer_touchpoints', 'provider') ? 'provider' : null,
                Schema::hasColumn('customer_touchpoints', 'provider_message_id') ? 'provider_message_id' : null,
                Schema::hasColumn('customer_touchpoints', 'delivery_status') ? 'delivery_status' : null,
                Schema::hasColumn('customer_touchpoints', 'delivered_at') ? 'delivered_at' : null,
                Schema::hasColumn('customer_touchpoints', 'opened_at') ? 'opened_at' : null,
                Schema::hasColumn('customer_touchpoints', 'clicked_at') ? 'clicked_at' : null,
                Schema::hasColumn('customer_touchpoints', 'bounced_at') ? 'bounced_at' : null,
                Schema::hasColumn('customer_touchpoints', 'complained_at') ? 'complained_at' : null,
                Schema::hasColumn('customer_touchpoints', 'replied_at') ? 'replied_at' : null,
                Schema::hasColumn('customer_touchpoints', 'provider_payload') ? 'provider_payload' : null,
            ]));

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
