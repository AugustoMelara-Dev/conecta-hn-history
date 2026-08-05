<?php

declare(strict_types=1);

namespace Tests\Feature\Loyalty;

use App\Domain\Shared\Services\TenantContext;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LoyaltyIdempotencyMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_backfills_a_legacy_earn_before_the_writer_depends_on_the_unique_key(): void
    {
        [$tenant, $user, $order] = $this->fixture();
        $ledgerId = $this->insertLegacyEarn($tenant, $user, $order);

        $this->migration()->up();

        self::assertSame(
            "order:{$order->getKey()}:earn",
            DB::table('loyalty_ledger')->where('id', $ledgerId)->value('idempotency_key'),
        );
    }

    public function test_it_fails_closed_when_historical_awards_are_already_duplicated(): void
    {
        [$tenant, $user, $order] = $this->fixture();
        $this->insertLegacyEarn($tenant, $user, $order);
        $this->insertLegacyEarn($tenant, $user, $order);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('duplicate historical earn entries');

        $this->migration()->up();
    }

    /** @return array{Tenant, User, Order} */
    private function fixture(): array
    {
        app(TenantContext::class)->setAsSystemRoot();
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
        ]);

        return [$tenant, $user, $order];
    }

    private function insertLegacyEarn(Tenant $tenant, User $user, Order $order): int
    {
        return (int) DB::table('loyalty_ledger')->insertGetId([
            'tenant_id' => $tenant->getKey(),
            'user_id' => $user->getKey(),
            'order_id' => $order->getKey(),
            'idempotency_key' => null,
            'type' => 'earn',
            'amount' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_07_22_170000_add_loyalty_award_idempotency.php');
    }
}
