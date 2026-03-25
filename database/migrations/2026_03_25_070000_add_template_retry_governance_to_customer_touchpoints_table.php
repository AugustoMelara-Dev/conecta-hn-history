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
            $table->string('template_key', 128)->nullable()->after('automation_key');
            $table->unsignedSmallInteger('attempt_count')->default(0)->after('delivery_status');
            $table->unsignedSmallInteger('retry_limit')->default(0)->after('attempt_count');
            $table->timestamp('last_attempt_at')->nullable()->after('sent_at');
            $table->timestamp('next_retry_at')->nullable()->after('last_attempt_at');

            $table->index(['tenant_id', 'template_key'], 'customer_touchpoints_template_idx');
            $table->index(['tenant_id', 'next_retry_at'], 'customer_touchpoints_retry_queue_idx');
        });
    }

    public function down(): void
    {
        Schema::table('customer_touchpoints', function (Blueprint $table): void {
            $table->dropIndex('customer_touchpoints_template_idx');
            $table->dropIndex('customer_touchpoints_retry_queue_idx');

            $table->dropColumn([
                'template_key',
                'attempt_count',
                'retry_limit',
                'last_attempt_at',
                'next_retry_at',
            ]);
        });
    }
};
