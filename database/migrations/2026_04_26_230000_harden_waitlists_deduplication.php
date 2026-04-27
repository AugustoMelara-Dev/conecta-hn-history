<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waitlists', function (Blueprint $table): void {
            if (! Schema::hasColumn('waitlists', 'normalized_email')) {
                $table->string('normalized_email')->nullable()->after('customer_email');
            }
        });

        DB::table('waitlists')
            ->whereNull('normalized_email')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('waitlists')
                        ->where('id', $row->id)
                        ->update([
                            'normalized_email' => strtolower(trim((string) $row->customer_email)),
                        ]);
                }
            });

        DB::table('waitlists')
            ->select([
                'tenant_id',
                'service_id',
                'normalized_email',
                'requested_date',
                'status',
                DB::raw('MIN(id) as keep_id'),
            ])
            ->groupBy('tenant_id', 'service_id', 'normalized_email', 'requested_date', 'status')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('keep_id')
            ->chunk(200, function ($duplicates): void {
                foreach ($duplicates as $duplicate) {
                    DB::table('waitlists')
                        ->where('tenant_id', $duplicate->tenant_id)
                        ->where('service_id', $duplicate->service_id)
                        ->where('normalized_email', $duplicate->normalized_email)
                        ->where('requested_date', $duplicate->requested_date)
                        ->where('status', $duplicate->status)
                        ->where('id', '<>', $duplicate->keep_id)
                        ->delete();
                }
            });

        Schema::table('waitlists', function (Blueprint $table): void {
            $table->unique(
                ['tenant_id', 'service_id', 'normalized_email', 'requested_date', 'status'],
                'waitlists_active_dedupe_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('waitlists', function (Blueprint $table): void {
            $table->dropUnique('waitlists_active_dedupe_unique');

            if (Schema::hasColumn('waitlists', 'normalized_email')) {
                $table->dropColumn('normalized_email');
            }
        });
    }
};
