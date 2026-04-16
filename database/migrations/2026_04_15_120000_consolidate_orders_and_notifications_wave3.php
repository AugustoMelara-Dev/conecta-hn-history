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
        $this->alignOrders();
        $this->alignNotifications();
    }

    public function down(): void
    {
        $this->rollbackOrders();
        $this->rollbackNotifications();
    }

    private function alignOrders(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (! Schema::hasTable('orders_legacy_snapshot')) {
            Schema::create('orders_legacy_snapshot', function (Blueprint $table): void {
                $table->unsignedBigInteger('order_id')->primary();
                $table->text('legacy_customer_address')->nullable();
                $table->text('legacy_cancel_reason')->nullable();
                $table->decimal('legacy_discount_amount', 12, 2)->nullable();
                $table->timestamp('captured_at')->nullable();
            });
        }

        $legacyCustomerAddressExpression = Schema::hasColumn('orders', 'customer_address')
            ? 'customer_address'
            : 'NULL';
        $legacyCancelReasonExpression = Schema::hasColumn('orders', 'cancel_reason')
            ? 'cancel_reason'
            : 'NULL';
        $legacyDiscountExpression = Schema::hasColumn('orders', 'discount_amount')
            ? 'discount_amount'
            : 'NULL';

        DB::table('orders_legacy_snapshot')->insertUsing(
            ['order_id', 'legacy_customer_address', 'legacy_cancel_reason', 'legacy_discount_amount', 'captured_at'],
            DB::table('orders')
                ->selectRaw(
                    "id, {$legacyCustomerAddressExpression}, {$legacyCancelReasonExpression}, {$legacyDiscountExpression}, CURRENT_TIMESTAMP"
                )
                ->whereNotIn('id', DB::table('orders_legacy_snapshot')->select('order_id'))
        );

        if (Schema::hasColumn('orders', 'customer_address') && Schema::hasColumn('orders', 'delivery_address')) {
            DB::table('orders')
                ->whereNull('delivery_address')
                ->whereNotNull('customer_address')
                ->update(['delivery_address' => DB::raw('customer_address')]);
        }

        if (Schema::hasColumn('orders', 'cancel_reason') && Schema::hasColumn('orders', 'cancellation_reason')) {
            DB::table('orders')
                ->whereNull('cancellation_reason')
                ->whereNotNull('cancel_reason')
                ->update(['cancellation_reason' => DB::raw('cancel_reason')]);
        }

        if (Schema::hasColumn('orders', 'discount_amount') && Schema::hasColumn('orders', 'discount')) {
            DB::table('orders')
                ->where(function ($query): void {
                    $query->whereNull('discount')->orWhere('discount', 0);
                })
                ->whereNotNull('discount_amount')
                ->update(['discount' => DB::raw('discount_amount')]);
        }

        DB::table('orders')->whereNull('subtotal')->update(['subtotal' => 0]);
        DB::table('orders')->whereNull('discount')->update(['discount' => 0]);
        DB::table('orders')->whereNull('delivery_fee')->update(['delivery_fee' => 0]);
        DB::table('orders')->whereNull('total')->update(['total' => 0]);
        DB::table('orders')->whereNull('payment_status')->update(['payment_status' => 'pending']);
        DB::table('orders')->whereNull('status')->update(['status' => 'pending']);

        if (Schema::hasColumn('orders', 'order_number')) {
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                DB::statement("UPDATE orders SET order_number = CONCAT('#', id) WHERE order_number IS NULL OR order_number = ''");
            } elseif ($driver === 'pgsql') {
                DB::statement("UPDATE orders SET order_number = '#' || id::text WHERE order_number IS NULL OR order_number = ''");
            } else {
                DB::table('orders')
                    ->where(function ($query): void {
                        $query->whereNull('order_number')->orWhere('order_number', '');
                    })
                    ->orderBy('id')
                    ->chunkById(500, function ($rows): void {
                        foreach ($rows as $row) {
                            DB::table('orders')
                                ->where('id', $row->id)
                                ->update(['order_number' => '#'.$row->id]);
                        }
                    });
            }
        }

        $legacyColumns = collect(['customer_address', 'cancel_reason', 'discount_amount'])
            ->filter(static fn (string $column): bool => Schema::hasColumn('orders', $column))
            ->values()
            ->all();

        if ($legacyColumns !== []) {
            Schema::table('orders', function (Blueprint $table) use ($legacyColumns): void {
                $table->dropColumn($legacyColumns);
            });
        }

        if (! $this->hasIndexWithColumns('orders', ['tenant_id', 'status', 'created_at'], false)) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->index(['tenant_id', 'status', 'created_at'], 'orders_tenant_status_created_at_idx');
            });
        }

        if (! $this->hasIndexWithColumns('orders', ['tenant_id', 'payment_status'], false)) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->index(['tenant_id', 'payment_status'], 'orders_tenant_payment_status_idx');
            });
        }

        if (! $this->hasIndexWithColumns('orders', ['order_number'], true)) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->unique(['order_number'], 'orders_order_number_unique_global');
            });
        }
    }

    private function alignNotifications(): void
    {
        if (! Schema::hasTable('notifications')) {
            Schema::create('notifications', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('type');
                $table->uuidMorphs('notifiable');
                $table->text('data');
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
            });

            return;
        }

        if (! Schema::hasTable('notifications_legacy_snapshot')) {
            Schema::create('notifications_legacy_snapshot', function (Blueprint $table): void {
                $table->string('notification_id', 64)->primary();
                $table->string('legacy_notifiable_id', 191)->nullable();
                $table->string('legacy_notifiable_type')->nullable();
                $table->timestamp('captured_at')->nullable();
            });
        }

        if (Schema::hasColumns('notifications', ['id', 'notifiable_id', 'notifiable_type'])) {
            DB::table('notifications_legacy_snapshot')->insertUsing(
                ['notification_id', 'legacy_notifiable_id', 'legacy_notifiable_type', 'captured_at'],
                DB::table('notifications')
                    ->selectRaw('CAST(id AS CHAR), CAST(notifiable_id AS CHAR), notifiable_type, CURRENT_TIMESTAMP')
                    ->whereNotIn(
                        DB::raw('CAST(id AS CHAR)'),
                        DB::table('notifications_legacy_snapshot')->select('notification_id')
                    )
            );
        }

        if (! Schema::hasColumns('notifications', ['notifiable_id', 'notifiable_type'])) {
            return;
        }

        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'pgsql'], true)
            && ! $this->columnTypeMatches('notifications', 'notifiable_id', ['char', 'string', 'varchar', 'uuid'])
        ) {
            if ($this->hasIndex('notifications', 'notifications_notifiable_type_notifiable_id_index')) {
                Schema::table('notifications', function (Blueprint $table): void {
                    $table->dropIndex('notifications_notifiable_type_notifiable_id_index');
                });
            }

            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE notifications MODIFY notifiable_id CHAR(36) NOT NULL');
                DB::statement('ALTER TABLE notifications MODIFY notifiable_type VARCHAR(255) NOT NULL');
            }

            if ($driver === 'pgsql') {
                DB::statement('ALTER TABLE notifications ALTER COLUMN notifiable_id TYPE VARCHAR(36)');
                DB::statement('ALTER TABLE notifications ALTER COLUMN notifiable_type TYPE VARCHAR(255)');
            }
        }

        if (! $this->hasIndexWithColumns('notifications', ['notifiable_type', 'notifiable_id'], false)) {
            Schema::table('notifications', function (Blueprint $table): void {
                $table->index(['notifiable_type', 'notifiable_id'], 'notifications_notifiable_type_notifiable_id_index');
            });
        }
    }

    private function rollbackOrders(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasTable('orders_legacy_snapshot')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'customer_address')) {
                $table->text('customer_address')->nullable()->after('customer_email');
            }

            if (! Schema::hasColumn('orders', 'cancel_reason')) {
                $table->string('cancel_reason')->nullable()->after('status');
            }

            if (! Schema::hasColumn('orders', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 2)->nullable()->after('delivery_fee');
            }
        });

        DB::table('orders_legacy_snapshot')
            ->orderBy('order_id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('orders')
                        ->where('id', $row->order_id)
                        ->update([
                            'customer_address' => $row->legacy_customer_address,
                            'cancel_reason' => $row->legacy_cancel_reason,
                            'discount_amount' => $row->legacy_discount_amount,
                        ]);
                }
            }, 'order_id');
    }

    private function rollbackNotifications(): void
    {
        if (! Schema::hasTable('notifications') || ! Schema::hasTable('notifications_legacy_snapshot')) {
            return;
        }

        if (! Schema::hasColumns('notifications', ['id', 'notifiable_id', 'notifiable_type'])) {
            return;
        }

        DB::table('notifications_legacy_snapshot')
            ->orderBy('notification_id')
            ->chunk(500, function ($rows): void {
                foreach ($rows as $row) {
                    DB::table('notifications')
                        ->where('id', $row->notification_id)
                        ->update([
                            'notifiable_id' => $row->legacy_notifiable_id,
                            'notifiable_type' => $row->legacy_notifiable_type,
                        ]);
                }
            });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        return collect(Schema::getIndexes($table))
            ->contains(static fn (array $index): bool => (string) ($index['name'] ?? '') === $indexName);
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function hasIndexWithColumns(string $table, array $columns, ?bool $unique = null): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        $normalizedColumns = array_values($columns);

        return collect(Schema::getIndexes($table))
            ->contains(static function (array $index) use ($normalizedColumns, $unique): bool {
                $indexColumns = array_values((array) ($index['columns'] ?? []));
                $isUnique = (bool) ($index['unique'] ?? false);

                if ($indexColumns !== $normalizedColumns) {
                    return false;
                }

                if ($unique === null) {
                    return true;
                }

                return $isUnique === $unique;
            });
    }

    /**
     * @param  array<int, string>  $allowedFragments
     */
    private function columnTypeMatches(string $table, string $column, array $allowedFragments): bool
    {
        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return false;
        }

        $columnMeta = collect(Schema::getColumns($table))->firstWhere('name', $column);
        $actualType = strtolower((string) ($columnMeta['type'] ?? $columnMeta['type_name'] ?? ''));

        return collect($allowedFragments)
            ->map(static fn (string $fragment): string => strtolower($fragment))
            ->contains(static fn (string $fragment): bool => str_contains($actualType, $fragment));
    }
};
