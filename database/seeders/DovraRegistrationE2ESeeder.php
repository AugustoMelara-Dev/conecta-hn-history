<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Application\Markets\HondurasMarketBackfill;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Minimal, deterministic registration fixture for local/testing browser runs.
 *
 * This intentionally creates reference data only. It never creates users,
 * tenants, products, tokens, billing records, or provider state.
 */
final class DovraRegistrationE2ESeeder extends Seeder
{
    public function run(HondurasMarketBackfill $backfill): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new \LogicException(
                'DovraRegistrationE2ESeeder is restricted to local or testing environments.',
            );
        }

        DB::transaction(function () use ($backfill): void {
            $now = now();

            DB::table('categories')->updateOrInsert(
                ['slug' => 'restaurantes'],
                [
                    'name' => 'Restaurantes',
                    'icon' => 'utensils',
                    'is_active' => true,
                    'is_featured' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );

            // The legacy city schema permits a null municipality; the market
            // backfill still maps this city into the canonical localities table.
            DB::table('cities')->updateOrInsert(
                ['slug' => 'tegucigalpa'],
                [
                    'name' => 'Tegucigalpa',
                    'municipality_id' => null,
                    'is_active' => true,
                    'is_featured' => true,
                    'coordinates' => json_encode(['lat' => 14.0723, 'lng' => -87.1921], JSON_THROW_ON_ERROR),
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );

            $backfill->run();
        });
    }
}
