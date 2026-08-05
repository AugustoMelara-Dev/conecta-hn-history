<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('loyalty_ledger', 'idempotency_key')) {
            Schema::table('loyalty_ledger', function (Blueprint $table): void {
                $table->string('idempotency_key', 191)->nullable()->after('order_id');
            });
        }

        $duplicateHistoricalAwards = DB::table('loyalty_ledger')
            ->select(['tenant_id', 'order_id'])
            ->where('type', 'earn')
            ->whereNotNull('order_id')
            ->groupBy('tenant_id', 'order_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateHistoricalAwards) {
            throw new \RuntimeException(
                'Cannot enable loyalty award idempotency while duplicate historical earn entries exist.',
            );
        }

        DB::table('loyalty_ledger')
            ->where('type', 'earn')
            ->whereNotNull('order_id')
            ->orderBy('id')
            ->chunkById(500, static function ($entries): void {
                foreach ($entries as $entry) {
                    DB::table('loyalty_ledger')
                        ->where('id', $entry->id)
                        ->update([
                            'idempotency_key' => "order:{$entry->order_id}:earn",
                        ]);
                }
            });

        $hasUniqueIndex = collect(Schema::getIndexes('loyalty_ledger'))
            ->contains(static fn (array $index): bool => (string) $index['name'] === 'loyalty_ledger_tenant_idempotency_unique');

        if (! $hasUniqueIndex) {
            Schema::table('loyalty_ledger', function (Blueprint $table): void {
                $table->unique(
                    ['tenant_id', 'idempotency_key'],
                    'loyalty_ledger_tenant_idempotency_unique',
                );
            });
        }
    }

    public function down(): void
    {
        Schema::table('loyalty_ledger', function (Blueprint $table): void {
            $table->dropUnique('loyalty_ledger_tenant_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
