<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('directory_guide_request_metrics')) {
            return;
        }

        Schema::table('directory_guide_request_metrics', function (Blueprint $table): void {
            if (! Schema::hasColumn('directory_guide_request_metrics', 'trace_id')) {
                $table->string('trace_id', 64)->nullable()->after('request_id');
                $table->index('trace_id', 'dir_guide_metrics_trace_id_idx');
            }

            if (! Schema::hasColumn('directory_guide_request_metrics', 'endpoint')) {
                $table->string('endpoint', 40)->nullable()->after('trace_id');
                $table->index('endpoint', 'dir_guide_metrics_endpoint_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('directory_guide_request_metrics')) {
            return;
        }

        Schema::table('directory_guide_request_metrics', function (Blueprint $table): void {
            if (Schema::hasColumn('directory_guide_request_metrics', 'endpoint')) {
                $table->dropIndex('dir_guide_metrics_endpoint_idx');
                $table->dropColumn('endpoint');
            }

            if (Schema::hasColumn('directory_guide_request_metrics', 'trace_id')) {
                $table->dropIndex('dir_guide_metrics_trace_id_idx');
                $table->dropColumn('trace_id');
            }
        });
    }
};
