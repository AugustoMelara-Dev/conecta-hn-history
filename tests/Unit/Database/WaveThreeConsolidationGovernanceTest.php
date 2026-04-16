<?php

declare(strict_types=1);

namespace Tests\Unit\Database;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class WaveThreeConsolidationGovernanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_wave_three_alignment_consolidates_orders_and_notifications_with_snapshots(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
        ]);

        $orderId = (int) DB::table('orders')->insertGetId([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'order_number' => '#9001',
            'subtotal' => 120,
            'discount' => 0,
            'delivery_fee' => 10,
            'total' => 130,
            'payment_status' => 'pending',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'customer_address')) {
                $table->text('customer_address')->nullable();
            }

            if (! Schema::hasColumn('orders', 'cancel_reason')) {
                $table->string('cancel_reason')->nullable();
            }

            if (! Schema::hasColumn('orders', 'discount_amount')) {
                $table->decimal('discount_amount', 12, 2)->nullable();
            }
        });

        DB::table('orders')
            ->where('id', $orderId)
            ->update([
                'customer_address' => 'Barrio Centro, Tegucigalpa',
                'cancel_reason' => 'Cliente cambio horario',
                'discount_amount' => 18,
            ]);

        $notificationId = (string) Str::uuid();

        DB::table('notifications')->insert([
            'id' => $notificationId,
            'type' => 'App\\Notifications\\OrderStatusChangedNotification',
            'notifiable_type' => $user->getMorphClass(),
            'notifiable_id' => (string) $user->id,
            'data' => json_encode(['title' => 'Pedido actualizado']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        /** @var object{up: callable():void} $migration */
        $migration = require base_path('database/migrations/2026_04_15_120000_consolidate_orders_and_notifications_wave3.php');
        $migration->up();

        $this->assertTrue(Schema::hasTable('orders_legacy_snapshot'));
        $this->assertTrue(Schema::hasTable('notifications_legacy_snapshot'));

        $this->assertNotNull(
            DB::table('orders_legacy_snapshot')
                ->where('order_id', $orderId)
                ->first()
        );

        $this->assertNotNull(
            DB::table('notifications_legacy_snapshot')
                ->where('notification_id', $notificationId)
                ->first()
        );

        $this->assertFalse(Schema::hasColumn('orders', 'customer_address'));
        $this->assertFalse(Schema::hasColumn('orders', 'cancel_reason'));
        $this->assertFalse(Schema::hasColumn('orders', 'discount_amount'));

        $this->assertSame(
            'Barrio Centro, Tegucigalpa',
            DB::table('orders')->where('id', $orderId)->value('delivery_address')
        );

        $this->assertSame(
            'Cliente cambio horario',
            DB::table('orders')->where('id', $orderId)->value('cancellation_reason')
        );

        $this->assertSame(
            '18.00',
            (string) DB::table('orders')->where('id', $orderId)->value('discount')
        );

        $this->assertTrue($this->hasIndexWithColumns('orders', ['tenant_id', 'status', 'created_at'], false));
        $this->assertTrue($this->hasIndexWithColumns('orders', ['order_number'], true));
        $this->assertTrue($this->hasIndexWithColumns('notifications', ['notifiable_type', 'notifiable_id'], false));
    }

    /**
     * @param  array<int, string>  $columns
     */
    private function hasIndexWithColumns(string $table, array $columns, bool $unique): bool
    {
        $normalizedColumns = array_values($columns);

        return collect(Schema::getIndexes($table))
            ->contains(static function (array $index) use ($normalizedColumns, $unique): bool {
                $indexColumns = array_values((array) ($index['columns'] ?? []));
                $isUnique = (bool) ($index['unique'] ?? false);

                return $indexColumns === $normalizedColumns && $isUnique === $unique;
            });
    }
}
